<?php

namespace app\api\controller;

use app\admin\model\ba\coin\Coin;
use app\admin\model\ba\coin\Contract;
use app\admin\model\ba\financial\Card;
use app\admin\model\ba\trade\ContractOrder as ContractOrderModel;
use app\admin\model\ba\user\Assets;
use app\common\controller\Frontend;
use app\common\library\RedisKey;
use app\custom\library\RedisUtil;
use app\custom\library\NumberUtil;
use ba\Exception;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Cache;

class ContractOrder extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function contractInfo()
    {
        $klineType = $this->request->param('kline_type');
        $coin = RedisUtil::remember(RedisKey::COIN_MARGIN . $klineType, function() use ($klineType) {
            return Coin::where(['kline_type' => $klineType])->field('margin,name')->find();
        }, 600);
//        $contract = Contract::where(['coin_id' => $coin['id']])->find();

//        $client = BinanceUtil::getBinanceRedis();
//        $ticker = json_decode($client->get('ticker'), true);
//        $key = strtolower(str_replace('/', '', $klineType)) . '@ticker';
//        $price = isset($ticker[$key]) ? $ticker[$key]['data']['c'] : 0;
        $result = [
            'fee_ratio' => 1,
            'purchase_up' => 1,
            'purchase_down' => 1,
            'margin' => $coin['margin'],
//            'price' => $price
        ];
        $this->success('', $result);
    }

    public function buy(): void
    {
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $klineType = $this->request->param('kline_type');
        $num = (int)$this->request->param('num');
        if (empty($user->is_certified) || empty($user->idcard) || $user->is_certified != 2) {
            $this->error('请先完成实名认证');
        }

        $lockKey = RedisKey::CONTRACT_ORDER_LOCK . $userId . '_' . $klineType;
        $isLocked = RedisUtil::set($lockKey, 1,'EX',150,'NX');
        if (!$isLocked) {
            $this->error('请勿频繁购买该合约币种');
        }

//        $card = Card::where(['user_id' => $userId, 'status' => 1])->find();
//        if (!$card) {
//            $this->error('请先绑定一张银行卡，并等待审核通过');
//        }


        try {
//            $coin = Coin::where(['kline_type' => $klineType])->find();
            // 获取缓存的合约数据，减少重复查询
            $coin = RedisUtil::remember(RedisKey::COIN . $klineType, function() use ($klineType) {
                return Coin::where('kline_type', $klineType)->field('id,name,margin')->find();
            }, 600);
            if (!$coin) {
                $this->error('当前合约未开放');
            }

//            $contract = Contract::where(['coin_id' => $coin['id'], 'status' => 1])->find();
            // 获取合约限制信息
            $contract = RedisUtil::remember(RedisKey::CONTRACT . $coin['id'], function() use ($coin) {
                return Contract::where(['coin_id' => $coin['id'], 'status' => 1])->field('id,purchase_up,purchase_down,fee_ratio')->find();
            }, 600);
            if (!$contract) {
                $this->error('当前合约未开放');
            }
            $existsOrder = ContractOrderModel::where(['user_id' => $userId, 'contract_id' => $contract['id'], 'status' => 0])->find();
            if ($existsOrder) {
                $this->error('当前有未完成的合约订单，禁止交易');
            }

            if ($contract['purchase_up'] && $num > $contract['purchase_up']) {
                $this->error('购买上限为 ' . $contract['purchase_up']);
            }
            if ($contract['purchase_down'] && $num < $contract['purchase_down']) {
                $this->error('购买下限为 ' . $contract['purchase_down']);
            }

            $investedCoinNum = bcmul($coin['margin'], $num, 2);
            $fee = bcmul($investedCoinNum, $contract['fee_ratio'] / 100, 2);
            $mainCoin = get_sys_config('main_coin');

            $assets = Assets::coinAssets($userId, $mainCoin);
            if ($assets->balance < bcadd($investedCoinNum, $fee, 2)) {
                $this->error('币种余额不足');
            }
            //启动事物
            Db::startTrans();
            //更新资产
            Assets::updateCoinAssetsBalance($userId, $mainCoin, -$investedCoinNum, 'contract_buy');
            Assets::updateCoinAssetsBalance($userId, $mainCoin, -$fee, 'contract_buy_fee');

            $orderNo = 'CT' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $order = [
                'user_id' => $userId,
                'refereeid' => $user->refereeid,
                'team_leader_id' => $user->team_leader_id,
                'contract_id' => $contract['id'],
                'coin_id' => $coin['id'],
                'order_no' => $orderNo,
                'title' => $coin['name'],
                'num' => $num,
                'buy_price' => $coin['margin'],
                'invested_coin_num' => $investedCoinNum,
                'fee' => $fee,
                'fee_ratio' => $contract['fee_ratio'],
                'buy_time' => time()
            ];
            ContractOrderModel::create($order);
            Db::commit();

            // 延迟 10 分钟后执行任务
            $delay = 600; // 延迟时间（秒）
            Queue::later($delay, '\app\custom\job\RewardQueue@userActivation', ['user_id' => $userId], 'reward');
            Queue::later($delay, '\app\custom\job\RewardQueue@contractBuy', ['user_id' => $userId, 'margin' => $investedCoinNum], 'reward');
            Queue::later($delay, '\app\custom\job\TaskRewardQueue@todayContractNumReached', ['user_id' => $userId], 'task_reward');
            Queue::later($delay, '\app\custom\job\TaskRewardQueue@todayContractAmountReached', ['user_id' => $userId], 'task_reward');
            Queue::later($delay, '\app\custom\job\TaskRewardQueue@monthContractAmountReached', ['user_id' => $userId], 'task_reward');
            Queue::later($delay, '\app\custom\job\TaskRewardQueue@firstContractAmountReached', ['user_id' => $userId], 'task_reward');
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage(), null, $e->getCode());
        }

//        RedisUtil::del($lockKey); //释放锁

        $this->success('购买成功');
    }

    public function list(): void
    {
        $userId = $this->auth->id;
        $status = request()->param('status');
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);
        $w = [];
        if ($status != '3') {
            $w['status'] = $status;
        }
        $list = ContractOrderModel::where(['user_id' => $userId])
            ->where($w)
            ->field('id,title,num,status,income,buy_price,buy_time,sell_time,payment_status')
            ->order('id desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]);
        $result = ['list' => $list];
        $this->success('', $result);
    }

    public function sell(): void
    {
        $userId = $this->auth->id;
        $orderId = $this->request->param('orderId');

        $lockKey = RedisKey::CONTRACT_ORDER_SELL_LOCK . $userId;
        $isLocked = RedisUtil::set($lockKey, 1,'EX',20,'NX');
        if (!$isLocked) {
            $this->error('请勿频繁操作');
        }

        Db::startTrans();
        try {
            $order = ContractOrderModel::where(['user_id' => $userId, 'id' => $orderId, 'status' => 0])->lock(true)->find();
            if (!$order) {
                $this->error('该笔订单已卖出');
            }
            $contract = Contract::find($order->contract_id);
            $buy_price = $order->buy_price;
            $buyingCycle = $contract->buying_cycle;
            $buyingCycleUnit = $contract->buying_cycle_unit;
            $limitTime = strtotime('+' . $buyingCycle . ' ' . $buyingCycleUnit, strtotime($order->buy_time));
            if (time() < $limitTime) {
                $buyingCycleUnitArray = ['minute' => '分钟', 'hour' => '小时', 'day' => '天', 'year' => '年'];
                $this->error('当前订单需持有至少 ' . $buyingCycle . ' ' . $buyingCycleUnitArray[$buyingCycleUnit] . '后可以卖出！');
            }
            if ($contract->is_profit == '0') {
                $up = $contract->loss_ratio_up;
                $down = $contract->loss_ratio_down;
                $symbol = -1;
            } else {
                $up = $contract->profit_ratio_up;
                $down = $contract->profit_ratio_down;
                $symbol = 1;
            }
            $ratio = NumberUtil::generateRand($up, $down);
            $ratio = 1 + ($symbol * $ratio / 100);
            $sellPrice = bcmul($buy_price, $ratio, 2);
            $sellCoinNum = bcmul($sellPrice, $order->num, 2);
            $incomeCoinNum = bcsub($sellCoinNum, $order->invested_coin_num, 2);
            $incomeRatio = bcdiv($incomeCoinNum, $order->invested_coin_num, 2);
            $order->save([
                'sell_price' => $sellPrice,
                'income' => $incomeCoinNum,
                'income_ratio' => $incomeRatio,
                'status' => '1',
                'payment_status' => $incomeCoinNum < 0 ? 0 : null,
                'sell_time' => time()
            ]);
            Assets::updateMainCoinAssetsBalance($userId, $order->invested_coin_num, 'contract_sell');
            Assets::updateMainCoinAssetsBalance($userId, $incomeCoinNum, 'contract_income');
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage(), null, $e->getCode());
        }

        RedisUtil::del($lockKey); //释放锁

        $this->success('卖出成功');
    }
}
