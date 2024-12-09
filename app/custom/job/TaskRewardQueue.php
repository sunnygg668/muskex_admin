<?php

namespace app\custom\job;

use app\admin\model\ba\financial\Recharge;
use app\admin\model\ba\trade\ContractOrder;
use app\admin\model\ba\user\Assets;
use app\admin\model\ba\user\CoinChange;
use app\admin\model\User;
use think\queue\Job;

class TaskRewardQueue
{

    public function authGive(Job $job, $data): void
    {
        $userId = $data['user_id'];
        $taskGiveCoinType = get_sys_config('task_give_coin_type');
        $authGiveCoinNum = get_sys_config('auth_give_coin_num');
        if ($authGiveCoinNum <= 0) {
            return;
        }
        $coinChange = CoinChange::where(['user_id' => $userId, 'type' => 'auth_give'])->find();
        if (!$coinChange) {
            Assets::updateCoinAssetsBalance($userId, $taskGiveCoinType, $authGiveCoinNum, 'auth_give');
        }
        $job->delete();
    }

    public function firstRechargeReachedGive(Job $job, $data): void
    {
        $userId = $data['user_id'];
        $amount = $data['amount'];
        $rechargeCount = CoinChange::where('user_id', $userId)->where('type', 'in', ['financial_recharge'])->count();
        $rechargeGiveCount = CoinChange::where(['user_id' => $userId, 'type' => 'first_recharge_reached_give'])->count();
        if ($rechargeCount > 1 || $rechargeGiveCount > 0) {
            $job->delete();
            return;
        }
        $taskGiveCoinType = get_sys_config('task_give_coin_type');
        $giveArray = get_sys_config('first_recharge_reached_give');
        array_multisort(array_column($giveArray,'key'),SORT_DESC, $giveArray);
        foreach ($giveArray as $give) {
            if ($amount >= $give['key'] && $give['value'] > 0) {
                Assets::updateCoinAssetsBalance($userId, $taskGiveCoinType, $give['value'], 'first_recharge_reached_give', null, $give['key']);
                break;
            }
        }
        $job->delete();
    }

    public function todayRechargeReachedGive(Job $job, $data): void
    {
        $userId = $data['user_id'];
        $taskGiveCoinType = get_sys_config('task_give_coin_type');
        $giveArray = get_sys_config('today_recharge_reached_give');
        array_multisort(array_column($giveArray,'key'),SORT_DESC, $giveArray);
        $todayAmount = Recharge::where('user_id', $userId)->where('status', 'in', ['1', '3'])->whereDay('create_time')->sum('amount') ?: 0;
        foreach ($giveArray as $give) {
            if ($todayAmount >= $give['key'] && $give['value'] > 0) {
                $coinChange = CoinChange::where(['user_id' => $userId, 'type' => 'today_recharge_reached_give', 'remark' => $give['key']])->whereDay('create_time')->find();
                if (!$coinChange) {
                    Assets::updateCoinAssetsBalance($userId, $taskGiveCoinType, $give['value'], 'today_recharge_reached_give', null, $give['key']);
                }
            }
        }
        $job->delete();
    }

    public function inviteNumReachedGive(Job $job, $data): void
    {
        $userId = $data['user_id'];
        $user = User::find($userId);
        if (!$user) {
            $job->delete();
            return;
        }
        $taskGiveCoinType = get_sys_config('task_give_coin_type');
        $giveArray = get_sys_config('invite_num_reached_give');
        array_multisort(array_column($giveArray,'key'),SORT_DESC, $giveArray);
        $refereeNums = $user->referee_nums;
        foreach ($giveArray as $give) {
            if ($refereeNums >= $give['key'] && $give['value'] > 0) {
                $coinChange = CoinChange::where(['user_id' => $userId, 'type' => 'invite_num_reached_give', 'remark' => $give['key']])->find();
                if (!$coinChange) {
                    Assets::updateCoinAssetsBalance($userId, $taskGiveCoinType, $give['value'], 'invite_num_reached_give', null, $give['key']);
                }
                break;
            }
        }
        $job->delete();
    }

    public function todayInviteReachedGive(Job $job, $data): void
    {
        $userId = $data['user_id'];
        $taskGiveCoinType = get_sys_config('task_give_coin_type');
        $giveArray = get_sys_config('today_invite_reached_give');
        array_multisort(array_column($giveArray,'key'),SORT_DESC, $giveArray);
        $todayInviteNums = User::where(['refereeid' => $userId, 'is_activation' => 1])->whereDay('create_time')->count();
        foreach ($giveArray as $give) {
            if ($todayInviteNums >= $give['key'] && $give['value'] > 0) {
                $coinChange = CoinChange::where(['user_id' => $userId, 'type' => 'today_invite_reached_give', 'remark' => $give['key']])->whereDay('create_time')->find();
                if (!$coinChange) {
                    Assets::updateCoinAssetsBalance($userId, $taskGiveCoinType, $give['value'], 'today_invite_reached_give', null, $give['key']);
                }
                break;
            }
        }
        $job->delete();
    }

    public function weekInviteReachedGive(Job $job, $data): void
    {
        $userId = $data['user_id'];
        $taskGiveCoinType = get_sys_config('task_give_coin_type');
        $giveArray = get_sys_config('week_invite_reached_give');
        array_multisort(array_column($giveArray,'key'),SORT_DESC, $giveArray);
        $weekInviteNums = User::where(['refereeid' => $userId, 'is_activation' => 1])->whereWeek('create_time')->count();
        foreach ($giveArray as $give) {
            if ($weekInviteNums >= $give['key'] && $give['value'] > 0) {
                $coinChange = CoinChange::where(['user_id' => $userId, 'type' => 'week_invite_reached_give', 'remark' => $give['key']])->whereWeek('create_time')->find();
                if (!$coinChange) {
                    Assets::updateCoinAssetsBalance($userId, $taskGiveCoinType, $give['value'], 'week_invite_reached_give', null, $give['key']);
                }
                break;
            }
        }
        $job->delete();
    }

    public function monthInviteReachedGive(Job $job, $data): void
    {
        $userId = $data['user_id'];
        $taskGiveCoinType = get_sys_config('task_give_coin_type');
        $giveArray = get_sys_config('month_invite_reached_give');
        array_multisort(array_column($giveArray,'key'),SORT_DESC, $giveArray);
        $monthInviteNums = User::where(['refereeid' => $userId, 'is_activation' => 1])->whereMonth('create_time')->count();
        foreach ($giveArray as $give) {
            if ($monthInviteNums >= $give['key'] && $give['value'] > 0) {
                $coinChange = CoinChange::where(['user_id' => $userId, 'type' => 'month_invite_reached_give', 'remark' => $give['key']])->whereMonth('create_time')->find();
                if (!$coinChange) {
                    Assets::updateCoinAssetsBalance($userId, $taskGiveCoinType, $give['value'], 'month_invite_reached_give', null, $give['key']);
                }
                break;
            }
        }
        $job->delete();
    }

    public function teamNumReachedGive(Job $job, $data): void
    {
        $userId = $data['user_id'];
        $user = User::find($userId);
        if (!$user) {
            $job->delete();
            return;
        }
        $taskGiveCoinType = get_sys_config('task_give_coin_type');
        $giveArray = get_sys_config('team_num_reached_give');
        array_multisort(array_column($giveArray,'key'),SORT_DESC, $giveArray);
        $teamNums = $user->team_nums;
        foreach ($giveArray as $give) {
            if ($teamNums >= $give['key'] && $give['value'] > 0) {
                $coinChange = CoinChange::where(['user_id' => $userId, 'type' => 'team_num_reached_give', 'remark' => $give['key']])->find();
                if (!$coinChange) {
                    Assets::updateCoinAssetsBalance($userId, $taskGiveCoinType, $give['value'], 'team_num_reached_give', null, $give['key']);
                }
                break;
            }
        }
        $job->delete();
    }

    public function todayContractNumReached(Job $job, $data): void
    {
        $userId = $data['user_id'];
        $taskGiveCoinType = get_sys_config('task_give_coin_type');
        $giveArray = get_sys_config('today_contract_num_reached');
        array_multisort(array_column($giveArray,'key'),SORT_DESC, $giveArray);
        $todayContractNum = ContractOrder::where(['user_id' => $userId])->whereDay('buy_time')->count();
        foreach ($giveArray as $give) {
            if ($todayContractNum >= $give['key'] && $give['value'] > 0) {
                $coinChange = CoinChange::where(['user_id' => $userId, 'type' => 'today_contract_num_reached', 'remark' => $give['key']])->whereDay('create_time')->find();
                if (!$coinChange) {
                    Assets::updateCoinAssetsBalance($userId, $taskGiveCoinType, $give['value'], 'today_contract_num_reached', null, $give['key']);
                }
                break;
            }
        }
        $job->delete();
    }

    public function todayContractAmountReached(Job $job, $data): void
    {
        $userId = $data['user_id'];
        $taskGiveCoinType = get_sys_config('task_give_coin_type');
        $giveArray = get_sys_config('today_contract_amount_reached');
        array_multisort(array_column($giveArray,'key'),SORT_DESC, $giveArray);
        $todayInvestedCoinNum = ContractOrder::where(['user_id' => $userId])->whereDay('buy_time')->sum('invested_coin_num');
        foreach ($giveArray as $give) {
            if ($todayInvestedCoinNum >= $give['key'] && $give['value'] > 0) {
                $coinChange = CoinChange::where(['user_id' => $userId, 'type' => 'today_contract_amount_reached', 'remark' => $give['key']])->whereDay('create_time')->find();
                if (!$coinChange) {
                    Assets::updateCoinAssetsBalance($userId, $taskGiveCoinType, $give['value'], 'today_contract_amount_reached', null, $give['key']);
                }
                break;
            }
        }
        $job->delete();
    }

    public function monthContractAmountReached(Job $job, $data): void
    {
        $userId = $data['user_id'];
        $taskGiveCoinType = get_sys_config('task_give_coin_type');
        $giveArray = get_sys_config('month_contract_amount_reached');
        array_multisort(array_column($giveArray,'key'),SORT_DESC, $giveArray);
        $monthInvestedCoinNum = ContractOrder::where(['user_id' => $userId])->whereMonth('buy_time')->sum('invested_coin_num');
        foreach ($giveArray as $give) {
            if ($monthInvestedCoinNum >= $give['key'] && $give['value'] > 0) {
                $coinChange = CoinChange::where(['user_id' => $userId, 'type' => 'month_contract_amount_reached', 'remark' => $give['key']])->whereMonth('create_time')->find();
                if (!$coinChange) {
                    Assets::updateCoinAssetsBalance($userId, $taskGiveCoinType, $give['value'], 'month_contract_amount_reached', null, $give['key']);
                }
                break;
            }
        }
        $job->delete();
    }

    public function firstContractAmountReached(Job $job, $data): void
    {
        $userId = $data['user_id'];
        $coinChange = CoinChange::where(['user_id' => $userId, 'type' => 'first_contract_amount_reached'])->find();
        if ($coinChange) {
            $job->delete();
            return;
        }
        $taskGiveCoinType = get_sys_config('task_give_coin_type');
        $giveArray = get_sys_config('first_contract_amount_reached');
        array_multisort(array_column($giveArray,'key'),SORT_DESC, $giveArray);
        $totalInvestedCoinNum = ContractOrder::where(['user_id' => $userId])->sum('invested_coin_num');
        foreach ($giveArray as $give) {
            if ($totalInvestedCoinNum >= $give['key'] && $give['value'] > 0) {
                Assets::updateCoinAssetsBalance($userId, $taskGiveCoinType, $give['value'], 'first_contract_amount_reached', null, $give['key']);
                break;
            }
        }
        $job->delete();
    }

}