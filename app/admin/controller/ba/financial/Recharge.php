<?php

namespace app\admin\controller\ba\financial;

use app\admin\model\ba\financial\Recharge as RechargeModel;
use app\admin\model\ba\user\Assets;
use app\admin\model\User;
use app\common\controller\Backend;
use think\facade\Db;
use think\facade\Queue;
use Throwable;

class Recharge extends Backend
{
    /**
     * Recharge模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\financial\Recharge
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['user', 'financialPaymentMethod'];

    protected string|array $quickSearchField = ['id', 'user.username'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\financial\Recharge;
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $res->visible(['user' => ['wallet_addr', 'nickname', 'username'], 'financialPaymentMethod' => ['name']]);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function summary()
    {
        $whitelistUserIds = User::where('is_whitelist', 1)->column('id');
        $todayRecharge = RechargeModel::where('user_id', 'not in', $whitelistUserIds)
            ->whereDay('create_time')
            ->where('status', 'in', '1,3')
            ->sum('amount');
        $todayWhitelistRecharge = RechargeModel::where('user_id', 'in', $whitelistUserIds)
            ->whereDay('create_time')
            ->where('status', 'in', '1,3')
            ->sum('amount');
        $totalRecharge = RechargeModel::where('user_id', 'not in', $whitelistUserIds)
            ->where('status', 'in', '1,3')
            ->sum('amount');
        $totalWhitelistRecharge = RechargeModel::where('user_id', 'in', $whitelistUserIds)
            ->where('status', 'in', '1,3')
            ->sum('amount');
        $result = [
            'todayRecharge' => $todayRecharge,
            'todayWhitelistRecharge' => $todayWhitelistRecharge,
            'totalRecharge' => $totalRecharge,
            'totalWhitelistRecharge' => $totalWhitelistRecharge,
        ];
        $this->success('', $result);
    }

    public function audit()
    {
        $id = $this->request->param('id');
        $status = $this->request->param('status');
        $recharge = RechargeModel::find($id);
        if (in_array($recharge->status, [1, 3])) {
            $this->error('当前充值记录已被处理过');
        }
        Db::startTrans();
        try {
            if (in_array($status, [1, 3])) {
                Assets::updateMainCoinAssetsBalance($recharge->user_id, $recharge->main_coin_num, 'financial_recharge');
                // 后台确认充值订单，限制提现时间
                $newCardWithdrawalInterval = get_sys_config('per_recharge_withdrawal_interval');
                $limitWithdrawTime = strtotime('+' . $newCardWithdrawalInterval . ' hour');
                (new User())->where(['id' => $recharge->user_id])->update(['limit_withdraw_time' => $limitWithdrawTime]);
            }
            $recharge->save(['status' => $status]);
            Db::commit();
            if (in_array($status, [1, 3])) {
                Queue::push('\app\custom\job\RewardQueue@userFirstRecharge', ['user_id' => $recharge->user_id], 'reward');
                Queue::push('\app\custom\job\TaskRewardQueue@firstRechargeReachedGive', ['user_id' => $recharge->user_id, 'amount' => $recharge->amount], 'task_reward');
                Queue::push('\app\custom\job\TaskRewardQueue@todayRechargeReachedGive', ['user_id' => $recharge->user_id], 'task_reward');
                Queue::push('\app\custom\job\RewardQueue@giveLotteryCount', ['user_id' => $recharge->user_id, 'coinAmount' => $recharge->main_coin_num], 'reward');
            }
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('设置成功');
    }
}
