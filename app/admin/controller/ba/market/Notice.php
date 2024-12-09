<?php

namespace app\admin\controller\ba\market;

use app\common\controller\Backend;

class Notice extends Backend
{
    /**
     * Notice模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\market\Notice
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\market\Notice;
        $this->request->filter('clean_xss');
    }

}