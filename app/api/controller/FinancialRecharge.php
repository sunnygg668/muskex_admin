<?php

namespace app\api\controller;

use app\admin\model\ba\financial\PaymentMethod;
use app\admin\model\ba\financial\PaymentChannel;
use app\admin\model\ba\financial\Recharge;
use app\admin\model\ba\user\Assets;
use app\common\controller\Frontend;
use app\common\model\BussinessLog;
use ba\Exception;
use GuzzleHttp\Client;
use think\db\Query;
use think\facade\Db;
use think\facade\Config;
use think\facade\Log;
use app\custom\library\KfpaySign;

class FinancialRecharge extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function methodList()
    {
        $channelId = $this->request->param('channelId');
        $otherWhere = [];
        $where = [];
        if (!empty($channelId)) {
            $where[] = ['id', '=', $channelId];
            $otherWhere[] = ['channel_id', '=', $channelId];
        }
        $mainCoinPrice = Assets::mainCoinPrice();
        $rechargeMoneyTip = get_sys_config('recharge_money_tip');
        $methodList = [];
        $channelList = PaymentChannel::where('status', 1)->where($where)->field('id,channel_name')->select()->toArray();
        $quickMethodList = PaymentMethod::where(['status' => 1, 'type' => '0'])->where($otherWhere)->select()->toArray();
        $otherMethodList = PaymentMethod::where('status', 1)->where('type', 'in', ['1', '2', '3'])->where($otherWhere)->field('id,name,short_name,type,min_amount,max_amount')->order('weigh desc')->select()->toArray();
        if ($quickMethodList) {
            $minAmountValues = array_column($quickMethodList, 'min_amount');
            $maxAmountValues = array_column($quickMethodList, 'max_amount');
            $minAmount = min($minAmountValues);
            $maxAmount = max($maxAmountValues);
            $methodList[] = [
                'id' => -1,
                'name' => '快捷支付',
                'short_name' => '快捷支付',
                'type' => '0',
                'min_amount' => $minAmount,
                'max_amount' => $maxAmount,
            ];
        }
        $methodList = array_merge($methodList, $otherMethodList);
        $result = [
            'mainCoinPrice' => $mainCoinPrice,
            'rechargeMoneyTip' => $rechargeMoneyTip,
            'methodList' => $methodList,
            'channelList' => $channelList,
        ];
        $this->success('', $result);
    }

    public function submitRecharge()
    {
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $methodId = $this->request->param('methodId');
        $name = $this->request->param('name');
        $amount = $this->request->param('amount');

        if (empty($user->is_certified) || empty($user->idcard) || $user->is_certified != 2) {
            $this->error('请先完成实名认证');
        }
        BussinessLog::record('submitRecharge支付：' . json_encode(['user' => $user, 'methodId' => $methodId, 'name' => $name, 'amount' => $amount]));

        $method = PaymentMethod::where(['status' => 1, 'id' => $methodId])->find();

        if (!$method || $method->status == 0) {
            $this->error('该支付方式暂不可用，请刷新重试');
        }

        $minAmount = $method->min_amount;
        $maxAmount = $method->max_amount;
        if (!empty($minAmount) && !empty($maxAmount)) {
            if ($amount < $minAmount || $amount > $maxAmount) {
                $this->error('充值金额的范围为：' . $minAmount . ' - ' . $maxAmount);
            }
        }

        $mainCoinPrice = Assets::mainCoinPrice();
        $feeRatio = $method->fee_ratio;
        $fee = bcmul($amount, $feeRatio / 100, 2);
        $actualMoney = bcsub($amount, $fee, 2);
        $mainCoinNum = bcdiv($actualMoney, $mainCoinPrice, 2);
        $mainCoinFee = bcdiv($fee, $mainCoinPrice, 2);
        Db::startTrans();
        try {
            $orderNo = 'FR' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $rechargeData = [
                'user_id' => $userId,
                'order_no' => $orderNo,
                'name' => $name,
                'financial_payment_method_id' => $method->id,
                'amount' => $amount,
                'fee_ratio' => $feeRatio,
                'fee' => $fee,
                'actual_money' => $actualMoney,
                'main_coin_num' => $mainCoinNum,
                'main_coin_fee' => $mainCoinFee,
                'coin_price' => $mainCoinPrice
            ];
            Recharge::create($rechargeData);
            $url = null;
//            if ($method->type == 0) {
//                $url = $this->quickPay1($method, $amount, $orderNo);
//            }
            if ($method->type != 1) {
                if (!empty($method->channel_as)) {
                    $url = $this->{$method->channel_as.'quickPay'}($method, $amount, $orderNo, $name);
                    BussinessLog::record('submitRecharge返回111：' . json_encode(['url' => $url]));
                } else {
                    $url = $this->quickPay2($method, $amount, $orderNo);
                }
            }
            BussinessLog::record('submitRecharge返回：' . json_encode(['url' => $url]));

            Db::commit();
            if ($url) {
                $this->success('', ['url' => $url]);
            } else {
                $this->success('提交成功，等待商家确认');
            }
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    public function submitRecharge2() {
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $methodId = $this->request->param('methodId');
        $channelId = $this->request->param('channelId');
        $name = $this->request->param('name');
        $amount = $this->request->param('amount');

        if (empty($user->is_certified) || empty($user->idcard) || $user->is_certified != 2) {
            $this->error('请先完成实名认证');
        }

        $channel = PaymentChannel::find($channelId);
        if (!$channel || $channel->status == 0) {
            $this->error('该支付通道暂不可用，请刷新重试');
        }
        $method = null;
        switch ($methodId) {
            case -1:
                $quickMethodList = PaymentMethod::where(['status' => 1, 'type' => '0', 'channel_id' => $channelId])->select();
                foreach ($quickMethodList as $quickMethod) {
                    if (empty($quickMethod->min_amount) && empty($quickMethod->max_amount)) {
                        $method = $quickMethod;
                        break;
                    }
                    if ($amount >= $quickMethod->min_amount && $amount <= $quickMethod->max_amount) {
                        $method = $quickMethod;
                        break;
                    }
                }
                break;
            case 2:
            case 3:
                $methodList = PaymentMethod::where(['status' => 1, 'type' => $methodId, 'channel_id' => $channelId])->select();
                foreach ($methodList as $quickMethod) {
                    if (empty($quickMethod->min_amount) && empty($quickMethod->max_amount)) {
                        $method = $quickMethod;
                        break;
                    }
                    if ($amount >= $quickMethod->min_amount && $amount <= $quickMethod->max_amount) {
                        $method = $quickMethod;
                        break;
                    }
                }
                break;
            default:
                $method = PaymentMethod::find($methodId);
        }
        if (!$method || $method->status == 0) {
            $this->error('该支付方式暂不可用，请刷新重试');
        }

        $mainCoinPrice = Assets::mainCoinPrice();
        $feeRatio = $method->fee_ratio;
        $fee = bcmul($amount, $feeRatio / 100, 2);
        $actualMoney = bcsub($amount, $fee, 2);
        $mainCoinNum = bcdiv($actualMoney, $mainCoinPrice, 2);
        $mainCoinFee = bcdiv($fee, $mainCoinPrice, 2);
        Db::startTrans();
        try {
            $orderNo = 'FR' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $rechargeData = [
                'user_id' => $userId,
                'order_no' => $orderNo,
                'name' => $name,
                'financial_payment_method_id' => $method->id,
                'amount' => $amount,
                'fee_ratio' => $feeRatio,
                'fee' => $fee,
                'actual_money' => $actualMoney,
                'main_coin_num' => $mainCoinNum,
                'main_coin_fee' => $mainCoinFee,
                'coin_price' => $mainCoinPrice
            ];
            Recharge::create($rechargeData);
            $url = null;
            if ($method->type != 1) {
                if (!empty($channel->channel_as)) {
                    $url = $this->{$channel->channel_as.'quickPay'}($method, $channel, $amount, $orderNo, $name);
                } else {
                    $url = $this->quickPay2($method, $amount, $orderNo);
                }
            }
            Db::commit();
            if ($url) {
                $this->success('', ['url' => $url]);
            } else {
                $this->success('提交成功，等待商家确认');
            }
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    private function kfquickPay($method, $channel, $amount, $orderNo, $name = '') {
        $notifyUrl = Config::get('app.pay_notifyurl') . '/api/quick_pay/kfnotify';
        // 商户私钥
        $merchant_private_key = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDIr7d+k7q8q7HV2xSs84ZrQYe8VIU8z+9kjPmdFvFXzMsjPKJ1Pf8lYLcdIQGEmWH7VXSf9JlsopXUdBFDC3+7DoCBNNXZcJ1Ate5Ms72QWnu7Mc6TFTFHgH10DMlLC2nfdT3188Ig49822D0kg2Mk/9A9Ev+q83YORT8doVY3ZI3kFP+tTVuCkwkwU5b9PpJJXOC1+AfXENTlyhwp8rSY278MjplqenCcERSpist5CwNR400nUFY/0gUm9L2SDClQ+5KpMaS3HEWtJbNBO3qaRW0cwaXB8/5qr93WRGVTl+f0RoEcKhAyk74dVrdnrE7I0QCWdWoeiqY0foff4Iq5AgMBAAECggEBAJKEL1xbMJ9kXr56ZzrpxH5z5kxqDKhcSzPG9MpqoKLwYnKEZztfmvLIDIhYu4n6jZ14Q2AwrP6yKN9mKeAf+g4wRUOPtn08iaJf8O3Q4BoO5XzCnUWm2EaSmYTrfN6sI4zARQ9W+qkWlLWu49zPi8fMeFTOlRhTDSLYDX9tdcuT8qwry120K5MdSc0aKSmVviWn/9YcbwsKFwlihJYRj55zw517cOXo1+y2aTV26FCX+LCC0tzrAxTHh0ziJ69ZtjzsEntzww8iLtHxKvOhHxlaXOBh/Lr1S/SO+/NqZAWUJvl8e+LPjHWXijUO91IJby+Zb35mBjTykgIVAQKsWfECgYEA+ib3Y3fpfWHhHTXGWcwldAGgTNjUO3FWT7FlKdsZBD3pEUQ7KU4jloLzVUES8lFybz5fszIPkdYIyEe3JD1o2ctdFDmgmD9Guu9hHu3Rtyisp1tBlOi/5KlmMwYS7+ephC0ycVD+3mtS3pMx2AnWrgCqHUV6vcQ+wCgK9oal2iUCgYEAzWC5AQSUvJRKdjhOXB+tgeLhi8+XWZS0TCPmxhmXgDva1PDRPrKvwT94qXIF+Mczr0UI1bJMhaTCmPdPQI9D7nUJXv5+3Cj2eU2/M+xzK7872IFGYfdJ4etN/5Jr0CHaqk7y8jcRUumbtpGJjze8VeiI04iX/MpkxddWi/kfqAUCgYAXUGJmJtrgEKtGaaie3ePvt+cUwnClSZ7dto7TI9RlDNAYB9/rrZirgnDjVTlK1ERyEcIhlVzWHria0fRDsGWBRu7Z37UT+3HAImdO1qNhCq1su5iVJEweJ5uazcoeCd1GsF+vJ/lZCW+jxtwyYlhbxzwTllImNZAToKfE6i+y8QKBgQCXYth7+5h3eQd6JP79wQvwVgDTQe3aRlawWTZeh7a4+2XO2MQkZypOVC7pF/d27b8XTte4TXlCebRHdOSiPfhg6TxDHWz9c+RafgiWiZBLiubeLExpIhL/yKbx4EhAXvQD3bYO/LB0YWY6KQUw4HCfEozpACKoX7fwbU6KVtQS0QKBgAufd4hL6YSE7uP5YSBdAZ7Exx47S2U2R78NkiA61gnGOct/rump3lQDNkSDj0AIZwLbYrNCA87RWj4xV8aGf5mQVdTJB4c4widjhonRKCtgK2uP0wTDqNC3zh1R9ckp6cDcezwzPlG5WqFjNNXKAHxR1QpTPxCUlQq0yv9y5Z3y';

        // 支付下单
        $reqArr = array(
            "memberid"  => $method->merchant_num,
            "appid"     => $channel->appid,
            "bankcode"  => $method->payment_channels,
            "orderid"   => $orderNo,
            "applydate" => date('Y-m-d H:i:s'),
            "amount"    => $amount,
            "notify_url"=> $notifyUrl,
            "return_url"=> "",
            "attach"    => $name,
            "sign_type" => "RSA2",
        );
        $str = KfpaySign::getSignContent($reqArr);
        $sign_str = KfpaySign::RSA2sign($str,$merchant_private_key);
        //添加sign
        $reqArr["sign"] = $sign_str;
        $res = KfpaySign::post($method->url,$reqArr);
        $resData = json_decode($res,true);

        if (isset($resData['status']) && $resData['status'] == 'ok') {
            return $resData['pay_url'];
        } else {
            $this->error('充值通道繁忙，请稍后再试！');
        }
    }

    private function quickPay1($method, $amount, $orderNo)
    {
        $notifyUrl = Config::get('app.pay_notifyurl') . '/api/quick_pay/notify1?server=1';
        $params = [
            'agent_id' => $method->merchant_num,
            'channel_id' => $method->payment_channels,
            'subject' => 'Recharge_' . $amount,
            'totalAmount' => strval($amount),
            'outTradeNo' => $orderNo,
            'notify_url' => $notifyUrl,
            'tranIP' => request()->ip(),
        ];
        ksort($params);
        $paramsStr = implode('', $params);
        $params['sign'] = md5($paramsStr . $method->encryption_key);
        $params['returnUrl'] = '';
        $params['quit_url'] = $notifyUrl;
        $client = new Client();
        $response = $client->post($method->url, [
            'form_params' => $params
        ]);
        $resContent = $response->getBody()->getContents();
        $resData = json_decode($resContent, true);

        $resData = empty($resData['msg']) ? $resData : ['ret_code'=>0,'ret_msg'=>$resData['msg']];

        if ($resData['ret_code'] == 'S200') {
            return $resData['url'];
        } else {
            $this->error($resData['ret_msg']);
        }
    }

    private function quickPay2($method, $amount, $orderNo)
    {
        $notifyUrl = Config::get('app.pay_notifyurl') . '/api/quick_pay/notify2?server=1';
        $params = [
            'pay_memberid' => $method->merchant_num,
            'pay_orderid' => $orderNo,
            'pay_amount' => $amount,
            'pay_applydate' => date('Y-m-d H:i:s'),
            'pay_bankcode' => $method->payment_channels,
            'pay_notifyurl' => $notifyUrl,
            'pay_callbackurl' => '',
        ];
        ksort($params);
        $paramsArray = [];
        foreach ($params as $k => $v) {
            if ($v) {
                $paramsArray[] = $k . '=' . $v;
            }
        }
        $paramsStr = implode('&', $paramsArray);
        $params['pay_md5sign'] = strtoupper(md5($paramsStr . '&key=' . $method->encryption_key));
        $params['pay_productname'] = 'VIP基础服务';
        BussinessLog::record('支付通道请求数据：' . json_encode($params, JSON_UNESCAPED_UNICODE));

        $client = new Client();
        $response = $client->post($method->url, [
            'form_params' => $params
        ]);
        $resContent = $response->getBody()->getContents();
        $resData = json_decode($resContent, true);
        if (isset($resData['code']) && $resData['code'] == '200') {
            return $resData['data'];
        } else {
            $this->error('充值通道繁忙，请稍后再试！');
        }
    }

    public function quickPay3($method, $amount, $orderNo)
    {
        $notifyUrl = Config::get('app.pay_notifyurl') . '/api/quick_pay/notify3?server=1';
        $params = [
            'pay_memberid' => $method->merchant_num,
            'pay_orderid' => $orderNo,
            'pay_amount' => $amount,
            'pay_applydate' => date("Y-m-d H:i:s"),
            'pay_bankcode' => $method->payment_channels,
            'pay_notifyurl' => $notifyUrl,
            'pay_callbackurl' => '',
        ];
        ksort($params);
        $paramsArray = [];
        foreach ($params as $k => $v) {
            if ($v) {
                $paramsArray[] = $k . '=' . $v;
            }
        }
        $paramsStr = implode('&', $paramsArray);
        $params['pay_md5sign'] = strtoupper(md5($paramsStr . '&key=' . $method->encryption_key));
        $params['pay_productname'] = 'VIP基础服务';
        $client = new Client();
        $response = $client->post($method->url, [
            'form_params' => $params
        ]);
        $resContent = $response->getBody()->getContents();
        $resData = json_decode($resContent, true);
        if (isset($resData['code']) && $resData['code'] == '200') {
            return $resData['data'];
        } else {
            $this->error($resData['msg']);
        }
    }

    public function rechargeList()
    {
        $userId = $this->auth->id;
        $beginTime = $this->request->param('beginTime');
        $endTime = $this->request->param('endTime');
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);
        $query = function (Query $query) use ($beginTime, $endTime) {
            if ($beginTime && $endTime) {
                $beginTime .= ' 00:00:00';
                $endTime .= ' 23:59:59';
                $query->whereBetweenTime('create_time', $beginTime, $endTime);
            }
        };
        $list = Recharge::where('user_id', $userId)
            ->where($query)
            ->order('id desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]);
        $result = ['list' => $list];
        $this->success('', $result);
    }

    public function detail()
    {
        $userId = $this->auth->id;
        $id = request()->param('id');
        $recharge = Recharge::where(['user_id' => $userId, 'id' => $id])->find();
        $this->success('', $recharge);
    }
}
