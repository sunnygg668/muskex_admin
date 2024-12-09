<?php

namespace app\api\controller;

use app\admin\model\ba\coin\Management;
use app\admin\model\ba\trade\ManagementOrder;
use app\admin\model\ba\user\Assets;
use app\admin\model\User;
use app\common\controller\Frontend;
use think\db\Query;
use think\facade\Db;
use think\facade\Queue;

class CoinManagement extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function index(): void
    {
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);
        $mList = Management::with([
                'settlementCoin' => function (Query $query) {$query->field('id, name');},
                'incomeCoin' => function (Query $query) {$query->field('id, name');}
            ])
            ->where(['status' => '1'])
            ->order('weigh desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]
            );
        foreach ($mList as $m) {
            $m['left_num'] = $m->issues_num - $m->sold_num;
        }
        $data = ['list' => $mList];
        $this->success('', $data);
    }

    public function detail(): void
    {
        $id = request()->param('id');
        $management = Management::with([
            'settlementCoin' => function (Query $query) {$query->field('id, name');},
            'incomeCoin' => function (Query $query) {$query->field('id, name');}
        ])->find($id);
        $management['left_num'] = $management->issues_num - $management->sold_num;
        $this->success('', $management);
    }

    public function buy(): void
    {
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $id = request()->param('id');
        $num = request()->param('num');
        $fundPassword = request()->param('fundPassword');
        if ($num <= 0) {
            $this->error('请输入正确的数量');
        }
        $user    = $this->auth->getUser();
        if (empty($user->is_certified) || empty($user->idcard) || $user->is_certified != 2) {
            $this->error('请先完成实名认证');
        }
        if (!$this->auth->checkFundPassword($fundPassword)) {
            $this->error('资金密码错误');
        }
        $management = Management::find($id);
        if ($management->status == '0') {
            $this->error('该理财产品已停售');
        }
        if ($num < $management->min_buy_num || $num > $management->max_buy_num) {
            $this->error('可申购数量范围为：' . $management->min_buy_num . ' - ' . $management->max_buy_num);
        }
        $leftNum = $management->issues_num - $management->sold_num;
        if ($num > $leftNum) {
            $this->error('可申购数量不足，目前可申购数：' . $leftNum);
        }
        Db::startTrans();
        try {
            $orderNo = 'TMO' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $closedDays = $management->closed_days;
            $incomeType = $management->income_type;
            $incomeRatio = $management->income_ratio;
            $totalPrice = bcmul($num, $management->price, 2);
            $unitTotalIncome = bcmul($totalPrice, $incomeRatio / 100, 2);
            $totalIncome = 0;
            if ($management->income_type == 'hour') {
                $totalIncome = bcmul($closedDays * 24, $unitTotalIncome, 2);
            } else if ($management->income_type == 'day') {
                $totalIncome = bcmul($closedDays, $unitTotalIncome, 2);
            } else if ($management->income_type == 'month') {
                $totalIncome = bcmul($closedDays / 30, $unitTotalIncome, 2);
            } else if ($management->income_type == 'year') {
                $totalIncome = bcmul($closedDays / 365, $unitTotalIncome, 2);
            }

            if($closedDays <7 ){//7天以下
                $rebateRatio = 0;
            }elseif($closedDays ==7 ){//7天
                $rebateRatio = get_sys_config('7_day_rebate_rate');
            }elseif($closedDays>7 && $closedDays<=30){//15天 30天
                $rebateRatio = get_sys_config('30_day_rebate_rate');
            }else{//30天以上
                $rebateRatio = get_sys_config('30_over_day_rebate_rate');
            }
            $rebate_income = bcmul($totalPrice/2, $rebateRatio/100, 2);// 50%发放除以2

            Assets::updateCoinAssetsBalance($userId, $management->settlement_coin_id, -$totalPrice, 'management_buy');
            $order = [
                'order_no' => $orderNo,
                'user_id' => $userId,
                'refereeid' => $user->refereeid,
                'team_leader_id' => $user->team_leader_id,
                'coin_management_id' => $id,
                'settlement_coin_id' => $management->settlement_coin_id,
                'income_coin_id' => $management->income_coin_id,
                'price' => $management->price,
                'buy_num' => $num,
                'total_price' => $totalPrice,
                'income_type' => $incomeType,
                'income_ratio' => $incomeRatio,
                'total_income' => $totalIncome,
                'rebate_income' => $rebate_income,
                'closed_days' => $closedDays,
                'expire_time' => strtotime('+' . $closedDays . ' days'),
            ];
            ManagementOrder::create($order);
            $management->setInc('sold_num', $num);
            if($user->refereeid && $rebate_income > 0){//返利给上级
                Queue::push('\app\custom\job\RewardQueue@managementBuy', ['user_id' => $user->refereeid, 'rebate_income' => $rebate_income], 'reward');
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('申购成功');
    }

    public function transferIn()
    {
        $userId = $this->auth->id;
        $amount = request()->param('amount');
        $minAmount = get_sys_config('management_min_amount');
        if ($amount < $minAmount) {
            $this->error('最少存入金额不能少于'.$minAmount);
        }
        $user    = $this->auth->getUser();
        if (empty($user->is_certified) || empty($user->idcard) || $user->is_certified != 2) {
            $this->error('请先完成实名认证');
        }
        $fundPassword = $this->request->param('fundPassword');
        if (!$this->auth->checkFundPassword($fundPassword)) {
            $this->error('资金密码错误');
        }
        Db::startTrans();
        try {
            Assets::updateMainCoinAssetsBalance($userId, -$amount, 'transfer_in_money');
            User::updateManagement($userId, $amount, 'transfer_in_money');
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('转入失败', null, $e->getCode());
        }
        $this->success('转入成功');
    }

    public function transferOut()
    {
        $userId = $this->auth->id;
        $amount = request()->param('amount');
        $fundPassword = $this->request->param('fundPassword');
        if (!$this->auth->checkFundPassword($fundPassword)) {
            $this->error('资金密码错误');
        }
        Db::startTrans();
        try {
            Assets::updateMainCoinAssetsBalance($userId, $amount, 'transfer_out_money');
            User::updateManagement($userId, -$amount, 'transfer_out_money');
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('转出成功');
    }
}
