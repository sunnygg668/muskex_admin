<?php

namespace app\admin\controller\ba\coin;

use app\admin\model\ba\coin\Recharge as RechargeModel;
use app\admin\model\User;
use app\common\controller\Backend;
use Throwable;

class Recharge extends Backend
{
    /**
     * Recharge模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\coin\Recharge
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time'];

    protected array $withJoinTable = ['user'];

    protected string|array $quickSearchField = ['id', 'user.username'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\coin\Recharge;
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
        $res->visible(['user' => ['wallet_addr', 'nickname', 'username']]);

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
            ->sum('amount');
        $todayWhitelistRecharge = RechargeModel::where('user_id', 'in', $whitelistUserIds)
            ->whereDay('create_time')
            ->sum('amount');
        $totalRecharge = RechargeModel::where('user_id', 'not in', $whitelistUserIds)
            ->sum('amount');
        $totalWhitelistRecharge = RechargeModel::where('user_id', 'in', $whitelistUserIds)
            ->sum('amount');
        $result = [
            'todayRecharge' => $todayRecharge,
            'todayWhitelistRecharge' => $todayWhitelistRecharge,
            'totalRecharge' => $totalRecharge,
            'totalWhitelistRecharge' => $totalWhitelistRecharge,
        ];
        $this->success('', $result);
    }
}
