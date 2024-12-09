<?php

namespace app\custom\command;

use app\custom\library\BinanceUtil;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Config;

/**
 * 逐笔交易
 */
class BinanceTradeClient extends Command
{
    protected function configure()
    {
        $this->setName('binance_trade_client')->setDescription('binance trade websocket client');
    }

    protected function execute(Input $input, Output $output)
    {
        $binance = BinanceUtil::getBinanceWs();
        $redisClient = BinanceUtil::getBinanceRedis();
        $subscribeArray = [];
        $klineTypeList = Config::get('binance.coin_klinetype');
        foreach ($klineTypeList as $klineType) {
            $klineType = strtolower(str_replace('/', '', $klineType));
            $subscribeArray[] = $klineType . '@aggTrade';
        }
        if ($subscribeArray) {
//            $binance->unsubscribe($subscribeArray);
            $binance->subscribe($subscribeArray);
        }
        while (true) {
            pcntl_alarm(0);
            $tradeArray = $binance->getSubscribe($subscribeArray);
            $tradeArray = json_decode(json_encode($tradeArray), true);
            $existsTrade = json_decode($redisClient->get('trade') ?? '[]', true);
            foreach ($tradeArray as $key => $trade) {
                if (isset($existsTrade[$key])) {
                    $existsData = &$existsTrade[$key]['data'];
                    array_unshift($existsData, $trade['data']);
                    $existsData = array_slice($existsData, 0, 20);
                } else {
                    $existsTrade[$key] = $trade;
                }
            }
            $trade = json_decode($redisClient->get('trade') ?? '[]', true);
            $trade = array_merge($trade ?? [], $existsTrade);
            $redisClient->set('trade', json_encode($trade, true));
            sleep(1);
        }
    }

}
