<?php

namespace App\admin\controller\auth;

use app\common\controller\Backend;

class Domain extends Backend
{
    /**
     * PaymentMethod模型对象
     * @var object
     * @phpstan-var \app\admin\model\Domain
     */
    protected object $model;

    protected string|array $defaultSortField = 'id,desc';

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    protected array $withJoinTable = [
        'user' => ['username', 'nickname', 'mobile', 'name'],
    ];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\Domain;
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->withoutField('password,salt')
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }
}
