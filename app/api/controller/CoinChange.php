<?php

namespace app\api\controller;

use app\admin\model\ba\user\CoinChange as CoinChangeModel;
use app\admin\model\ba\user\ManagementChange as ManagementChangeModel;
use app\admin\model\ba\user\CommissionChange;
use app\common\controller\Frontend;

class CoinChange extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function list()
    {
        $userId = $this->auth->id;
        $coinId = $this->request->param('coinId');
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);
        $list = CoinChangeModel::where(['user_id' => $userId, 'coin_id' => $coinId])
            ->with(['coin', 'coinChangeTypes'])
            ->where('type', '<>', 'system_deduction')
            ->order('id desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]);
        $list->visible(['coin' => ['id', 'name', 'logo_image'], 'coinChangeTypes' => ['id', 'type_name', 'color', 'logo']]);
        $data = ['list' => $list];
        $this->success('', $data);
    }
    public function managementIncomeList()
    {
        $userId = $this->auth->id;
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);
        $list = ManagementChangeModel::where(['user_id' => $userId])->where('type', 'in', ['transfer_in_money', 'transfer_out_money', 'money_income', 'rebate_income'])
            ->with(['coin', 'coinChangeTypes'])
            ->order('id desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]);
        $list->visible(['coin' => ['id', 'name', 'logo_image'], 'coinChangeTypes' => ['id', 'type_name', 'color', 'logo']]);
        $data = ['list' => $list];
        $this->success('', $data);
    }

}
