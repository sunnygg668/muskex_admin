<?php

namespace app\api\controller;

use app\admin\model\ba\coin\Coin;
use app\admin\model\ba\market\LotteryAward as LotteryAwardModel;
use app\admin\model\ba\market\LotteryRecord;
use app\admin\model\ba\user\Assets;
use app\admin\model\ba\user\CoinChange;
use app\common\controller\Frontend;
use app\custom\library\NumberUtil;
use think\facade\Db;

class LotteryAward extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function index(): void
    {
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $awardList = LotteryAwardModel::select();
        $lotteryPrice = get_sys_config('lottery_price');
        $mainCoin = Coin::mainCoin();
        $awareRuleTip = get_sys_config('aware_rule_tip');
        $dayCount = get_sys_config('day_count');
        $todayCount = LotteryRecord::where('user_id', $userId)->whereDay('create_time')->count();
        $todayLeftFreeCount = max($dayCount - $todayCount, 0);
        $todayLeftFreeCount += $user->lottery_count;
        $data = [
            'awardList' => $awardList,
            'lotteryPrice' => $lotteryPrice,
            'unit' => $mainCoin->name,
            'awareRuleTip' => $awareRuleTip,
            'todayLeftFreeCount' => $todayLeftFreeCount
        ];
        $this->success('', $data);
    }

    public function draw()
    {
        $user = $this->auth->getUser();
        $dayCount = get_sys_config('day_count');
        $todayCount = LotteryRecord::where('user_id', $user->id)->whereDay('create_time')->count();
        Db::startTrans();
        try {
            if ($todayCount >= $dayCount) {
                if ($user->lottery_count > 0) {
                    $user->dec('lottery_count');
                } else {
                    $lotteryPrice = get_sys_config('lottery_price');
                    if ($lotteryPrice > 0) {
                        Assets::updateMainCoinAssetsBalance($user->id, -$lotteryPrice, 'lottery_deduction');
                    }
                }
            }
            $awardArray = LotteryAwardModel::select()->toArray();
            $totalProbability = array_sum(array_column($awardArray, 'weigh'));
            $rand = mt_rand(1, $totalProbability);
            $accumulatedProbability = 0;
            $winningAward = null;
            foreach ($awardArray as $award) {
                $accumulatedProbability += $award['weigh'];
                if ($rand <= $accumulatedProbability) {
                    $winningAward = $award;
                    break;
                }
            }
            $amount = NumberUtil::generateRand($winningAward['amount_down'], $winningAward['amount_up']);
            Assets::updateCoinAssetsBalance($user->id, $winningAward['coin_id'], $amount, 'lottery_gain');
            $record = [
                'user_id' => $user->id,
                'market_lottery_award_id' => $winningAward['id'],
                'amount' => $amount,
                'name' => $winningAward['name'],
                'coin_id' => $winningAward['coin_id'],
            ];
            LotteryRecord::create($record);
            $totalCount = LotteryRecord::where('user_id', $user->id)->count();
            $rewardCoin = get_sys_config('reward_coin');
            $rewardRule = get_sys_config('reward_rule');
            if ($rewardCoin && $rewardRule) {
                array_multisort(array_column($rewardRule,'key'),SORT_DESC, $rewardRule);
                foreach ($rewardRule as $key => $rule) {
                    if ($totalCount >= $rule['key'] && $rule['value'] > 0) {
                        $existsCount = CoinChange::where(['user_id' => $user->id, 'type' => 'lottery_give', 'remark' => $key])->count();
                        if ($existsCount > 0) {
                            break;
                        } else {
                            $amount = $rule['value'];
                            Assets::updateCoinAssetsBalance($user->id, $rewardCoin, $amount, 'lottery_give', null, $key);
                        }
                    }
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('', $winningAward);
    }
    public function lotteryRecordList()
    {
        $userId = $this->auth->id;
        $pagesize = request()->param('pagesize', 10);
        $page = request()->param('page', 1);
        $list = LotteryRecord::with(['coin'])
            ->where('user_id', $userId)
            ->order('id desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]
            );
        $list->visible(['coin' => ['name']]);
        $data = ['list' => $list];
        $this->success('', $data);
    }

}