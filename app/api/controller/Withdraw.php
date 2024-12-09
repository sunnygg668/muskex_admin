<?php

namespace app\api\controller;

use app\admin\model\ba\coin\Coin;
use app\admin\model\ba\coin\Recharge;
use app\admin\model\ba\financial\Card;
use app\admin\model\ba\financial\Withdraw as WithdrawModel;
use app\admin\model\ba\user\Assets;
use app\common\controller\Frontend;
use app\custom\library\{RedisUtil,Ali};
use ba\Exception;
use think\db\Query;
use think\facade\Db;
use app\admin\model\ba\financial\Address as AddressesModel;

class Withdraw extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function info()
    {
        $userId = $this->auth->id;
        $balance = Assets::mainCoinAssets($userId)->balance;
        $usdtPrice = Assets::mainCoinPrice();
        $cardList = Card::where(['user_id' => $userId, 'status' => '1'])
            ->with(['financialBank' => function (Query $query) {
                $query->field('id, name, logo');
            }])
            ->order('id desc')->select();
        $openWithdrawUsdt = (bool)get_sys_config('open_withdraw_usdt');
        $openWithdrawMoney = (bool)get_sys_config('open_withdraw_money');
        $withdrawMinNum = get_sys_config('withdraw_min_num');
        $withdrawMaxNum = get_sys_config('withdraw_max_num');
        $withdrawMinCoinNum = get_sys_config('withdraw_min_coin_num');
        $withdrawMaxCoinNum = get_sys_config('withdraw_max_coin_num');
        $withdrawRuleTip = get_sys_config('withdraw_rule_tip');
        $feeRatio = get_sys_config('withdraw_fee_ratio');
        $result = [
            'balance' => $balance,
            'usdtPrice' => $usdtPrice,
            'cardList' => $cardList,
            'openWithdrawUsdt' => $openWithdrawUsdt,
            'openWithdrawMoney' => $openWithdrawMoney,
            'withdrawMinNum' => $withdrawMinNum,
            'withdrawMaxNum' => $withdrawMaxNum,
            'withdrawMinCoinNum' => $withdrawMinCoinNum,
            'withdrawMaxCoinNum' => $withdrawMaxCoinNum,
            'withdrawRuleTip' => $withdrawRuleTip,
            'feeRatio' => $feeRatio
        ];
        $this->success('', $result);
    }

    public function apply(): void
    {
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $type = $this->request->param('type');
        $amount = $this->request->param('amount');
        $cardId = $this->request->param('cardId');
        $fundPassword = request()->param('fundPassword');
        $addressId = $this->request->param('addressId');

//        $certifyId = $this->request->param('certifyId'); //实人认证唯一标识
//
//        if (empty($certifyId) && empty($fundPassword)) {
//            $this->error('非法操作！');
//        }
//
//        // 判断实名认证结果
//        if (!empty($certifyId)) {
//            $result = Ali::certificationResults($certifyId);
//            if ($result['code'] != 200 && $result['resultObject']['passed'] != 'T') {
//                $this->error('实名认证失败，无法提现');
//            }
//        }
        $card = Card::where(['user_id' => $userId, 'status' => 1])->find();
        if (!$card) {
            $this->error('请先绑定一张银行卡，并等待审核通过');
        }
        if ($user->is_can_withdraw == 0) {
            $this->error('当前账号暂时无法提现');
        }
        if ($user->is_activation == 0) {
            $this->error('活跃度不足，无法提现');
        }
        if (empty($user->is_certified) || empty($user->idcard) || $user->is_certified != 2) {
            $this->error('请先完成实名认证');
        }
        if ($user->limit_withdraw_time && $user->limit_withdraw_time > time()) {
            $this->error(date('Y-m-d H:i:s', $user->limit_withdraw_time) . ' 之前不可提现（安全保护期中）');
        }
        if (empty($fundPassword)) {
            $this->error('请输入资金密码');
        }
        if (!$this->auth->checkFundPassword($fundPassword)) {
            // 密码输入错误三次，自动关闭提现
            RedisUtil::incr('wrong_password_' . $userId, 600);
            $num = RedisUtil::getValue('wrong_password_' . $userId);
            if ($num >= 3) {
                $user->save([
                    'is_can_withdraw' => 0,
                ]);
            }
            $this->error('您输入的资金密码错误（还剩' . bcsub(3, $num) . '次机会）', null, 204);
        }

        $dayWithdrawNum = get_sys_config('day_withdraw_num');
        $dayWithdrawCount = WithdrawModel::where('user_id', $userId)/*->where('status', '<>', 2)*/->whereDay('create_time')->count();
        if ($dayWithdrawCount >= $dayWithdrawNum) {
            $this->error('当日提现次数已达到上限，暂时无法提现');//'当日提现次数已超过 ' . $dayWithdrawNum . '次，暂时无法提现'
        }
        $feeRatio = get_sys_config('withdraw_fee_ratio');
        $dayWithdrawFreeCount = get_sys_config('day_withdraw_free_count');
        if ($dayWithdrawCount < $dayWithdrawFreeCount) {
            $feeRatio = 0;
        }
        $orderNo = 'W' . date('Ymd') . str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        Db::startTrans();
        try {
            if ($type == 1) {
                $this->coinWithdraw($userId, $user, $type, $amount, $orderNo, $addressId, $feeRatio);
            } else if ($type == 0) {
                $this->bankWithdraw($userId, $user, $type, $amount, $orderNo, $cardId, $feeRatio);
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage(), null, $e->getCode());
        }
        $this->success('提现申请已提交');
    }

    private function coinWithdraw($userId, $user, $type, $amount, $orderNo, $addressId, $feeRatio)
    {
        $withdrawRechargeUsdtNum = get_sys_config('withdraw_recharge_usdt_num');
        $rechargeCoin = Recharge::where('user_id', $userId)->sum('amount');
        $totalRecharge = $rechargeCoin;
        if ($totalRecharge < $withdrawRechargeUsdtNum) {
            $mainCoin = Coin::mainCoin();
            $this->error('充值满 ' . $withdrawRechargeUsdtNum . ' ' . $mainCoin->name . ' 可提现');
        }
        $addressModel = AddressesModel::find($addressId);
        if (!$addressModel) {
            $this->error('钱包地址不存在');
        }
        $address = $addressModel->address;
        $coinPrice = Assets::mainCoinPrice();
        $money = bcmul($amount, $coinPrice, 2);
        $feeMoney = bcmul($money, $feeRatio / 100, 2);
        $feeCoin = bcmul($amount, $feeRatio / 100, 2);
        $withdrawMinCoinNum = get_sys_config('withdraw_min_coin_num');
        $withdrawMaxCoinNum = get_sys_config('withdraw_max_coin_num');
        if ($amount < $withdrawMinCoinNum || $amount > $withdrawMaxCoinNum) {
            $this->error('可提币数量为：' . $withdrawMinCoinNum . ' - ' . $withdrawMaxCoinNum);
        }
        $assets = Assets::updateMainCoinAssetsBalance($userId, -$amount, 'coin_withdraw');
        $assets = Assets::updateMainCoinAssetsBalance($userId, -$feeCoin, 'coin_withdraw_fee');
        $withdraw = [
            'user_id' => $userId,
            'refereeid' => $user->refereeid,
            'team_leader_id' => $user->team_leader_id,
            'type' => $type,
            'coin_id' => $assets->coin_id,
            'order_no' => $orderNo,
            'money' => $money,
            'coin_num' => $amount,
            'price' => $coinPrice,
            'wallet_type' => 'TRC20',
            'wallet_address' => $address,
            'address_id' => $addressId,
            'fee_ratio' => $feeRatio,
            'fee_money' => $feeMoney,
            'fee_coin' => $feeCoin,
            'actual_money' => $money,
            'actual_coin' => $amount,
        ];
        WithdrawModel::create($withdraw);
        RedisUtil::set($orderNo, $address);
    }

    private function bankWithdraw($userId, $user, $type, $amount, $orderNo, $cardId, $feeRatio)
    {
        $coinPrice = Assets::mainCoinPrice();
        $coinNum = bcdiv($amount, $coinPrice, 2);
        $feeMoney = bcmul($amount, $feeRatio / 100, 2);
        $feeCoin = bcmul($coinNum, $feeRatio / 100, 2);
        $withdrawMinNum = get_sys_config('withdraw_min_num');
        $withdrawMaxNum = get_sys_config('withdraw_max_num');
        if ($amount < $withdrawMinNum || $amount > $withdrawMaxNum) {
            $this->error('可提现金额为：' . $withdrawMinNum . ' - ' . $withdrawMaxNum);
        }
        $assets = Assets::updateMainCoinAssetsBalance($userId, -$coinNum, 'coin_withdraw');
        $assets = Assets::updateMainCoinAssetsBalance($userId, -$feeCoin, 'coin_withdraw_fee');
        $withdraw = [
            'user_id' => $userId,
            'refereeid' => $user->refereeid,
            'team_leader_id' => $user->team_leader_id,
            'type' => $type,
            'coin_id' => $assets->coin_id,
            'financial_card_id' => $cardId,
            'order_no' => $orderNo,
            'money' => $amount,
            'coin_num' => $coinNum,
            'price' => $coinPrice,
            'fee_ratio' => $feeRatio,
            'fee_money' => $feeMoney,
            'fee_coin' => $feeCoin,
            'actual_money' => $amount,
            'actual_coin' => $coinNum,
        ];
        WithdrawModel::create($withdraw);
    }

    public function list()
    {
        $userId = $this->auth->id;
        $status = $this->request->param('status');
        $type   = $this->request->param('type', 0);// 提现类型 1 加密货币  0 法币
        $beginTime = $this->request->param('beginTime');
        $endTime = $this->request->param('endTime');
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);
        $w = ['user_id' => $userId];
        if ($status) {
            $w['status'] = $status;
        }
        $w['type']  = $type;

        $list = WithdrawModel::with([
                'coin' => function (Query $query) {
                    $query->field('id, name, logo_image, kline_type');
                },
                'financialCard' => function (Query $query) {
                    $query->field('id, account_name, bank_num, status');
                }
            ])
            ->where($w)
            ->where(function (Query $query) use ($beginTime, $endTime) {
                if ($beginTime && $endTime) {
                    $beginTime .= ' 00:00:00';
                    $endTime .= ' 23:59:59';
                    $query->whereBetweenTime('create_time', $beginTime, $endTime);
                }
            })
            ->order('id desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]
            );
        $result = [
            'list' => $list
        ];
        $this->success('', $result);
    }

    public function detail()
    {
        $userId = $this->auth->id;
        $id = $this->request->param('id');
        $withdraw = WithdrawModel::with([
                'coin' => function (Query $query) {
                    $query->field('id, name, logo_image, kline_type');
                },
                'financialCard' => function (Query $query) {
                    $query->field('id, account_name, bank_num, status, financial_bank_id')->with(['financialBank']);
                }
            ])
            ->where(['id' => $id, 'user_id' => $userId])
            ->find();
        $this->success('', $withdraw);
    }

}
