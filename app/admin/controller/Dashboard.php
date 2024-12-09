<?php

namespace app\admin\controller;

use app\admin\model\ba\coin\Recharge as CoinRechargeModel;
use app\admin\model\ba\financial\Recharge;
use app\admin\model\ba\financial\Withdraw;
use app\admin\model\ba\miners\Order;
use app\admin\model\ba\trade\ContractOrder;
use app\admin\model\ba\trade\ManagementOrder;
use app\admin\model\ba\user\CoinChange;
use app\admin\model\ba\user\CommissionChange;
use app\admin\model\User;
use app\common\controller\Backend;
use think\db\Query;

class Dashboard extends Backend
{
    public function initialize(): void
    {
        parent::initialize();
    }

    public function index(): void
    {
        // 白名单用户
        $whitelistUserIds = User::where('is_whitelist', 1)->column('id');
        $todayQuery = function (Query $query) use ($whitelistUserIds) {
            $query->whereDay('create_time')->where('user_id', 'not in', $whitelistUserIds);
        };
        $yesterdayQuery = function (Query $query) use ($whitelistUserIds) {
            $query->whereDay('create_time', 'yesterday')->where('user_id', 'not in', $whitelistUserIds);
        };
        $updateTimeTodayQuery = function (Query $query) use ($whitelistUserIds) {
            $query->whereDay('update_time')->where('user_id', 'not in', $whitelistUserIds);
        };
        // 主币种 ID
        $mainCoinId = get_sys_config('main_coin');

        // 今日注册/总注册（当天/全部）
        $todayRegNum = User::whereDay('create_time')->count();
        $totalRegNum = User::count();

        // 今日充值（今日去白名单的RMB总数/笔数）
        $todayFinancialRecharge = Recharge::where($todayQuery)->where('status', 1)->sum('amount');
        $todayFinancialRechargeNum = Recharge::where($todayQuery)->where('status', 1)->count();

        // 今日提现（今日去白名单的RMB总数/笔数）
        $todayWithdraw = Withdraw::where($updateTimeTodayQuery)->where('status', 'in', ['1', '3'])->where('type', 0)->sum('money');
        $todayWithdrawNum = Withdraw::where($updateTimeTodayQuery)->where('status', 'in', ['1', '3'])->where('type', 0)->count();

        // 总充值/总提现（去白名单的RMB充值/提现总数）
        $totalRecharge = Recharge::where('user_id', 'not in', $whitelistUserIds)->where('status', 1)->sum('amount');
        $totalWithdraw = Withdraw::where('user_id', 'not in', $whitelistUserIds)->where('status', 'in', ['1', '3'])->where('type', 0)->sum('money');

        // USDT充值（今日去白名单的USDT充值数量/笔数）
        $todayUsdtRecharge = CoinRechargeModel::where($todayQuery)->sum('amount');
        $todayUsdtRechargeNum = CoinRechargeModel::where($todayQuery)->count();

        // USDT提现（今日去白名单的USDT提现总数/笔数）
        $todayUsdtWithdraw = Withdraw::where($updateTimeTodayQuery)->where('status', 'in', ['1', '3'])->where('type', 1)->sum('coin_num');
        $todayUsdtWithdrawNum = Withdraw::where($updateTimeTodayQuery)->where('status', 'in', ['1', '3'])->where('type', 1)->count();

        // 矿机产出（今日去白名单的矿机产出USDT）
        // $todayMinersProduce = CoinChange::where($todayQuery)->where('type', 'miners_income')->sum('amount');
        $todayContractIncome = ContractOrder::whereDay('buy_time')->where('user_id', 'not in', $whitelistUserIds)->sum('income');

        // 今日消费（去白名单的矿机+定期理财的交易金额）
        $todayMinersConsum = Order::where('settlement_coin_id', $mainCoinId)->where($todayQuery)->sum('total_price');
        $todayManagementConsum = ManagementOrder::where('settlement_coin_id', $mainCoinId)->where($todayQuery)->sum('total_price');

        // 代理返点（佣金池返点）
        $todayCommissionPoolRebate = CommissionChange::where($todayQuery)->where('type', 'margin_reward')->sum('amount');

        // 产生收益（昨日结算的矿机/理财收益）
        $yesterdayMinersIncome = CoinChange::where($yesterdayQuery)->where('type', 'miners_income')->where('coin_id', $mainCoinId)->sum('amount');
        $yesterdayManagementIncome = CoinChange::where($yesterdayQuery)->where('type', 'management_income')->where('coin_id', $mainCoinId)->sum('amount');

        $newUserIds = User::whereDay('create_time')->where('id', 'not in', $whitelistUserIds)->column('id');
        // 日新增用户充值
        $todayNewUserRechargeMoney = Recharge::where('user_id', 'in', $newUserIds)->where('status', 1)->sum('amount');
        $todayNewUserRechargeMoneyNum = Recharge::where('user_id', 'in', $newUserIds)->where('status', 1)->count();

        // 日新增用户 USDT 充值
        $todayNewUserRechargeCoin = CoinRechargeModel::where('user_id', 'in', $newUserIds)->sum('amount');
        $todayNewUserRechargeCoinNum = CoinRechargeModel::where('user_id', 'in', $newUserIds)->count();

        // 抽奖累计扣除
        $totalLotteryDeduction = CoinChange::where('user_id', 'not in', $whitelistUserIds)->where('type', 'lottery_deduction')->sum('amount');
        $totalLotteryDeduction = abs($totalLotteryDeduction);
        // 抽奖累计获得
        $totalLotteryGain = CoinChange::where('user_id', 'not in', $whitelistUserIds)->where('type', 'lottery_gain')->sum('amount');

        $this->success('', [
            'todayRegNum' => $todayRegNum,
            'totalRegNum' => $totalRegNum,
            'todayFinancialRecharge' => $todayFinancialRecharge,
            'todayFinancialRechargeNum' => $todayFinancialRechargeNum,
            'todayWithdraw' => $todayWithdraw,
            'todayWithdrawNum' => $todayWithdrawNum,
            'totalRecharge' => $totalRecharge,
            'totalWithdraw' => $totalWithdraw,
            'todayUsdtRecharge' => $todayUsdtRecharge,
            'todayUsdtRechargeNum' => $todayUsdtRechargeNum,
            'todayUsdtWithdraw' => $todayUsdtWithdraw,
            'todayUsdtWithdrawNum' => $todayUsdtWithdrawNum,
            // 'todayMinersProduce' => $todayMinersProduce,
            'todayContractIncome' => $todayContractIncome,
            'todayMinersConsum' => $todayMinersConsum,
            'todayManagementConsum' => $todayManagementConsum,
            'todayCommissionPoolRebate' => $todayCommissionPoolRebate,
            'yesterdayMinersIncome' => $yesterdayMinersIncome,
            'yesterdayManagementIncome' => $yesterdayManagementIncome,
            'todayNewUserRechargeMoney' => $todayNewUserRechargeMoney,
            'todayNewUserRechargeMoneyNum' => $todayNewUserRechargeMoneyNum,
            'todayNewUserRechargeCoin' => $todayNewUserRechargeCoin,
            'todayNewUserRechargeCoinNum' => $todayNewUserRechargeCoinNum,
            'totalLotteryDeduction' => $totalLotteryDeduction,
            'totalLotteryGain' => $totalLotteryGain
        ]);
    }
}