<?php

namespace app\api\controller;

use app\admin\model\ba\coin\Recharge;
use app\admin\model\ba\financial\Recharge as FinancialRecharge;
use app\admin\model\ba\financial\Withdraw;
use app\admin\model\ba\miners\Order;
use app\admin\model\ba\report\Statistics;
use app\admin\model\ba\report\TeamStatistics;
use app\admin\model\ba\trade\ContractOrder;
use app\admin\model\ba\trade\ManagementOrder;
use app\admin\model\ba\user\Assets;
use app\admin\model\ba\user\CoinChange;
use app\admin\model\ba\user\CommissionChange;
use app\admin\model\User;
use app\common\controller\Frontend;
use app\common\library\RedisKey;
use app\custom\library\RedisUtil;
use think\App;
use think\db\Query;
use think\facade\Db;
use think\facade\Request;

class StatisticsTask extends Frontend
{

    protected array $noNeedLogin = ['*'];

    protected string $locks_key = '';

    public function __construct(App $app)
    {
        parent::__construct($app);
        //print_r($this->app->request->server()['ARGV'][2]);exit;

        $this->locks_key = RedisKey::STATISTICS_TASK.Request::server()['ARGV'][2];
        $is_lock = RedisUtil::set($this->locks_key, 1,'EX',300,'NX');
        if(!$is_lock){//存在锁
            die($this->locks_key." => Task is already running on another server.\n");
        }
    }

    public function __destruct()
    {
        RedisUtil::del($this->locks_key);
    }

    public function reportStatistics()
    {
        $date = date('Y-m-d');
        $mainCoinId = get_sys_config('main_coin');
        $activityTypes = ['check_in_reward', 'first_contract_amount_reached', 'month_invite_reached_give',
            'week_invite_reached_give', 'today_invite_reached_give', 'month_contract_amount_reached',
            'today_contract_amount_reached', 'today_contract_num_reached', 'team_num_reached_give',
            'invite_num_reached_give', 'today_recharge_reached_give', 'first_recharge_reached_give',
            'auth_give', 'u_recharge', 'invite_first_recharge', 'invite_register_reward', 'register_reward'];
        $whitelistUserIds = User::where('is_whitelist', 1)->column('id');
        $todayQuery = function (Query $query) use ($whitelistUserIds, $date) {
            $query->whereDay('create_time', $date)->where('user_id', 'not in', $whitelistUserIds);
        };
        $updateTimeTodayQuery = function (Query $query) use ($whitelistUserIds, $date) {
            $query->whereDay('update_time', $date)->where('user_id', 'not in', $whitelistUserIds);
        };
        $regNums = User::whereDay('create_time', $date)->where('id', 'not in', $whitelistUserIds)->count();
        $minersIncome = CoinChange::where($todayQuery)->where('type', 'miners_income')->where('coin_id', $mainCoinId)->sum('amount');
        $managementIncome = CoinChange::where($todayQuery)->where('type', 'management_income')->where('coin_id', $mainCoinId)->sum('amount');
        $income = bcadd($minersIncome, $managementIncome, 2);
        $minersConsumption = Order::where('settlement_coin_id', $mainCoinId)->where($todayQuery)->sum('total_price');
        $managementConsumption = ManagementOrder::where('settlement_coin_id', $mainCoinId)->where($todayQuery)->sum('total_price');
        $consumption = bcadd($minersConsumption, $managementConsumption, 2);
        $rechargeCoin = Recharge::where($todayQuery)->sum('amount');
        $rechargeMoney = FinancialRecharge::where('status', 1)->where($todayQuery)->sum('amount');
        $managementBuy = $managementConsumption;
        $minersBuy = $minersConsumption;
        $withdraw = Withdraw::where($updateTimeTodayQuery)->where('status', 'in', '1,3')->where('type', 0)->sum('money');
        $rebate = CommissionChange::where($todayQuery)->where('type', 'margin_reward')->sum('amount');
        $activity = CoinChange::where($todayQuery)->where('type', 'in', $activityTypes)->sum('amount');
        $withdrawFee = Withdraw::where($updateTimeTodayQuery)->where('status', 'in', '1,3')->sum('fee_coin');
        $fee = $withdrawFee;
        $payment = ContractOrder::whereDay('buy_time', $date)->where('user_id', 'not in', $whitelistUserIds)->where('payment_status', 1)->sum('income');
        $payment = abs($payment);
        $minersProduce = CoinChange::where($todayQuery)->where('type', 'miners_income')->where('coin_id', $mainCoinId)->sum('amount');
        $data = [
            'date' => $date,
            'reg_nums' => $regNums,
            'income' => $income,
            'consumption' => $consumption,
            'recharge_coin' => $rechargeCoin,
            'recharge_money' => $rechargeMoney,
            'management_buy' => $managementBuy,
            'miners_buy' => $minersBuy,
            'withdraw' => $withdraw,
            'rebate' => $rebate,
            'activity' => $activity,
            'fee' => $fee,
            'payment' => $payment,
            'bonus' => 0,
            'miners_produce' => $minersProduce,
            'management_income' => $managementIncome,
            'create_time' => time()
        ];
        $statistics = Statistics::where('date', $date)->find();
        if ($statistics) {
            $statistics->save($data);
        } else {
            Statistics::create($data);
        }
        $this->success($date . ' 的统计报表生成成功');
    }

    public function reportStatisticsTotal()
    {
        $mainCoinId = get_sys_config('main_coin');
        $activityTypes = ['check_in_reward', 'first_contract_amount_reached', 'month_invite_reached_give',
            'week_invite_reached_give', 'today_invite_reached_give', 'month_contract_amount_reached',
            'today_contract_amount_reached', 'today_contract_num_reached', 'team_num_reached_give',
            'invite_num_reached_give', 'today_recharge_reached_give', 'first_recharge_reached_give',
            'auth_give', 'u_recharge', 'invite_first_recharge', 'invite_register_reward', 'register_reward'];
        $whitelistUserIds = User::where('is_whitelist', 1)->column('id');
        $noWhitelistUserQuery = function (Query $query) use ($whitelistUserIds) {
            $query->where('user_id', 'not in', $whitelistUserIds);
        };
        $regNums = User::count();
        $minersIncome = CoinChange::where($noWhitelistUserQuery)->where('type', 'miners_income')->where('coin_id', $mainCoinId)->sum('amount');
        $managementIncome = CoinChange::where($noWhitelistUserQuery)->where('type', 'management_income')->where('coin_id', $mainCoinId)->sum('amount');
        $income = bcadd($minersIncome, $managementIncome, 2);
        $minersConsumption = Order::where('settlement_coin_id', $mainCoinId)->where($noWhitelistUserQuery)->sum('total_price');
        $managementConsumption = ManagementOrder::where('settlement_coin_id', $mainCoinId)->where($noWhitelistUserQuery)->sum('total_price');
        $consumption = bcadd($minersConsumption, $managementConsumption, 2);
        $rechargeMoney = FinancialRecharge::where('status', 1)->where($noWhitelistUserQuery)->sum('amount');
        $rechargeCoin = Recharge::where($noWhitelistUserQuery)->sum('amount');
        $withdraw = Withdraw::where($noWhitelistUserQuery)->where('status', 'in', '1,3')->where('type', 0)->sum('money');
        $withdrawCoin = Withdraw::where($noWhitelistUserQuery)->where('status', 'in', '1,3')->where('type', 1)->sum('coin_num');
        $rebate = CommissionChange::where($noWhitelistUserQuery)->where('type', 'margin_reward')->sum('amount');
        $activity = CoinChange::where($noWhitelistUserQuery)->where('type', 'in', $activityTypes)->sum('amount');
        $withdrawFee = Withdraw::where($noWhitelistUserQuery)->where('status', 'in', '1,3')->sum('fee_coin');
        $fee = $withdrawFee;
        $payment = ContractOrder::where($noWhitelistUserQuery)->where('payment_status', 1)->sum('income');
        $payment = abs($payment);
        $bonus = 0;
        $minersProduce = CoinChange::where($noWhitelistUserQuery)->where('type', 'miners_income')->where('coin_id', $mainCoinId)->sum('amount');
        $data = [
            'reg_nums' => $regNums,
            'income' => $income,
            'consumption' => $consumption,
            'recharge_money' => $rechargeMoney,
            'recharge_coin' => $rechargeCoin,
            'withdraw' => $withdraw,
            'withdrawCoin' => $withdrawCoin,
            'rebate' => $rebate,
            'activity' => $activity,
            'fee' => $fee,
            'payment' => $payment,
            'bonus' => $bonus,
            'miners_produce' => $minersProduce,
        ];
        RedisUtil::set(RedisKey::REPORT_STATISTICS_TOTAL, $data);
        $this->success('总统计报表生成成功');
    }

    public function reportTeamStatistics()
    {
        $users = User::select();
        $mainCoinId = get_sys_config('main_coin');
        $whitelistUserIds = User::where('is_whitelist', 1)->column('id');
        foreach ($users as $user) {
            $userId = $user->id;
            $childIds = Db::query('select queryChildrenUsers(:refereeid) as childIds', ['refereeid' => $userId])[0]['childIds'];
            $childIds = explode(',', $childIds);
            $childIds = array_diff($childIds, $whitelistUserIds);
            $teamQuery = function (Query $query) use ($childIds) {
                $query->where('user_id', 'in', $childIds);
            };
            $assets = Assets::mainCoinAssets($userId);
            $balance = $assets->balance;
            $teamBalance = Assets::where($teamQuery)->where('coin_id', $mainCoinId)->sum('balance');
            $refereeNums = $user->referee_nums;
            $totalRefereeNums = User::where('refereeid', $userId)->where('id', 'not in', $whitelistUserIds)->count();
            $refereeNums = '总 ' . $totalRefereeNums . ' / ' . $refereeNums;
            $teamNums = $user->team_nums;
            $totalTeamNums = count($childIds);
            $teamNums = '总 ' . $totalTeamNums . ' / ' . $teamNums;
            $todayRechargeMoney = FinancialRecharge::where(['user_id' => $userId, 'status' => 1])->whereDay('create_time')->sum('amount');
            $totalRechargeMoney = FinancialRecharge::where(['user_id' => $userId, 'status' => 1])->sum('amount');
            $todayTeamRechargeCoin = Recharge::where($teamQuery)->whereDay('create_time')->sum('amount');
            $totalTeamRechargeCoin = Recharge::where($teamQuery)->sum('amount');
            $todayTeamRechargeMoney = FinancialRecharge::where($teamQuery)->where('status', 1)->whereDay('create_time')->sum('amount');
            $teamRechargeMoney = FinancialRecharge::where($teamQuery)->where('status', 1)->sum('amount');
            $teamWithdraw = Withdraw::where($teamQuery)->where('status', 'in', '1,3')->where('type', 0)->sum('money');
            $teamLeftWithdraw = Withdraw::where($teamQuery)->where('status', 'in', '0,4')->where('type', 0)->sum('money');
            $teamWithdrawCoin = Withdraw::where($teamQuery)->where('status', 'in', '1,3')->where('type', 1)->sum('coin_num');
            $teamLeftWithdrawCoin = Withdraw::where($teamQuery)->where('status', 'in', '0,4')->where('type', 1)->sum('coin_num');
            $data = [
                'user_id' => $userId,
                'balance' => $balance,
                'team_balance' => $teamBalance,
                'referee_nums' => $refereeNums,
                'team_nums' => $teamNums,
                'today_recharge_money' => $todayRechargeMoney,
                'total_recharge_money' => $totalRechargeMoney,
                'today_team_recharge_coin' => $todayTeamRechargeCoin,
                'total_team_recharge_coin' => $totalTeamRechargeCoin,
                'today_team_recharge_money' => $todayTeamRechargeMoney,
                'team_recharge_money' => $teamRechargeMoney,
                'team_withdraw' => $teamWithdraw,
                'team_left_withdraw' => $teamLeftWithdraw,
                'team_withdraw_coin' => $teamWithdrawCoin,
                'team_left_withdraw_coin' => $teamLeftWithdrawCoin,
            ];
            $teamStatistics = TeamStatistics::where('user_id', $userId)->find();
            if ($teamStatistics) {
                $teamStatistics->save($data);
            } else {
                TeamStatistics::create($data);
            }
        }
        $this->success('团队报表的数据生成完毕');
    }

    public function reportTeamStatisticsTotal()
    {
        $mainCoinId = get_sys_config('main_coin');
        $whitelistUserIds = User::where('is_whitelist', 1)->column('id');
        $noWhitelistUserQuery = function (Query $query) use ($whitelistUserIds) {
            $query->where('user_id', 'not in', $whitelistUserIds);
        };
        $todayRegNums = User::whereDay('create_time')->where('id', 'not in', $whitelistUserIds)->count();
        $totalRegNums = User::count();
        $todayRechargeCoin = Recharge::where($noWhitelistUserQuery)->whereDay('create_time')->sum('amount');
        $todayRechargeMoney = FinancialRecharge::where($noWhitelistUserQuery)->where('status', 1)->whereDay('create_time')->sum('amount');
        $totalWithdrawMoney = Withdraw::where($noWhitelistUserQuery)->where('status', 'in', '1,3')->where('type', 0)->sum('money');
        $leftWithdrawMoney = Withdraw::where($noWhitelistUserQuery)->where('status', 'in', '0,4')->where('type', 0)->sum('money');
        $totalWithdrawCoin = Withdraw::where($noWhitelistUserQuery)->where('status', 'in', '1,3')->where('type', 1)->sum('coin_num');
        $leftWithdrawCoin = Withdraw::where($noWhitelistUserQuery)->where('status', 'in', '0,4')->where('type', 1)->sum('coin_num');
        $totalBalance = Assets::where($noWhitelistUserQuery)->where('coin_id', $mainCoinId)->sum('balance');
        $totalRechargeCoin = Recharge::where($noWhitelistUserQuery)->sum('amount');
        $totalRechargeMoney = FinancialRecharge::where($noWhitelistUserQuery)->where('status', 1)->sum('amount');;
        $data = [
            'todayRegNums' => $todayRegNums,
            'totalRegNums' => $totalRegNums,
            'todayRechargeCoin' => $todayRechargeCoin,
            'todayRechargeMoney' => $todayRechargeMoney,
            'totalWithdrawMoney' => $totalWithdrawMoney,
            'leftWithdrawMoney' => $leftWithdrawMoney,
            'totalWithdrawCoin' => $totalWithdrawCoin,
            'leftWithdrawCoin' => $leftWithdrawCoin,
            'totalBalance' => $totalBalance,
            'totalRechargeCoin' => $totalRechargeCoin,
            'totalRechargeMoney' => $totalRechargeMoney,
        ];
        RedisUtil::set(RedisKey::REPORT_TEAM_STATISTICS_TOTAL, $data);
        $this->success('团队总的报表数据生成成功');
    }


}
