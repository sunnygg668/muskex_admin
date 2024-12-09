<?php

namespace app\admin\controller\ba\financial;

use app\common\controller\Backend;
use Throwable;

class PaymentChannel extends Backend
{
    /**
     * PaymentMethod模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\financial\PaymentChannel
     */
    protected object $model;

    protected string|array $defaultSortField = 'id,desc';

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\financial\PaymentChannel;
    }


    /**
     * 查看
     * @throws Throwable
     */
    public function channelList(): void
    {
        $res = $this->model
            ->where(['status' => 1])
            ->order("id desc")->select();

        $this->success('', [
            'list'   => $res
        ]);
    }
}
