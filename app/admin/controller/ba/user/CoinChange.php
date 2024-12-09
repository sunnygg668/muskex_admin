<?php

namespace app\admin\controller\ba\user;

use app\admin\model\User;
use Throwable;
use app\common\controller\Backend;

class CoinChange extends Backend
{
    /**
     * CoinChange模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\user\CoinChange
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'user_id', 'create_time'];

    protected array $withJoinTable = ['user', 'coin', 'coinChangeTypes', 'fromUser', 'toUser'];

    protected string|array $quickSearchField = ['user.mobile'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\user\CoinChange;
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
        $res->visible(['user' => ['wallet_addr', 'nickname', 'name', 'username', 'mobile', 'money', 'team_leader_id'], 'coin' => ['name'], 'coinChangeTypes' => ['type_name'], 'fromUser' => ['username'], 'toUser' => ['username']]);

        $items = $res->items();
        foreach ($items as &$item) {
            $item['teamLeader'] = User::where('id', $item->user['team_leader_id'])->field('name, mobile')->find();
        }

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }
}
