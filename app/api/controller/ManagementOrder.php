<?php

namespace app\api\controller;

use app\admin\model\ba\trade\ManagementOrder as ManagementOrderModel;
use app\common\controller\Frontend;
use think\db\Query;

class ManagementOrder extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function list(): void
    {
        $userId = $this->auth->id;
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);
        $orderList = ManagementOrderModel::where(['user_id' => $userId])
            ->with([
                'coinManagement' => function (Query $query) {$query->field('id, name, income_type');},
                'settlementCoin' => function (Query $query) {$query->field('id, name');},
                'incomeCoin' => function (Query $query) {$query->field('id, name');}
            ])
            ->order('id desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]
            );
        $data = ['list' => $orderList];
        $this->success('', $data);
    }

}