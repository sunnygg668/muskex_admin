<?php

namespace app\custom\command;

use app\custom\library\BinanceUtil;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Config;

/**
 * 按Symbol的完整Ticker
 */
class BinanceTickerClient extends Command
{
    protected function configure()
    {
        $this->setName('binance_ticker_client')->setDescription('binance ticker websocket client');
    }

    protected function execute(Input $input, Output $output)
    {
        $binance = BinanceUtil::getBinanceWs();
        $redisClient = BinanceUtil::getBinanceRedis();
        $subscribeArray = [];
        $klineTypeList = Config::get('binance.coin_klinetype');
        foreach ($klineTypeList as $klineType) {
            $klineType = strtolower(str_replace('/', '', $klineType));
            $subscribeArray[] = $klineType . '@ticker';
        }
        if ($subscribeArray) {
//            $binance->unsubscribe($subscribeArray);
            $binance->subscribe($subscribeArray);
        }
        while (true) {
            pcntl_alarm(0);
            $data = $binance->getSubscribe($subscribeArray);
            $ticker = json_decode($redisClient->get('ticker') ?? '[]', true);
            $ticker = array_merge($ticker ?? [], $data);
            $redisClient->set('ticker', json_encode($ticker, true));
            sleep(1);
        }
    }

}
