<?php

namespace app\custom\command;

use app\custom\library\BinanceUtil;
use Lin\Binance\Binance;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Config;

/**
 * UTC K线
 */
class BinanceKlineClient extends Command
{
    protected function configure()
    {
        $this->setName('binance_kline_client')->setDescription('binance kline websocket client');
    }

    protected function execute(Input $input, Output $output)
    {
        $binance = BinanceUtil::getBinanceWs();
        $redisClient = BinanceUtil::getBinanceRedis();
        $intervals = ['1m', '5m', '15m', '30m', '1h', '4h', '1d', '1w', '1M'];
        $subscribeArray = [];
        $klineTypeList = Config::get('binance.coin_klinetype');
        foreach ($klineTypeList as $klineType) {
            $klineType = strtolower(str_replace('/', '', $klineType));
            foreach ($intervals as $interval) {
                $subscribeArray[] = $klineType . '@kline_' . $interval;
                $redisKey = $klineType . '_' . $interval;
                $redisClient->del($redisKey);
            }
        }
        if ($subscribeArray) {
//            $binance->unsubscribe($subscribeArray);
            $binance->subscribe($subscribeArray);
        }
        while (true) {
            pcntl_alarm(0);
            foreach ($klineTypeList as $klineType) {
                $klineType = strtolower(str_replace('/', '', $klineType));
                foreach ($intervals as $interval) {
                    $redisKey = $klineType . '_' . $interval;
                    $subscribe = $klineType . '@kline_' . $interval;
                    $data = $binance->getSubscribe([$subscribe]);
                    $klineData = isset($data[$subscribe]) ? $data[$subscribe]['data']['k'] : [];
                    if ($klineData) {
                        $redisKlineData = [
                            $klineData['t'],
                            $klineData['o'],
                            $klineData['h'],
                            $klineData['l'],
                            $klineData['c'],
                            $klineData['v'],
                            $klineData['T'],
                            $klineData['q'],
                            $klineData['n'],
                            $klineData['V'],
                            $klineData['Q'],
                            '0'
                        ];
                        $kline = json_decode($redisClient->get($redisKey) ?? '[]', true);
                        if (!$klineData['x']) {
                            array_pop($kline);
                        }
                        array_push($kline, $redisKlineData);
                        $klineCount = count($kline);
                        if ($klineCount < 300) {
                            $minusCount = 300 - $klineCount;
                            $historyKlines = $this->getKlines($klineType, $interval, $minusCount);
                            $kline = array_merge($historyKlines, $kline);
                        } else if ($klineCount > 300) {
                            $minusCount = $klineCount - 300;
                            array_splice($kline, 0, $minusCount);
                        }
                        $redisClient->set($redisKey, json_encode($kline, true));
                    }
                }
            }
            sleep(1);
        }
    }

    private function getKlines($klineType, $interval, $limit): array
    {
        $klineType = strtoupper($klineType);
        $binance = new Binance();
        return $binance->system()->getKlines(['symbol' => $klineType, 'interval' => $interval, 'limit' => $limit]);
    }

}
