 ### 启动币安脚本之前先启动：
 
 php binance_server.php start -d

 ### 币安的几个脚本：

nohup php think binance_ticker_client >> logs/binance_ticker_client-$(date +%Y%m).log 2>&1 &

nohup php think binance_depth_client >> logs/binance_depth_client-$(date +%Y%m).log 2>&1 &

nohup php think binance_trade_client >> logs/binance_trade_client-$(date +%Y%m).log 2>&1 &

nohup php think binance_kline_client >> logs/binance_kline_client-$(date +%Y%m).log 2>&1 &

 ### 会员等级计算脚本：

nohup php think user_level_calc >> logs/user_level_calc-$(date +%Y%m).log 2>&1 &

 ### 队列：
php think queue:listen --queue user &

php think queue:listen --queue reward &

php think queue:listen --queue task_reward &




 ### Task任务：


 1.超时的充值订单取消：*/5 * * * * php think action api/Task/rechargeOrderTimeout

 2.理财钱包收益按小时分发：0 */1 * * * php think action api/Task/managementWalletIncomeHours

 3.定期计算会员的激活状态：*/30 * * * * php think action api/Task/calcIsActivation

 4.分红奖励分发：55 23 * * * php think action api/Task/bonusAward

 5.矿机产出：0 */1 * * * php think action api/Task/minerOrderIncome

 6.定期理财结束，收益发放和本金返还：0 */1 * * * php think action api/Task/managementOrderIncome

 7.每日统计报表生成：0 */2 * * * php think action api/StatisticsTask/reportStatistics

 8.总统计报表生成：0 1 * * * php think action api/StatisticsTask/reportStatisticsTotal 

 9.团队报表的数据生成：0 */2 * * * php think action api/StatisticsTask/reportTeamStatistics

 10.团队总的报表数据生成：30 1 * * * php think action api/StatisticsTask/reportTeamStatisticsTotal

 11理财返佣按小时分发.：0 */1 * * * php think action api/Task/managementOrderRebateIncomeDay



 ### 数据库创建函数sql：
 
 use musk_ex2; /**应修改为对应数据库名称**/
 
CREATE DEFINER=\`admin\`@\`%\` FUNCTION \`queryParentUsers\`(userId BIGINT) RETURNS text CHARSET utf8mb4
BEGIN
	DECLARE
		sTemp text;
	WITH recursive t AS ( SELECT * FROM ba_user WHERE id = userId UNION ALL SELECT a.* FROM ba_user a JOIN t ON a.id = t.refereeid )
	SELECT
		group_concat( t.id ) INTO sTemp 
	FROM
		t;
	RETURN sTemp;

END


CREATE DEFINER=`admin`@`%` FUNCTION `queryChildrenUsers`(`rid` int) RETURNS text CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
BEGIN
	DECLARE
		result text;
	WITH recursive t AS ( SELECT id,refereeid FROM ba_user WHERE id = rid UNION ALL SELECT a.id,a.refereeid FROM ba_user a JOIN t ON a.refereeid = t.id )
	SELECT
		group_concat( t.id ) INTO result
	FROM
	t;
RETURN result;

END