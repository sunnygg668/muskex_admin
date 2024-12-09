<?php

namespace app\api\controller;

use app\admin\model\ba\miners\Miners as MinersModel;
use app\common\controller\Frontend;
use think\db\Query;

class Miners extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function index(): void
    {
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);
        $mList = MinersModel::with([
                'settlementCoin' => function (Query $query) {$query->field('id, name');},
                'produceCoin' => function (Query $query) {$query->field('id, name');}
            ])
            ->where(['status' => '1'])
            ->order('weigh desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]
            );
        foreach ($mList as $m) {
            $m['left_num'] = $m->issues_num - $m->sales_num;
        }
        $data = ['list' => $mList];
        $this->success('', $data);
    }
}
