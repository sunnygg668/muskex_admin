<?php

namespace app\admin\controller\ba\user;

use app\common\controller\Backend;

class CoinChangeTypes extends Backend
{
    /**
     * CoinChangeTypes模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\user\CoinChangeTypes
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time'];

    protected string|array $quickSearchField = ['type_key', 'type_name'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\user\CoinChangeTypes;
    }
}