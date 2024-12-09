<?php

namespace app\api\controller;

use app\admin\model\ba\financial\PaymentMethod;
use app\admin\model\ba\financial\Recharge;
use app\admin\model\ba\user\Assets;
use app\common\controller\Frontend;
use app\common\model\BussinessLog;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use app\custom\library\KfpaySign;

class QuickPay extends Frontend
{
    protected array $noNeedLogin = ['*'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function notify1()
    {
        $agent_id = $this->request->post('agent_id');
        $ordernum = $this->request->post('ordernum');
        $trade_state = $this->request->post('trade_state');
        $trade_state_desc = $this->request->post('trade_state_desc');
        $transaction_date = $this->request->post('transaction_date');
        $transaction_money = $this->request->post('transaction_money');
        $sign = $this->request->post('sign');
        $checkSign = null;
        Db::startTrans();
        try {
            $recharge = Recharge::where(['order_no' => $ordernum, 'status' => 0])->find();
            if ($recharge) {
                $method = PaymentMethod::find($recharge->financial_payment_method_id);
                $params = [
                    'agent_id' => $agent_id,
                    'ordernum' => $ordernum,
                    'trade_state' => $trade_state,
                    'trade_state_desc' => $trade_state_desc,
                    'transaction_date' => $transaction_date,
                    'transaction_money' => $transaction_money,
                ];
                ksort($params);
                $paramsStr = implode('', $params);
                $checkSign = md5($paramsStr . $method->encryption_key);
                if ($sign == $checkSign) {
                    if ($trade_state == 'SUCCESS') {
                        $recharge->save(['status' => 1]);
                        Assets::updateMainCoinAssetsBalance($recharge->user_id, $recharge->main_coin_num, 'financial_recharge');
                    } else if ($trade_state == 'TRADE_CLOSED') {
                        $recharge->save(['status' => 2]);
                    }
                }
            }
            Db::commit();
            if ($recharge && $checkSign && $sign == $checkSign && $trade_state == 'SUCCESS') {
                Queue::push('\app\custom\job\RewardQueue@userFirstRecharge', ['user_id' => $recharge->user_id], 'reward');
                Queue::push('\app\custom\job\TaskRewardQueue@firstRechargeReachedGive', ['user_id' => $recharge->user_id, 'amount' => $recharge->amount], 'task_reward');
                Queue::push('\app\custom\job\TaskRewardQueue@todayRechargeReachedGive', ['user_id' => $recharge->user_id], 'task_reward');
                Queue::push('\app\custom\job\RewardQueue@giveLotteryCount', ['user_id' => $recharge->user_id, 'coinAmount' => $recharge->main_coin_num], 'reward');
            }
            return "success";
        } catch (\Exception $e) {
            Db::rollback();
            BussinessLog::record('快捷支付回调异常：' . $e->getMessage());
            return "err";
        }
    }

    public function notify2()
    {
        $memberid = $this->request->post('memberid');
        $orderid = $this->request->post('orderid');
        $amount = $this->request->post('amount');
        $transaction_id = $this->request->post('transaction_id');
        $datetime = $this->request->post('datetime');
        $returncode = $this->request->post('returncode');
        $sign = $this->request->post('sign');
        $checkSign = null;
        BussinessLog::record('快捷支付回调数据 ：' . json_encode($_REQUEST, JSON_UNESCAPED_UNICODE));
        Db::startTrans();
        try {
            $recharge = Recharge::where(['order_no' => $orderid, 'status' => 0])->find();
            if ($recharge) {
                $method = PaymentMethod::find($recharge->financial_payment_method_id);
                $params = [
                    'memberid' => $memberid,
                    'orderid' => $orderid,
                    'amount' => $amount,
                    'transaction_id' => $transaction_id,
                    'datetime' => $datetime,
                    'returncode' => $returncode,
                ];
                ksort($params);
                $paramsArray = [];
                foreach ($params as $k => $v) {
                    if ($v) {
                        $paramsArray[] = $k . '=' . $v;
                    }
                }
                $paramsStr = implode('&', $paramsArray);
                $checkSign = strtoupper(md5($paramsStr . '&key=' . $method->encryption_key));
                if ($sign == $checkSign) {
                    if ($returncode == '00') {
                        $recharge->save(['status' => 1]);
                        Assets::updateMainCoinAssetsBalance($recharge->user_id, $recharge->main_coin_num, 'financial_recharge');

                        // 法币每一笔充值，限制提现时间
                        $newCardWithdrawalInterval = get_sys_config('per_recharge_withdrawal_interval');
                        $limitWithdrawTime = strtotime('+' . $newCardWithdrawalInterval . ' hour');
                        (new \app\admin\model\User())->where(['id' => $recharge->user_id])->update(['limit_withdraw_time' => $limitWithdrawTime]);
                    } else {
                        $recharge->save(['status' => 2]);
                    }
                }
            }
            Db::commit();
            if ($recharge && $checkSign && $sign == $checkSign && $returncode == '00') {
                Queue::push('\app\custom\job\RewardQueue@userFirstRecharge', ['user_id' => $recharge->user_id], 'reward');
                Queue::push('\app\custom\job\TaskRewardQueue@firstRechargeReachedGive', ['user_id' => $recharge->user_id, 'amount' => $recharge->amount], 'task_reward');
                Queue::push('\app\custom\job\TaskRewardQueue@todayRechargeReachedGive', ['user_id' => $recharge->user_id], 'task_reward');
                Queue::push('\app\custom\job\RewardQueue@giveLotteryCount', ['user_id' => $recharge->user_id, 'coinAmount' => $recharge->main_coin_num], 'reward');
            }
            exit("OK");
        } catch (\Exception $e) {
            Db::rollback();
            BussinessLog::record('快捷支付回调异常：' . $e->getMessage());
            return "err";
        }
    }

    public function notify3()
    {
        $memberid = $this->request->post('memberid');
        $orderid = $this->request->post('orderid');
        $amount = $this->request->post('amount');
        $transaction_id = $this->request->post('transaction_id');
        $datetime = $this->request->post('datetime');
        $returncode = $this->request->post('returncode');
        $sign = $this->request->post('sign');
        $checkSign = null;
        Db::startTrans();
        try {
            $recharge = Recharge::where(['order_no' => $orderid, 'status' => 0])->find();
            if ($recharge) {
                $method = PaymentMethod::find($recharge->financial_payment_method_id);
                $params = [
                    'memberid' => $memberid,
                    'orderid' => $orderid,
                    'amount' => $amount,
                    'transaction_id' => $transaction_id,
                    'datetime' => $datetime,
                    'returncode' => $returncode,
                ];
                ksort($params);
                $paramsArray = [];
                foreach ($params as $k => $v) {
                    if ($v) {
                        $paramsArray[] = $k . '=' . $v;
                    }
                }
                $paramsStr = implode('&', $paramsArray);
                $checkSign = strtoupper(md5($paramsStr . '&key=' . $method->encryption_key));
                if ($sign == $checkSign) {
                    if ($returncode == '00') {
                        $recharge->save(['status' => 1]);
                        Assets::updateMainCoinAssetsBalance($recharge->user_id, $recharge->main_coin_num, 'financial_recharge');
                    } else {
                        $recharge->save(['status' => 2]);
                    }
                }
            }
            Db::commit();
            if ($recharge && $checkSign && $sign == $checkSign && $returncode == '00') {
                Queue::push('\app\custom\job\RewardQueue@userFirstRecharge', ['user_id' => $recharge->user_id], 'reward');
                Queue::push('\app\custom\job\TaskRewardQueue@firstRechargeReachedGive', ['user_id' => $recharge->user_id, 'amount' => $recharge->amount], 'task_reward');
                Queue::push('\app\custom\job\TaskRewardQueue@todayRechargeReachedGive', ['user_id' => $recharge->user_id], 'task_reward');
                Queue::push('\app\custom\job\RewardQueue@giveLotteryCount', ['user_id' => $recharge->user_id, 'coinAmount' => $recharge->main_coin_num], 'reward');
            }
            return "ok";
        } catch (\Exception $e) {
            Db::rollback();
            BussinessLog::record('快捷支付回调异常：' . $e->getMessage());
            return "err";
        }
    }

    public function kfnotify() {
        BussinessLog::record('kfpay支付回调数据 ：' . json_encode($_REQUEST, JSON_UNESCAPED_UNICODE));

        $queryGateway = 'https://www.kfpay.vip/pay/trade/query.do'; //订单查询接口
        //商户私钥
        $merchant_private_key = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDIr7d+k7q8q7HV2xSs84ZrQYe8VIU8z+9kjPmdFvFXzMsjPKJ1Pf8lYLcdIQGEmWH7VXSf9JlsopXUdBFDC3+7DoCBNNXZcJ1Ate5Ms72QWnu7Mc6TFTFHgH10DMlLC2nfdT3188Ig49822D0kg2Mk/9A9Ev+q83YORT8doVY3ZI3kFP+tTVuCkwkwU5b9PpJJXOC1+AfXENTlyhwp8rSY278MjplqenCcERSpist5CwNR400nUFY/0gUm9L2SDClQ+5KpMaS3HEWtJbNBO3qaRW0cwaXB8/5qr93WRGVTl+f0RoEcKhAyk74dVrdnrE7I0QCWdWoeiqY0foff4Iq5AgMBAAECggEBAJKEL1xbMJ9kXr56ZzrpxH5z5kxqDKhcSzPG9MpqoKLwYnKEZztfmvLIDIhYu4n6jZ14Q2AwrP6yKN9mKeAf+g4wRUOPtn08iaJf8O3Q4BoO5XzCnUWm2EaSmYTrfN6sI4zARQ9W+qkWlLWu49zPi8fMeFTOlRhTDSLYDX9tdcuT8qwry120K5MdSc0aKSmVviWn/9YcbwsKFwlihJYRj55zw517cOXo1+y2aTV26FCX+LCC0tzrAxTHh0ziJ69ZtjzsEntzww8iLtHxKvOhHxlaXOBh/Lr1S/SO+/NqZAWUJvl8e+LPjHWXijUO91IJby+Zb35mBjTykgIVAQKsWfECgYEA+ib3Y3fpfWHhHTXGWcwldAGgTNjUO3FWT7FlKdsZBD3pEUQ7KU4jloLzVUES8lFybz5fszIPkdYIyEe3JD1o2ctdFDmgmD9Guu9hHu3Rtyisp1tBlOi/5KlmMwYS7+ephC0ycVD+3mtS3pMx2AnWrgCqHUV6vcQ+wCgK9oal2iUCgYEAzWC5AQSUvJRKdjhOXB+tgeLhi8+XWZS0TCPmxhmXgDva1PDRPrKvwT94qXIF+Mczr0UI1bJMhaTCmPdPQI9D7nUJXv5+3Cj2eU2/M+xzK7872IFGYfdJ4etN/5Jr0CHaqk7y8jcRUumbtpGJjze8VeiI04iX/MpkxddWi/kfqAUCgYAXUGJmJtrgEKtGaaie3ePvt+cUwnClSZ7dto7TI9RlDNAYB9/rrZirgnDjVTlK1ERyEcIhlVzWHria0fRDsGWBRu7Z37UT+3HAImdO1qNhCq1su5iVJEweJ5uazcoeCd1GsF+vJ/lZCW+jxtwyYlhbxzwTllImNZAToKfE6i+y8QKBgQCXYth7+5h3eQd6JP79wQvwVgDTQe3aRlawWTZeh7a4+2XO2MQkZypOVC7pF/d27b8XTte4TXlCebRHdOSiPfhg6TxDHWz9c+RafgiWiZBLiubeLExpIhL/yKbx4EhAXvQD3bYO/LB0YWY6KQUw4HCfEozpACKoX7fwbU6KVtQS0QKBgAufd4hL6YSE7uP5YSBdAZ7Exx47S2U2R78NkiA61gnGOct/rump3lQDNkSDj0AIZwLbYrNCA87RWj4xV8aGf5mQVdTJB4c4widjhonRKCtgK2uP0wTDqNC3zh1R9ckp6cDcezwzPlG5WqFjNNXKAHxR1QpTPxCUlQq0yv9y5Z3y';
        //平台公钥
        $server_public_key = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA4Vj6JIbaryphTPbetLjBucEk+fBNVh00gY7pOjLlJKZwVSWeXezZGplXIwZYdaxg76klcGl1Psdy2n37WJTuCbzf/wjxBeg1QbANTNmP/8GlfQIl23CxYMlEWNpJTw7Ntls18f3IcviVm2GCjfdjQ5yUIiym2SixLsifzAsV8U31qcnf6qNw6T2QyEgjllcVr23fdQKo7fjFbpC2d2GLadj9YEf19wDnRoh74C5nE232Hc1HEpxueXWzkohIMi+3P0Krvz0f3UWAlUZL2WP4LyVL1lEVON9d6ndCFK0xYrgmVcP+TSjSgit4Z67en3Cu3hh9w/k2kVV3IZnE3gTGrwIDAQAB';
        $returnArray = array( // 返回字段
            "memberid"  => $_REQUEST["memberid"], // 商户ID
            "appid"     => $_REQUEST["appid"], // appid
            "bankcode"  => $_REQUEST["bankcode"],
            "amount"    =>  $_REQUEST["amount"], // 交易金额
            "amount_true" => $_REQUEST["amount_true"],
            "orderid"   =>  $_REQUEST["orderid"], // 商户订单号
            "sys_orderid" => $_REQUEST["sys_orderid"],
            "datetime"  =>  $_REQUEST["datetime"], // 交易时间
            "status"    =>  $_REQUEST["status"], // 订单状 成功:SUCCESS，失败:FAILURE
            "sign_type" =>  $_REQUEST["sign_type"],
            "sign"      =>  $_REQUEST["sign"],
        );
        $notify_sign = $returnArray['sign'];

        BussinessLog::record('kfpay支付回调数据 ：' . json_encode($returnArray, JSON_UNESCAPED_UNICODE));
        Db::startTrans();
        try {
            $recharge = Recharge::where(['order_no' => $returnArray['orderid'], 'status' => 0])->find();
            if ($recharge) {
                $str = KfpaySign::getSignContent($returnArray);
                if(KfpaySign::RSA2verify($str,$notify_sign,$server_public_key) && $returnArray['status'] == 'SUCCESS' ){
                    //二次查询订单是否为已支付
                    $params = [
                        "memberid"  => $returnArray['memberid'],
                        "appid"     => $returnArray['appid'],
                        "orderid"   => $returnArray['orderid'],
                    ];
                    $str1 = KfpaySign::getSignContent($params);
                    $sign_str = KfpaySign::RSA2sign($str1,$merchant_private_key);
                    $params["sign"] = $sign_str;
                    $params["sign_type"] = 'RSA2';
                    $res = KfpaySign::post($queryGateway,$params);
                    $resArr = json_decode($res,true);

                    if ($resArr['trade_state'] == 'SUCCESS') {
                        $recharge->save(['status' => 1]);
                        Assets::updateMainCoinAssetsBalance($recharge->user_id, $recharge->main_coin_num, 'financial_recharge');

                        // 法币每一笔充值，限制提现时间
                        $newCardWithdrawalInterval = get_sys_config('per_recharge_withdrawal_interval');
                        $limitWithdrawTime = strtotime('+' . $newCardWithdrawalInterval . ' hour');
                        (new \app\admin\model\User())->where(['id' => $recharge->user_id])->update(['limit_withdraw_time' => $limitWithdrawTime]);

                    } else {
                        $recharge->save(['status' => 2]);
                    }

                    Db::commit();
                    if ($recharge && $resArr['trade_state'] == 'SUCCESS') {
                        Queue::push('\app\custom\job\RewardQueue@userFirstRecharge', ['user_id' => $recharge->user_id], 'reward');
                        Queue::push('\app\custom\job\TaskRewardQueue@firstRechargeReachedGive', ['user_id' => $recharge->user_id, 'amount' => $recharge->amount], 'task_reward');
                        Queue::push('\app\custom\job\TaskRewardQueue@todayRechargeReachedGive', ['user_id' => $recharge->user_id], 'task_reward');
                        Queue::push('\app\custom\job\RewardQueue@giveLotteryCount', ['user_id' => $recharge->user_id, 'coinAmount' => $recharge->main_coin_num], 'reward');
                    }
                    exit('success');
                }
            }
            exit('fail');
        } catch (\Exception $e) {
            Db::rollback();
            BussinessLog::record('kfpay支付回调异常：' . $e->getMessage());
            exit('fail');
        }
    }
}
