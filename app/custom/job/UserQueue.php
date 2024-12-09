<?php

namespace app\custom\job;

use app\admin\model\ba\coin\Coin;
use app\admin\model\ba\user\Assets;
use app\admin\model\ba\user\Level;
use app\admin\model\ba\user\TeamLevel;
use app\admin\model\User;
use app\common\model\BussinessLog;
use app\custom\library\UDun;
use think\Exception;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use think\queue\Job;

class UserQueue
{
    public function createAssets(Job $job, $data)
    {
        $userId = isset($data['user_id']) ? $data['user_id'] : 0;
        $coinId = isset($data['coin_id']) ? $data['coin_id'] : 0;
        if ($userId) {
            $coinIds = Coin::column('id');
            foreach ($coinIds as $cid) {
                try {
                    $assets = [
                        'user_id' => $userId,
                        'coin_id' => $cid,
                    ];
                    Assets::create($assets);
                } catch (\Exception $e) {
                    BussinessLog::record($e->getMessage());
                }
            }
        }
        if ($coinId) {
            $assetsArray = [];
            $userIds = User::column('id');
            foreach ($userIds as $uid) {
                $assetsArray[] = [
                    'user_id' => $uid,
                    'coin_id' => $coinId,
                    'create_time' => time(),
                    'update_time' => time()
                ];
            }
            Db::name('user_assets')->insertAll($assetsArray);
        }
        $job->delete();
    }

    public function createMainCoinAddress(Job $job, $data)
    {
        try {
            $userId = $data['user_id'];

            $assets = Assets::mainCoinAssets($userId);
            if (!$assets) {
                return;
            }
            $udunMainCoinCode = get_sign_sys_config('udun_main_coin_code')['udun_main_coin_code']['value'] ?? '';
            $result =  UDun::uDunDispatch()->createAddress($udunMainCoinCode);
            if ($result['code'] == 200) {
                $address = $result['data']['address'];
                $assets->save(['address' => $address]);
                $job->delete();
            } else {
                throw new Exception(json_encode($result, true));
            }
        } catch (\Exception $e) {
            BussinessLog::record('创建USDT地址失败：' . $e->getMessage());
        }
    }

    public function createTeamLevel(Job $job, $data)
    {
        $userId = isset($data['user_id']) ? $data['user_id'] : 0;
        $level = isset($data['level']) ? $data['level'] : 0;
        if ($userId) {
            $teamLevelArray = [];
            $levels = Level::column('level');
            foreach ($levels as $lvl) {
                $teamLevelArray[] = [
                    'user_id' => $userId,
                    'user_level' => $lvl,
                    'create_time' => time(),
                    'update_time' => time()
                ];
            }
            Db::name('user_team_level')->insertAll($teamLevelArray);
        }
        if ($level) {
            $teamLevelArray = [];
            $userIds = User::column('id');
            foreach ($userIds as $uid) {
                $teamLevelArray[] = [
                    'user_id' => $uid,
                    'user_level' => $level,
                    'create_time' => time(),
                    'update_time' => time()
                ];
            }
            Db::name('user_team_level')->insertAll($teamLevelArray);
        }
        $job->delete();
    }

    public function updateTeamLevel(Job $job, $data)
    {
        $userId = $data['user_id'];
        $num = $data['num'];
        $user = User::find($userId);
        $parentIds = Db::query('select queryParentUsers(:userId) as parentIds', ['userId' => $userId])[0]['parentIds'];
        $parentIds = explode(',', $parentIds);
        array_shift($parentIds);
        Db::startTrans();
        try {
            User::where('id', 'in', $parentIds)->setInc('team_nums', $num);
            User::where('id', $user->refereeid)->setInc('referee_nums', $num);
            TeamLevel::where('user_id', 'in', $parentIds)->where('user_level', $user->level)->setInc('team_nums', $num);
            TeamLevel::where(['user_id' => $user->refereeid, 'user_level' => $user->level])->setInc('referee_nums', $num);
            Db::commit();
            $job->delete();
            Queue::push('\app\custom\job\TaskRewardQueue@inviteNumReachedGive', ['user_id' => $user->refereeid], 'task_reward');
            Queue::push('\app\custom\job\TaskRewardQueue@todayInviteReachedGive', ['user_id' => $user->refereeid], 'task_reward');
            Queue::push('\app\custom\job\TaskRewardQueue@weekInviteReachedGive', ['user_id' => $user->refereeid], 'task_reward');
            Queue::push('\app\custom\job\TaskRewardQueue@monthInviteReachedGive', ['user_id' => $user->refereeid], 'task_reward');
            Queue::push('\app\custom\job\TaskRewardQueue@teamNumReachedGive', ['user_id' => $user->refereeid], 'task_reward');
        } catch (\Exception $e) {
            Db::rollback();
            BussinessLog::record($e->getMessage());
        }
    }

    public function updateTeamLevelOnly(Job $job, $data)
    {
        $userId = $data['user_id'];
        $oldLevel = $data['old_level'];
        $user = User::find($userId);
        $parentIds = Db::query('select queryParentUsers(:userId) as parentIds', ['userId' => $userId])[0]['parentIds'];
        $parentIds = explode(',', $parentIds);
        array_shift($parentIds);
        Db::startTrans();
        try {
            TeamLevel::where('user_id', 'in', $parentIds)->where('user_level', $user->level)->setInc('team_nums');
            TeamLevel::where(['user_id' => $user->refereeid, 'user_level' => $user->level])->setInc('referee_nums');
            TeamLevel::where('user_id', 'in', $parentIds)->where('user_level', $oldLevel)->setDec('team_nums');
            TeamLevel::where(['user_id' => $user->refereeid, 'user_level' => $oldLevel])->setDec('referee_nums');
            Db::commit();
            $job->delete();
        } catch (\Exception $e) {
            Db::rollback();
            BussinessLog::record($e->getMessage());
        }
    }

}
