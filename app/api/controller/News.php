<?php

namespace app\api\controller;

use app\admin\model\ba\market\News as NewsModel;
use app\common\controller\Frontend;

class News extends Frontend
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
        $newsList = NewsModel::where(['status' => '1'])
            ->whereTime('release_time', '<=', time())
            ->order('id desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]
            );
        foreach ($newsList as $news) {
            $news['content'] = mb_substr(strip_tags($news['content']), 0, 60);
        }
        $data = ['list' => $newsList];
        $this->success('', $data);
    }

    public function detail()
    {
        $id = request()->param('id');
        $news = NewsModel::where(['id' => $id, 'status' => '1'])->find();
        $this->success('', $news);
    }

}