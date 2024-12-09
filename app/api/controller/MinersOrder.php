<?php

namespace app\api\controller;

use app\admin\model\ba\miners\Exchange;
use app\admin\model\ba\miners\Miners;
use app\admin\model\ba\miners\Order;
use app\admin\model\ba\user\Assets;
use app\common\controller\Frontend;
use ba\Exception;
use think\facade\Db;
use think\facade\Queue;

class MinersOrder extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function lease(): void
    {
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $minersId = $this->request->param('minersId');
        $num = $this->request->param('num');
        $fundPassword = $this->request->param('fundPassword');
        $exchangeCode = $this->request->param('exchange_code');
        if ($num <= 0 || !intval($num)) {
            $this->error('请输入正确的矿机租赁数量');
        }
        if (empty($user->is_certified) || empty($user->idcard) || $user->is_certified != 2) {
            $this->error('请先完成实名认证');
        }
        if (!$this->auth->checkFundPassword($fundPassword)) {
            $this->error('资金密码错误');
        }
        $miners = Miners::find($minersId);
        if ($miners->status == '0') {
            $this->error('该矿机已暂停租赁');
        }
        $purchasedNums = Order::where(['user_id' => $userId, 'miners_id' => $minersId, 'status' => '1'])->count();
        if ($num > $miners->buy_limit || ($purchasedNums + $num) > $miners->buy_limit) {
            $this->error('该矿机限购 ' . $miners->buy_limit . ' 台');
        }
        $leftNum = $miners->issues_num - $miners->sales_num;
        if ($num > $leftNum) {
            $this->error('矿机数量不足，目前可租赁数：' . $leftNum);
        }
        Db::startTrans();
        try {
            $orderNo = 'MO' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $realPay = $totalPrice = bcmul($miners->price, $num, 2);
            $discountInfo = [];
            if ($exchangeCode) {
                $exchange = Exchange::where(['code' => $exchangeCode, 'miners_id' => $minersId, 'status' => 1])->find();
                if (!$exchange) {
                    $this->error('兑换码无效');
                }
                if ($exchange->used_num >= $exchange->total_num) {
                    $this->error('该兑换码已使用');
                }
                if ($num > $exchange->total_num) {
                    $this->error('该兑换码最多可以兑换 ' . $exchange->total_num . ' 台矿机');
                }
                $discountRatio = $exchange->discount_ratio;
                if ($discountRatio > 0) {
                    $discountAmount = bcmul($totalPrice, $discountRatio / 100, 2);
                    $realPay = bcsub($totalPrice, $discountAmount, 2);
                    $discountInfo = [
                        'exchange_code' => $exchangeCode,
                        'discount_ratio' => $discountRatio,
                        'discount_amount' => $discountAmount,
                    ];
                    $exchange->used_num = $exchange->used_num + $num;
                    $exchange->user_id = $user->id;
                    $exchange->order_no = $orderNo;
                    $exchange->save();
                }
            }
            Assets::updateCoinAssetsBalance($userId, $miners->settlement_coin_id, -$realPay, 'lease_miners');
            $order = [
                'miners_id' => $minersId,
                'user_id' => $userId,
                'refereeid' => $user->refereeid,
                'team_leader_id' => $user->team_leader_id,
                'settlement_coin_id' => $miners->settlement_coin_id,
                'produce_coin_id' => $miners->produce_coin_id,
                'order_no' => $orderNo,
                'price' => $miners->price,
                'num' => $num,
                'total_price' => $totalPrice,
                'real_pay' => $realPay,
                'estimated_income' => $miners->gen_income * $num,
                'pending_income' => $miners->gen_income * $num,
                'run_minutes' => $miners->run_days * 24 * 60,
                'run_days' => $miners->run_days,
                'expire_time' => strtotime('+' . $miners->run_days . ' days'),
            ];
            $order = array_merge($order, $discountInfo);
            Order::create($order);
            $miners->setInc('sales_num', 1);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage(), null, $e->getCode());
        }
        $this->success('租赁成功');
    }

    public function list(): void
    {
        $userId = $this->auth->id;
        $status = request()->param('status');
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);
        $orderList = Order::where(['user_id' => $userId, 'status' => $status])
            ->with(['miners' => function ($query) {
                $query->field('id, image, name');
            }])
            ->order('id desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]);
        foreach ($orderList as &$order) {
            $estimatedIncome = $order->estimated_income;
            $runRatio = bcdiv(time() - $order->create_time, $order->run_days * 86400, 2);
            $gainedIncome = bcmul($estimatedIncome, $runRatio, 2);
            $order->gained_income = min($gainedIncome, $estimatedIncome);
        }
        $data = ['list' => $orderList];
        $this->success('', $data);
    }
}
