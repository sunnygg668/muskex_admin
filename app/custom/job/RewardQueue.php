<?php

namespace app\custom\job;

use app\admin\model\ba\financial\Recharge;
use app\admin\model\ba\user\Assets;
use app\admin\model\ba\user\CoinChange;
use app\admin\model\ba\user\Level;
use app\admin\model\User;
use app\common\model\BussinessLog;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use think\queue\Job;

class RewardQueue
{
    public function userRegister(Job $job, $data)
    {
        $userId = $data['user_id'];
        $giveCoinType = get_sys_config('give_coin_type');
        $giveCoinNum = get_sys_config('give_coin_num');
        if ($giveCoinType && $giveCoinNum > 0) {
            Assets::updateCoinAssetsBalance($userId, $giveCoinType, $giveCoinNum, 'register_reward');
        }
        $job->delete();
    }

    public function userActivation(Job $job, $data)
    {
        $userId = $data['user_id'];
        Db::startTrans();
        try {
            $user = User::find($userId);
            $oldIsActivation = $user->is_activation;
            if ($user->refereeid) {
                $refereeUser = User::find($user->refereeid);
                if ($refereeUser) {
                    $inviteOneGiveCoinType = get_sys_config('invite_one_give_coin_type');
                    $inviteOneGiveCoinNum = get_sys_config('invite_one_give_coin_num');
                    $coinChange = CoinChange::getRecordFrom($refereeUser->id, $userId, 'invite_register_reward');
                    if (!$coinChange && $inviteOneGiveCoinType && $inviteOneGiveCoinNum > 0) {
                        Assets::updateCoinAssetsBalance($refereeUser->id, $inviteOneGiveCoinType, $inviteOneGiveCoinNum, 'invite_register_reward', $userId);
                    }
                }

            }
            Db::commit();
            $job->delete();
            if ($oldIsActivation == 0) {
                $user->save(['is_activation' => 1, 'activation_time' => time()]);
                Queue::push('\app\custom\job\UserQueue@updateTeamLevel', ['user_id' => $userId, 'num' => 1], 'user');
            }
        } catch (\Exception $e) {
            Db::rollback();
            BussinessLog::record($e->getMessage());
        }
    }

    public function userFirstRecharge(Job $job, $data)
    {
        $userId = $data['user_id'];
        $user = User::find($userId);
        if ($user->refereeid) {
            $refereeUser = User::find($user->refereeid);
            if ($refereeUser) {
                $inviteRechargeGiveCoinType = get_sys_config('invite_recharge_give_coin_type');
                $inviteRechargeGiveCoinNum = get_sys_config('invite_recharge_give_coin_num');
                $coinChange = CoinChange::getRecordFrom($refereeUser->id, $userId, 'invite_first_recharge');
                if (!$coinChange && $inviteRechargeGiveCoinType && $inviteRechargeGiveCoinNum > 0) {
                    Assets::updateCoinAssetsBalance($refereeUser->id, $inviteRechargeGiveCoinType, $inviteRechargeGiveCoinNum, 'invite_first_recharge', $userId);
                }
            }
        }
        $job->delete();
    }

    public function contractBuy(Job $job, $data)
    {
        $userId = $data['user_id'];
        $margin = $data['margin'];
        $user = User::find($userId);
        $levelArray = [];
        $levelList = Level::where('is_open', 1)->select();
        if (empty($levelList)) {
            $job->delete();
            return;
        }
        foreach ($levelList as $level) {
            $levelArray[$level->level] = $level;
        }
        Db::startTrans();
        try {
            $maxRebateLayers = Level::where('is_open', 1)->max('rebate_layers');
            for ($i = 1; $i <= $maxRebateLayers; $i++) {
                if (!$user->refereeid) {
                    break;
                }
                $refereeUser = User::find($user->refereeid);
                if (!$refereeUser) {
                    break;
                }
                $level = $levelArray[$refereeUser->level];
                if ($level->rebate_layers < $i) {
                    continue;
                }
                $ratioKey = 'layer_' . $i . '_ratio';
                if (empty($level[$ratioKey])) {
                    continue;
                }
                $ratio = $level[$ratioKey];
                $commission = bcmul($margin, $ratio / 100, 2);
                User::updateCommission($refereeUser->id, $commission, 'margin_reward', $userId);
                $user = $refereeUser;
            }
            Db::commit();
            $job->delete();
        } catch (\Exception $e) {
            Db::rollback();
            BussinessLog::record($e->getMessage());
        }
    }


    public function managementBuy(Job $job, $data)
    {
        $userId = $data['user_id'];
        $rebate_income = $data['rebate_income'];

        Db::startTrans();
        try {
            $commission = $rebate_income;
            User::updateCommission($userId, $commission, 'rebate_income', $userId);
            Db::commit();
            $job->delete();
        } catch (\Exception $e) {
            Db::rollback();
            BussinessLog::record($e->getMessage());
        }
    }

    public function minersLease(Job $job, $data)
    {
        $userId = $data['user_id'];
        $totalPrice = $data['totalPrice'];
        $user = User::find($userId);
        $giveMinersRewardLevel = get_sys_config('give_miners_reward_level');
        Db::startTrans();
        try {
            for ($i = 1; $i <= 2; $i++) {
                if (!$user->refereeid) {
                    break;
                }
                $refereeUser = User::find($user->refereeid);
                if (!$refereeUser) {
                    break;
                }
                $ratioKey = 'layer_' . $i . '_miners_reward_ratio';
                $ratio = get_sys_config($ratioKey);
                if ($ratio) {
                    $amount = bcmul($totalPrice, $ratio / 100, 2);
                    Assets::updateMainCoinAssetsBalance($refereeUser->id, $amount, 'miners_reward', $userId);
                }
                $user = $refereeUser;
            }
            Db::commit();
            $job->delete();
        } catch (\Exception $e) {
            Db::rollback();
            BussinessLog::record($e->getMessage());
        }
    }

    public function giveLotteryCount(Job $job, $data)
    {
        $userId = $data['user_id'];
        $coinAmount = $data['coinAmount'];
        $mainCoinPrice = Assets::mainCoinPrice();
        $amount = bcmul($coinAmount, $mainCoinPrice, 2);
        $rechargeGiveCount = get_sys_config('recharge_give_count');
        if ($rechargeGiveCount) {
            array_multisort(array_column($rechargeGiveCount, 'key'), SORT_DESC, $rechargeGiveCount);
            foreach ($rechargeGiveCount as $giveCount) {
                if ($amount >= $giveCount['key'] && $giveCount['value'] > 0) {
                    User::where('id', $userId)->inc('lottery_count', $giveCount['value']);
                    break;
                }
            }
        }
        $job->delete();
    }
}