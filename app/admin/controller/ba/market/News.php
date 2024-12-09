<?php

namespace app\admin\controller\ba\market;

use app\common\controller\Backend;

class News extends Backend
{
    /**
     * News模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\market\News
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\market\News;
        $this->request->filter('clean_xss');
    }

}