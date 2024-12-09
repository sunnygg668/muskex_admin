<?php

namespace app\admin\controller\ba\report;

use app\admin\model\User;
use app\common\controller\Backend;
use app\common\library\RedisKey;
use app\custom\library\RedisUtil;
use Throwable;

class TeamStatistics extends Backend
{
    /**
     * TeamStatistics模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\report\TeamStatistics
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'team_withdraw', 'team_left_withdraw', 'create_time', 'update_time'];

    protected array $withJoinTable = ['user'];

    protected string|array $quickSearchField = ['user.mobile'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\report\TeamStatistics;
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

        $w = [];
        if (empty($where)) {
            $w['user.is_team_leader'] = 1;
        }

        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->where($w)
            ->order($order)
            ->paginate($limit);
        $res->visible(['user' => ['wallet_addr', 'nickname', 'username', 'mobile', 'name', 'refereeid']]);
        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function total()
    {
        $data = RedisUtil::get(RedisKey::REPORT_TEAM_STATISTICS_TOTAL);
        $this->success('', $data);
    }
}
