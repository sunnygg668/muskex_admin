<?php

namespace app\api\controller;

use app\admin\model\ba\coin\Recharge;
use app\admin\model\ba\financial\Withdraw;
use app\admin\model\ba\user\Assets;
use app\common\controller\Frontend;
use app\common\model\BussinessLog;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use Udun\Dispatch\UdunDispatchException;

class UDun extends Frontend
{
    protected array $noNeedLogin = ['callback'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function callback()
    {
        $body = $_POST['body'];
        $nonce = $_POST['nonce'];
        $timestamp = $_POST['timestamp'];
        $sign = $_POST['sign'];
        BussinessLog::record('UDun充值回调数据 ：' . json_encode($_REQUEST, JSON_UNESCAPED_UNICODE));
        Db::startTrans();
        try {
            $uDunDispatch = \app\custom\library\UDun::uDunDispatch();
            $signCheck = $uDunDispatch->signature($body, $timestamp, $nonce);
            if ($sign != $signCheck) {
                throw new UdunDispatchException(-1, '签名错误: sign: ' . $sign . ', signCheck: ' . $signCheck);
            }
            $body = json_decode($body);
            if ($body->tradeType == 1) {
                if ($body->status == 3 && $body->mainCoinType == '195' && $body->coinType == 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t') {
                    $recharge = $this->rechargeArrives($body);
                }
            } elseif ($body->tradeType == 2) {
                if ($body->status == 0) {
                } else if ($body->status == 1) {
                    $this->withdrawPassed($body);
                } else if ($body->status == 2) {
                } else if ($body->status == 3) {
                } else if ($body->status == 4) {
                }
            }
            Db::commit();
            if ($body->tradeType == 1 && $body->status == 3 && !empty($recharge)) {
                Queue::push('\app\custom\job\RewardQueue@userFirstRecharge', ['user_id' => $recharge->user_id], 'reward');
                Queue::push('\app\custom\job\RewardQueue@giveLotteryCount', ['user_id' => $recharge->user_id, 'coinAmount' => $recharge->amount], 'reward');
            }
            return "success";
        } catch (\Exception $e) {
            Db::rollback();
            BussinessLog::record('UDun 充值回调异常：' . $e->getMessage());
            return "failed";
        }
    }

    private function rechargeArrives($data): ?Recharge
    {
        $amount = bcdiv($data->amount, pow(10, $data->decimals), 2);
        $uMinRecharge = get_sys_config('u_min_recharge');
        if ($amount < $uMinRecharge) {
            return null;
        }
        $assets = Assets::where(['address' => $data->address])->find();
        Assets::updateMainCoinAssetsBalance($assets->user_id, $amount, 'recharge_coin');
        $uRechargeGiveRatio = get_sys_config('u_recharge_give_ratio');
        if ($uRechargeGiveRatio > 0) {
            $giveAmount = bcmul($amount, $uRechargeGiveRatio / 100, 2);
            Assets::updateMainCoinAssetsBalance($assets->user_id, $giveAmount, 'u_recharge');
        }
        $recharge = [
            'user_id' => $assets->user_id,
            'trade_id' => $data->tradeId,
            'amount' => $amount,
            'address' => $data->address,
            'main_coin_type' => $data->mainCoinType,
            'tx_id' => $data->txId
        ];

        // USDT每一笔充值，限制提现时间
        $newCardWithdrawalInterval = get_sys_config('per_recharge_withdrawal_interval');
        $limitWithdrawTime = strtotime('+' . $newCardWithdrawalInterval . ' hour');
        (new \app\admin\model\User())->where(['id' => $assets->user_id])->update(['limit_withdraw_time' => $limitWithdrawTime]);

        return Recharge::create($recharge);
    }

    private function withdrawPassed($data)
    {
        $businessId = $data->businessId;
        Withdraw::where(['order_no' => $businessId, 'status' => 4])->update(['status' => 3, 'update_time' => time()]);
    }
}
