<?php

namespace app\custom\command;

use app\custom\library\BinanceUtil;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Config;

/**
 * 归集交易
 */
class BinanceDepthClient extends Command
{
    protected function configure()
    {
        $this->setName('binance_depth_client')->setDescription('binance depth websocket client');
    }

    protected function execute(Input $input, Output $output)
    {
        $binance = BinanceUtil::getBinanceWs();
        $redisClient = BinanceUtil::getBinanceRedis();
        $subscribeArray = [];
        $klineTypeList = Config::get('binance.coin_klinetype');
        foreach ($klineTypeList as $klineType) {
            $klineType = strtolower(str_replace('/', '', $klineType));
            $subscribeArray[] = $klineType . '@depth20';
        }
        if ($subscribeArray) {
//            $binance->unsubscribe($subscribeArray);
            $binance->subscribe($subscribeArray);
        }
        while (true) {
            pcntl_alarm(0);
            $data = $binance->getSubscribe($subscribeArray);
            $depth = json_decode($redisClient->get('depth') ?? '[]', true);
            $depth = array_merge($depth ?? [], $data);
            $redisClient->set('depth', json_encode($depth, true));
            sleep(1);
        }
    }

}
