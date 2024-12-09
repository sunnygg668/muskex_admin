<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use app\admin\model\ba\market\Lecturer as LecturerModel;

class Lecturer extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function index()
    {
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);

        $list = LecturerModel::where('status', 1)
            ->order('weigh desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]);

        $result = [
            'list' => $list,
        ];
        $this->success('', $result);
    }
}