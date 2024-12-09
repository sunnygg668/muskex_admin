<?php

namespace app\api\controller;

use app\admin\model\ba\coin\Coin;
use app\admin\model\ba\coin\Recharge;
use app\common\controller\Frontend;

use think\db\Query;

class CoinRecharge extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function rechargeList()
    {
        $userId = $this->auth->id;
        $beginTime = $this->request->param('beginTime');
        $endTime = $this->request->param('endTime');
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);
        $query = function (Query $query) use ($beginTime, $endTime) {
            if ($beginTime && $endTime) {
                $beginTime .= ' 00:00:00';
                $endTime .= ' 23:59:59';
                $query->whereBetweenTime('create_time', $beginTime, $endTime);
            }
        };
        $list = Recharge::where('user_id', $userId)
            ->where($query)
            ->order('id desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]);
        $mainCoin = Coin::mainCoin();
        foreach ($list as &$item) {
            $item['coin'] = ['name' => $mainCoin->name, 'logo_image' => $mainCoin->logo_image];
            $item['status'] = "1";
            $item['status_text'] = "已到账";
        }
        $result = ['list' => $list];
        $this->success('', $result);
    }


}
