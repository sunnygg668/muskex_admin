<?php

namespace app\api\controller;

use app\admin\model\ba\market\Notice as NoticeModel;
use app\common\controller\Frontend;

class Notice extends Frontend
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
        $list = NoticeModel::whereTime('release_time', '<=', time())
            ->withoutField('content')
            ->order('is_top desc, release_time desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]
            );
        $data = ['list' => $list];
        $this->success('', $data);
    }

    public function detail()
    {
        $id = request()->param('id');
        $notice = NoticeModel::find($id);
        $this->success('', $notice);
    }

}
