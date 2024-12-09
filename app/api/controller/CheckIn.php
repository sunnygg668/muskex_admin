<?php

namespace app\api\controller;

use app\admin\model\ba\user\Assets;
use app\admin\model\ba\user\CheckIn as CheckInModel;
use app\common\controller\Frontend;
use think\facade\Db;

class CheckIn extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function checkRule()
    {
        $userId = $this->auth->id;
        $checkInPeriod = get_sys_config('check_in_period');
        $checkInGiveCoinNum = floatval(get_sys_config('check_in_give_coin_num'));
        $checkInEndGiveCoinNum = floatval(get_sys_config('check_in_end_give_coin_num'));
        $checkInRules = get_sys_config('check_in_rules');
        $dayInc = floatval(get_sys_config('day_inc'));
        $checkInToday = 0;
        $giveCoinNumArray = [];
        $i = 0;
        for (; $i < $checkInPeriod; $i++) {
            $day = date('Y-m-d', strtotime('-' . $i . ' day'));
            $checkIn = CheckInModel::where('user_id', $userId)->whereDay('create_time', $day)->find();
            if (!$checkIn && $i > 0) {
                break;
            }
            if ($checkIn) {
                $giveCoinNumArray[] = $checkIn->give_coin_num;
                if ($day == date('Y-m-d')) {
                    $checkInToday = 1;
                }
            }
        }
        if ($checkInToday == 0) {
            $i -= 1;
        }
        $checkNum = $i % $checkInPeriod;
        for ($i = $checkNum; $i < $checkInPeriod; $i++) {
            if ($i == 0) {
                $giveCoinNum = $checkInGiveCoinNum;
            } else if ($i == $checkInPeriod - 1) {
                $giveCoinNum = $checkInEndGiveCoinNum;
            } else {
                $giveCoinNum = $giveCoinNumArray[$i - 1] + $dayInc;
            }
            $giveCoinNumArray[] = $giveCoinNum;
        }
        $result = [
            'checkInPeriod' => $checkInPeriod,
            'checkInRules' => $checkInRules,
            'checkNum' => $checkNum,
            'giveCoinNumArray' => $giveCoinNumArray,
            'checkInToday' => $checkInToday
        ];
        $this->success('', $result);
    }

    public function checkIn()
    {
        $userId = $this->auth->id;
        $checkNum = $this->request->param('checkNum');
        $checkInPeriod = get_sys_config('check_in_period');
        $checkInGiveCoin = get_sys_config('check_in_give_coin');
        $checkInGiveCoinNum = get_sys_config('check_in_give_coin_num');
        $dayInc = get_sys_config('day_inc');
        $checkInEndGiveCoin = get_sys_config('check_in_end_give_coin');
        $checkInEndGiveCoinNum = get_sys_config('check_in_end_give_coin_num');
        Db::startTrans();
        try {
            $giveCoinNum = $checkInGiveCoinNum;
            $giveCoinType = $checkInGiveCoin;
            if ($checkNum == $checkInPeriod) {
                $giveCoinNum = $checkInEndGiveCoinNum;
                $giveCoinType = $checkInEndGiveCoin;
            } else if ($checkNum > 1) {
                $giveCoinNum = $checkInGiveCoinNum + ($checkNum - 1) * $dayInc;
            }
            $checkIn = [
                'user_id' => $userId,
                'give_coin_type' => $giveCoinType,
                'give_coin_num'  => $giveCoinNum
            ];
            CheckInModel::create($checkIn);
            Assets::updateMainCoinAssetsBalance($userId, $giveCoinNum, 'check_in_reward');
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('签到成功');
    }
}