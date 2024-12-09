<?php

namespace app\custom\library;

use Lin\Binance\BinanceWebSocket;
use Predis\Client;
use think\facade\Config;

class BinanceUtil
{
    public static function getBinanceWs(): BinanceWebSocket
    {
        $binance = new BinanceWebSocket();
        $config = Config::get('binance.websocket');
        $binance->config($config);
        return $binance;
    }

    public static function getBinanceRedis(): Client
    {
        $config = Config::get('binance.redis');
        return new Client($config);
    }
}