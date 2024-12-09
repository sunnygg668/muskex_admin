<?php

namespace app\api\controller;

use app\admin\model\ba\financial\Bank;
use app\admin\model\ba\financial\Card;
use app\admin\model\ba\financial\Withdraw;
use app\admin\model\Config;
use app\common\controller\Frontend;
use think\db\Query;
use think\facade\Db;

class FinancialCard extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function bankList(): void
    {
        $bankList = Bank::where(['status' => '1'])->order('id asc')->select();
        $this->success('', $bankList);
    }

    public function bankManagementTip()
    {
        $bankManagementTip = get_sys_config('bank_management_tip');
        $this->success('', $bankManagementTip);
    }

    public function getBank() {
        $card = $this->request->param('card');

        $bankList = \think\facade\Config::get('bank_list');

        $card_8 = substr($card, 0, 8);
        if (isset($bankList[$card_8])) {
            $bankList = Bank::where(['status' => '1'])->where('name', $bankList[$card_8])->order('id asc')->select();
            $this->success('', $bankList);
        }
        $card_6 = substr($card, 0, 6);
        if (isset($bankList[$card_6])) {
            $bankList = Bank::where(['status' => '1'])->where('name', $bankList[$card_6])->order('id asc')->select();
            $this->success('', $bankList);
        }

        $this->error();
    }

    public function add()
    {
        $user = $this->auth->getUser();
        $bankId = $this->request->param('bankId');
        $accountName = $this->request->param('accountName','');
        $bankNum = $this->request->param('bankNum');
        $fundPassword = $this->request->param('fundPassword');
        if (!$this->auth->checkFundPassword($fundPassword)) {
            $this->error('资金密码错误');
        }
        if (empty($user->is_certified) || empty($user->idcard) || $user->is_certified != 2) {
            $this->error('请先完成实名认证');
        }
        $bankCount = Card::where(['user_id' => $user->id, 'status' => 1])->count();
        $maxBindCard = get_sys_config('max_bind_card');
        if ($bankCount >= $maxBindCard) {
            $this->error('绑卡数量已达上限，最大可绑定 ' . $maxBindCard . ' 张');
        }
        $existsCard = Card::where(['user_id' => $user->id, 'financial_bank_id' => $bankId, 'bank_num' => $bankNum])->lock(true)->find();
        if ($existsCard) {
            $this->error('该银行卡已存在，请勿重复绑定');
        }
        Db::startTrans();
        try {
            $card = [
                'user_id' => $user->id,
                'financial_bank_id' => $bankId,
                'account_name' => $accountName,
                'bank_num' => $bankNum,
            ];
            Card::create($card);
            // 添加银行卡，限制提现时间
            $newCardWithdrawalInterval = get_sys_config('new_card_withdrawal_interval');
            $limitWithdrawTime = strtotime('+' . $newCardWithdrawalInterval . ' hour');
            $user->save(['limit_withdraw_time' => $limitWithdrawTime]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('添加成功');
    }

    public function list(): void
    {
        $userId = $this->auth->id;
        $cardList = Card::where(['user_id' => $userId])
            ->with(['financialBank' => function (Query $query) {
                $query->field('id, name, logo');
            }])
            ->order('id desc')
            ->select();
        $data = ['list' => $cardList];
        $this->success('', $data);
    }

    public function detail()
    {
        $userId = $this->auth->id;
        $cardId = $this->request->param('cardId');
        $card = Card::where(['id' => $cardId, 'user_id' => $userId])->find();
        $this->success('', $card);
    }

    public function edit(): void
    {
        $this->error('暂不提供银行卡修改功能');
        $userId = $this->auth->id;
        $cardId = $this->request->param('cardId');
        $bankId = $this->request->param('bankId');
        $accountName = $this->request->param('accountName');
        $bankNum = $this->request->param('bankNum');
        $fundPassword = $this->request->param('fundPassword');
        if (!$this->auth->checkFundPassword($fundPassword)) {
            $this->error('资金密码错误');
        }
        $card = Card::where(['id' => $cardId, 'user_id' => $userId])->find();
        if (!$card) {
            $this->error('银行卡记录不存在');
        }
        $card->save([
            'financial_bank_id' => $bankId,
            'account_name' => $accountName,
            'bank_num' => $bankNum,
        ]);
        $this->success('修改成功');
    }

    public function delete(): void
    {
        $userId = $this->auth->id;
        $cardId = $this->request->param('cardId');
        $noCompleteWithdrawCount = Withdraw::where('user_id', $userId)->where('status', 'in', ['0', '4'])->count();
        if ($noCompleteWithdrawCount > 0) {
            $this->error('当前存在未完成的提现记录，暂时无法删除');
        }
        $result = Card::where(['id' => $cardId, 'user_id' => $userId])->delete();
        if (!$result) {
            $this->error('删除失败');
        } else {
            $this->success('删除成功');
        }
    }
}
