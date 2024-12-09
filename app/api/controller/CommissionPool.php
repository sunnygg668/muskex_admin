<?php

namespace app\api\controller;

use app\admin\model\ba\coin\Coin;
use app\admin\model\ba\coin\Recharge;
use app\admin\model\ba\financial\Recharge as FinancialRecharge;
use app\admin\model\ba\user\Assets;
use app\admin\model\ba\user\CommissionChange;
use app\admin\model\ba\user\Level;
use app\admin\model\ba\user\TeamLevel;
use app\admin\model\User;
use app\common\controller\Frontend;
use think\facade\Db;

class CommissionPool extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }
    public function index()
    {
        $user = $this->auth->getUser()->visible(['id', 'commission_pool', 'level']);
        $level = Level::where(['level' => $user->level])->find();
        $level = $level->visible(['id', 'name', 'logo_image', 'bonus']);
        $commissionPoolTip = get_sys_config('commission_pool_tip');
        $totalAmount = CommissionChange::where(['user_id' => $user->id, 'type' => 'commission_pool_collect'])->sum('amount');
        $totalAmount = abs($totalAmount);
        $teamNums = $user->team_nums;
        $teamNumsGrade = 0;
        $collectNum = 0;
        $canCollect = false;
        $collectGradeArray = get_sys_config('collect_grade');
        array_multisort(array_column($collectGradeArray,'key'),SORT_ASC, $collectGradeArray);
        foreach ($collectGradeArray as $key => $collectGrade) {
            $canCollect = CommissionChange::where(['user_id' => $user->id, 'type' => 'commission_pool_collect', 'remark' => $key])->count() == 0;
            if ($teamNums < $collectGrade['key'] || $canCollect) {
                $teamNumsGrade = $collectGrade['key'];
                $collectNum = $collectGrade['value'];
                break;
            }
        }
        $canCollect = $canCollect && ($teamNums >= $teamNumsGrade) && ($user->commission_pool >= $collectNum);
        $result = [
            'user' => $user,
            'level' => $level,
            'commissionPoolTip' => $commissionPoolTip,
            'totalAmount' => $totalAmount,
            'teamNums' => $teamNums,
            'teamNumsGrade' => $teamNumsGrade,
            'collectNum' => $collectNum,
            'canCollect' => $canCollect
        ];
        $this->success('', $result);
    }

    public function collect()
    {
        $user = $this->auth->getUser();
        if (empty($user->is_certified) || empty($user->idcard) || $user->is_certified != 2) {
            $this->error('请先完成实名认证');
        }
        $commissionPool = $user->commission_pool;
        $teamLevel = get_sys_config('team_level');
        $teamLevelNums = get_sys_config('team_level_nums');
        $tl = TeamLevel::where(['user_id' => $user->id, 'user_level' => $teamLevel])->find();
        if ($tl->team_nums < $teamLevelNums) {
            $level = Level::where(['level' => $teamLevel])->find();
            $this->error('团队 ' . $level->name . ' 等级的有效人数不满 ' . $teamLevelNums . ' 人');
        }
        $teamTotalRecharge = get_sys_config('team_total_recharge');
        $childIds = Db::query('select queryChildrenUsers(:refereeid) as childIds', ['refereeid' => $user->id])[0]['childIds'];
        $rechargeCoin = Recharge::where('user_id', 'in', $childIds)->sum('amount');
        $rechargeMoney = FinancialRecharge::where('user_id', 'in', $childIds)->where('status', 1)->sum('main_coin_num');
        $totalRecharge = bcadd($rechargeCoin, $rechargeMoney, 2);
        if ($totalRecharge < $teamTotalRecharge) {
            $mainCoin = Coin::mainCoin();
            $this->error('团队总充值不满 ' . $teamTotalRecharge . ' ' . $mainCoin->name);
        }
        $collectNum = 0;
        $gradeIndex = 0;
        $teamNums = $user->team_nums;
        $canCollect = false;
        $collectGradeArray = get_sys_config('collect_grade');
        array_multisort(array_column($collectGradeArray,'key'),SORT_ASC, $collectGradeArray);
        foreach ($collectGradeArray as $key => $collectGrade) {
            $canCollect = CommissionChange::where(['user_id' => $user->id, 'type' => 'commission_pool_collect', 'remark' => $key])->count() == 0;
            if ($teamNums <= $collectGrade['key'] || $canCollect) {
                $collectNum = $collectGrade['value'];
                $gradeIndex = $key;
                break;
            }
        }
        if ($collectNum == 0 || !$canCollect) {
            $this->error('暂不满足领取条件');
        }
        if ($commissionPool < $collectNum) {
            $this->error('佣金池余额不足');
        }
        Db::startTrans();
        try {
            Assets::updateMainCoinAssetsBalance($user->id, $collectNum, 'commission_pool_collect');
            User::updateCommission($user->id, -$collectNum, 'commission_pool_collect', null, $gradeIndex);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('领取成功');
    }

    public function changeList()
    {
        $userId = $this->auth->id;
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);
        $list = CommissionChange::where(['user_id' => $userId])
            ->with(['coinChangeTypes'])
            ->order('id desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]);
        $list->visible(['coinChangeTypes' => ['id', 'type_name', 'color', 'logo']]);
        $mainCoin = Coin::mainCoin();
        foreach ($list as $item) {
            $item['coin'] = ['name' => $mainCoin->name, 'logo_image' => $mainCoin->logo_image];
        }
        $result = ['list' => $list];
        $this->success('', $result);
    }
}
