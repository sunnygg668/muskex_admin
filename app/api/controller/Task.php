<?php

namespace app\api\controller;

use app\admin\model\ba\financial\Recharge as FinancialRecharge;
use app\admin\model\ba\miners\Order;
use app\admin\model\ba\trade\ContractOrder;
use app\admin\model\ba\trade\ManagementOrder;
use app\admin\model\ba\user\Assets;
use app\admin\model\ba\user\Level;
use app\admin\model\ba\user\ManagementChange;
use app\admin\model\User;
use app\common\controller\Frontend;
use app\common\library\RedisKey;
use app\custom\library\RedisUtil;
use ba\Exception;
use think\App;
use think\facade\Db;
use think\facade\Queue;
use app\admin\model\ba\user\CoinChange;
use think\facade\Request;

class Task extends Frontend
{

    protected array $noNeedLogin = ['*'];

    protected string $locks_key = '';

    public function __construct(App $app)
    {
        parent::__construct($app);
        //print_r($this->app->request->server()['ARGV'][2]);exit;

        $this->locks_key = RedisKey::TASK.Request::server()['ARGV'][2];
        $is_lock = RedisUtil::set($this->locks_key, 1,'EX',300,'NX');
        if(!$is_lock){//存在锁
            die($this->locks_key." => Task is already running on another server.\n");
        }
    }

    public function __destruct()
    {
        RedisUtil::del($this->locks_key);
    }


    /**
     * 定期理财结束，收益发放和本金返还，每小时执行一次
     * @return void
     */
    public function managementOrderIncome()
    {
        Db::startTrans();
        try {
            $orderList = ManagementOrder::where(['status' => '1'])->whereTime('expire_time', '<=', time())->order('id asc')->select();
            foreach ($orderList as $order) {
                $totalPrice = $order->total_price;
                $totalIncome = $order->total_income;
                Assets::updateCoinAssetsBalance($order->user_id, $order->income_coin_id, $totalIncome, 'management_income');
                Assets::updateCoinAssetsBalance($order->user_id, $order->settlement_coin_id, $totalPrice, 'management_total_price_return');
                $order->paid_income += $totalIncome;
                $order->status = '2';
                $order->save();
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('理财收益已分发');
    }

    /**
     * 矿机到期发放本金和收益，每小时执行一次
     * @return void
     */
    public function minerOrderIncome()
    {
        Db::startTrans();
        try {
            $orderList = Order::where(['status' => '1'])->whereTime('expire_time', '<=', time())->order('id asc')->select();
            foreach ($orderList as $order) {
                $realPay = $order->real_pay;
                $estimatedIncome = $order->estimated_income;
                Assets::updateCoinAssetsBalance($order->user_id, $order->produce_coin_id, $estimatedIncome, 'miners_income');
                Assets::updateCoinAssetsBalance($order->user_id, $order->settlement_coin_id, $realPay, 'miners_real_pay_return');
                $order->gained_income += $estimatedIncome;
                $order->pending_income -= $estimatedIncome;
                $order->status = '2';
                $order->save();
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('矿机已产出');
    }


    public function managementWalletIncome()
    {
        exit($this->success('理财钱包收益分发改为按小时结算'));

        $moneyHourIncomeRatio = get_sys_config('money_hour_income_ratio');
        $moneyDayIncomeRatio = bcmul($moneyHourIncomeRatio / 100, 24, 4);
        if ($moneyDayIncomeRatio <= 0) {
            $this->error('理财收益比例为 0，无需发放');
        }
        Db::startTrans();
        try {
            $exists = ManagementChange::where('type', 'management_income')->whereDay('create_time')->find();
            if ($exists) {
                $this->error('当天的理财收益已分发过，请勿重复操作');
            }
            $userList = User::where('status', 1)->where('money', '>', 0)->select();
            foreach ($userList as $user) {
                $income = bcmul($user->money, $moneyDayIncomeRatio, 2);
                User::updateManagement($user->id, $income, 'management_income');
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('理财钱包收益分发成功');
    }

    /**
     * 理财钱包收益脚本，每小时执行一次
     * @return void
     * @throws \Throwable
     */
    public function managementWalletIncomeHours()
    {
        $moneyHourIncomeRatio = get_sys_config('money_hour_income_ratio');
        $moneyDayIncomeRatio = bcmul($moneyHourIncomeRatio / 100, 1, 4);
        if ($moneyDayIncomeRatio <= 0) {
            $this->error('理财收益比例为 0，无需发放');
        }
        $hourTime = date('Y-m-d H');
        Db::startTrans();
        try {

            $userList = User::where('status', 1)->where('money', '>', 0)->select();
            foreach ($userList as $user) {

                $first_transfer_in_where =[
                    'user_id' => $user->id,
                    'type' => 'transfer_in_money',
                ];
                $first_transfer_in = ManagementChange::where($first_transfer_in_where)->order('id asc')->find();
                if ($first_transfer_in && (time() - $first_transfer_in->create_time < 3600*8 )  ) {
                    echo $user->id."首次存入的每笔的收益，从8小时后才开始计算收益\r\n";
                    continue;
                }

                $exist_where =[
                    'user_id' => $user->id,
                    'type' => 'money_income',
                    'remark'=>$hourTime
                ];
                $exists = ManagementChange::where($exist_where)->find();
                if ($exists) {
                    //当前小时的理财收益已分发过，请勿重复操作
                    echo $user->id."当前小时的理财收益已分发过，请勿重复操作\r\n";
                    continue;
                }

                $income = bcmul($user->money, $moneyDayIncomeRatio, 2);
                if ($income <= 0) {
                    echo $user->id."理财收益为 0，无需发放\r\n";
                    continue;
                }

                User::updateManagement($user->id, $income, 'money_income',null,$hourTime);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success($hourTime.'理财钱包收益分发成功');
    }

    /**
     * 定期理财返点，返还给上级，每天执行一次
     * @return void
     */
    public function managementOrderRebateIncomeDay()
    {
        $dayTime = date('Y-m-d', time());
        Db::startTrans();
        try {
            $orderList = ManagementOrder::where(['status' => '1'])->where('rebate_income', '>', 0)->whereTime('expire_time', '>', time())->order('id asc')->select();
            foreach ($orderList as $order) {
                $user = User::where('id', $order->user_id)->find();
                if(!$user->refereeid){//发放给上级
                    continue;
                }
                $exist_where =[
                    'user_id' => $user->refereeid,
                    'type' => 'rebate_income',
                    'remark'=>$dayTime.'_'.$order->id
                ];
                $exists = ManagementChange::where($exist_where)->find();
                if ($exists) {
                    //当天的理财收益已分发过，请勿重复操作
                    echo $user->refereeid."的订单".$order->id."当天的理财收益已分发过，请勿重复操作\r\n";
                    continue;
                }

                $income = bcdiv($order->rebate_income, $order->closed_days, 2);
                if ($income <= 0) {
                    echo $user->id."理财返利收益为 0，无需发放\r\n";
                    continue;
                }
                User::updateManagement($user->refereeid, $income, 'rebate_income',$order->user_id,$dayTime.'_'.$order->id);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success($dayTime.'理财返利收益已分发');
    }

    /**
     * 计算会员的激活状态，每半小时执行一次
     * @return void
     * @throws \Throwable
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function calcIsActivation()
    {
        $userActivationCalcInterval = get_sys_config('user_activation_calc_interval');
        $beginTime = strtotime('-' . $userActivationCalcInterval . ' hour');
        $userList = User::where(['is_activation' => 1])->order('id asc')->select();
        foreach ($userList as $user) {
            if ($user->activation_time) {
                $shouldCalcTime = strtotime('+' . $userActivationCalcInterval . ' hour', $user->activation_time);
                if ($shouldCalcTime > time()) {
                    continue;
                }
            }
            $contractCount = ContractOrder::where(['user_id' => $user->id])->whereBetweenTime('buy_time', $beginTime, time())->count();
            $managementCount = ManagementOrder::where(['user_id' => $user->id])->whereBetweenTime('create_time', $beginTime, time())->count();
            $minersCount = Order::where(['user_id' => $user->id])->whereBetweenTime('create_time', $beginTime, time())->count();
            if (!$contractCount && !$managementCount && !$minersCount && $user->is_activation == 1) {
                $user->save(['is_activation' => 0, 'activation_time' => null]);
                Queue::push('\app\custom\job\UserQueue@updateTeamLevel', ['user_id' => $user->id, 'num' => -1], 'user');
            }
        }
        $this->success(date('Y-m-d') . ' 定期计算会员的激活状态');
    }

    /**
     * 充值订单超时取消脚本，每5分钟执行一次
     * @return void
     * @throws \Throwable
     */
    public function rechargeOrderTimeout()
    {
        $rechargeTimeoutInterval = get_sys_config('recharge_timeout_interval');
        $limitTime = strtotime('-' . $rechargeTimeoutInterval . ' minute');
        FinancialRecharge::where(['status' => 0])->whereTime('create_time', '<=', $limitTime)->update(['status' => 2, 'update_time' => time()]);
        $this->success('超时的充值订单已取消');
    }

    /**
     * 分红奖励，每天23:55执行
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function bonusAward()
    {
        $levelMap = [];
        $levelList = Level::where('is_open', 1)->where('bonus', '>', 0)->order('level asc')->select();
        foreach ($levelList as $level) {
            $levelMap[$level->level] = $level;
        }
        Db::startTrans();
        try {
            $userList = User::where('status', 1)->select();
            foreach ($userList as $user) {
                $exists = CoinChange::where('type', 'bonus_award')->where('user_id', $user->id)->whereDay('create_time')->find();
                if ($exists) {
                    continue;
                }
                if (array_key_exists($user->level, $levelMap)) {
                    $level = $levelMap[$user->level];
                    $bonus = $level->bonus;
                    Assets::updateMainCoinAssetsBalance($user->id, $bonus, 'bonus_award');
                }
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('分红奖励分发成功');
    }

    /**
     * 层级修复
     * @return void
     */
    public function refeshTeamLevel()
    {
        $time = strtotime("2024-11-16");
        $userList = User::where('create_time', '>', $time)->order('id asc')->select();

        foreach ($userList as $user) {
            $refereeUser = User::where(['id' => $user->refereeid])->find();
            if (!$refereeUser) {
                continue;
            }
            $data['refereeid'] = $refereeUser->id;
            if ($refereeUser->is_team_leader == 1) {
                $data['team_level'] = 1;
                $data['team_leader_id'] = $refereeUser->id;
            } else {
                $data['team_level'] = $refereeUser->team_level + 1;
                $data['team_leader_id'] = $refereeUser->team_leader_id;
            }
            User::where(['id' => $user->id])->update($data);
        }

        $this->success('层级修复完成');
    }


    /**
     * 清理全局opcache缓存
     * @return void
     */
    public function opcacheReset(){
        opcache_reset();
        $this->success('清理全局opcache缓存完成');
    }

}
