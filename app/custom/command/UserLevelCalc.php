<?php

namespace app\custom\command;

use app\custom\model\BaUser;
use app\custom\model\BaUserLevel;
use app\custom\model\BaUserTeamLevel;
use EasySwoole\FastDb\FastDb;
use EasySwoole\Mysqli\QueryBuilder;
use Swoole\Coroutine;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Queue;
use function Swoole\Coroutine\run;

class UserLevelCalc extends Command
{
    protected function configure()
    {
        $this->setName('user_level_calc')->setDescription('user level calculate');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set("memory_limit",-1);
        run(function () use ($input, $output) {
            $config = new \EasySwoole\FastDb\Config([
                'name'              => 'default',    // 设置 连接池名称，默认为 default
                'host'              => env('database.hostname', '127.0.0.1'),  // 设置 数据库 host
                'user'              => env('database.username', 'root'), // 设置 数据库 用户名
                'password'          => env('database.password', '123456'), // 设置 数据库 用户密码
                'database'          => env('database.database', 'muskex'), // 设置 数据库库名
                'port'              => env('database.hostport', '3306'),         // 设置 数据库 端口
                'timeout'           => 5,            // 设置 数据库连接超时时间
                'charset'           => env('database.charset', 'utf8mb4'),       // 设置 数据库字符编码，默认为 utf8
                'autoPing'          => 5,            // 设置 自动 ping 客户端链接的间隔
                'useMysqli'         => true,        // 设置 不使用 php mysqli 扩展连接数据库
                // 配置 数据库 连接池配置，配置详细说明请看连接池组件 https://www.easyswoole.com/Components/Pool/introduction.html
                // 下面的参数可使用组件提供的默认值
                'intervalCheckTime' => 15 * 1000,    // 设置 连接池定时器执行频率
                'maxIdleTime'       => 10,           // 设置 连接池对象最大闲置时间 (秒)
                'maxObjectNum'      => 20,           // 设置 连接池最大数量
                'minObjectNum'      => 5,            // 设置 连接池最小数量
                'getObjectTimeout'  => 3.0,          // 设置 获取连接池的超时时间
                'loadAverageTime'   => 0.001,        // 设置 负载阈值
            ]);
            // 或使用对象设置属性方式进行配置
            // $config->setName('default');
            // $config->setHost('127.0.0.1');
            FastDb::getInstance()->addDb($config);
            //FastDb::getInstance()->testDb();

            while (true) {
                $levelList = BaUserLevel::findAll(function (QueryBuilder $query) {
                    $query->where('is_open', 1)->orderBy('level', 'desc');
                }, null, false);
                $userList = BaUser::findAll();
                $levelChangeList = [];
                FastDb::getInstance()->begin();
                try {
                    foreach ($userList as $user) {
                        foreach ($levelList as $level) {
                            $checkRefereeNums = $user->referee_nums >= $level->referee_num;
                            $checkTeamNums = $user->team_nums >= $level->team_num;
                            $checkMemberLevel = true;
                            if ($level->member_level && $level->member_level_num) {
                                $memberTeamLevel = BaUserTeamLevel::findRecord(['user_id' => $user->id, 'user_level' => $level->member_level]);
                                $checkMemberLevel = $memberTeamLevel->team_nums >= $level->member_level_num;
                            }
                            if ($checkRefereeNums && $checkTeamNums && $checkMemberLevel) {
                                if ($user->level == $level->level) {
                                    break;
                                }
                                $oldLevel = $user->level;
                                $user->level = $level->level;
                                $user->update();
                                $levelChangeList[] = [
                                    'user_id' => $user->id,
                                    'old_level' => $oldLevel
                                ];
                                break;
                            }
                        }
                    }
                    FastDb::getInstance()->commit();
                } catch (\Exception $e) {
                    FastDb::getInstance()->rollback();
                    unset($levelChangeList);
                    $output->writeln($level->name . ': ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
                }
                if (!empty($levelChangeList)) {
                    foreach ($levelChangeList as $levelChange) {
                        Queue::push('\app\custom\job\UserQueue@updateTeamLevelOnly', ['user_id' => $levelChange['user_id'], 'old_level' => $levelChange['old_level']], 'user');
                    }
                }
                $output->writeln(date('Y-m-d H:i:s') . ' 会员等级计算完成');
                sleep(600);
            }
        });
    }
}
