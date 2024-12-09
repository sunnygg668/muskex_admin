<?php

namespace app\admin\controller\ba\market;

use app\common\controller\Backend;

class Lecturer extends Backend
{
    /**
     * Lecturer模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\market\Lecturer
     */
    protected object $model;

    protected string|array $defaultSortField = 'weigh,desc';

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\market\Lecturer;
    }

}