<?php

namespace app\custom\listener;

use EasySwoole\FastDb\Config;
use EasySwoole\FastDb\FastDb;

class EasySwooleDbRegister
{
    public function handle(): void
    {
        $config = new Config([
            'name'              => 'default',
            'host'              => '127.0.0.1',
            'user'              => 'root',
            'password'          => '123456',
            'database'          => 'muskex',
            'port'              => 3306,
            'timeout'           => 5,
            'charset'           => 'utf8mb4',
            'autoPing'          => 5,
            'useMysqli'         => true,
            'intervalCheckTime' => 15 * 1000,
            'maxIdleTime'       => 100,
            'maxObjectNum'      => 300,
            'minObjectNum'      => 200,
            'getObjectTimeout'  => 3.0,
            'loadAverageTime'   => 0.001,
        ]);
        FastDb::getInstance()->addDb($config);
    }
}