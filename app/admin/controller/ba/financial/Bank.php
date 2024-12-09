<?php

namespace app\admin\controller\ba\financial;

use app\common\controller\Backend;

class Bank extends Backend
{
    /**
     * Bank模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\financial\Bank
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\financial\Bank;
    }
}