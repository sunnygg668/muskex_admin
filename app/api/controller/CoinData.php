<?php

namespace app\api\controller;

use app\admin\model\ba\coin\Coin;
use app\common\controller\Frontend;
use app\common\library\RedisKey;
use app\custom\library\BinanceUtil;
use app\custom\library\RedisUtil;
use think\facade\Cache;

class CoinData extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }
    public function tickerInfo(): void
    {
        $klineType = $this->request->param('kline_type');
        $key = strtolower(str_replace('/', '', $klineType)) . '@ticker';
        $client = BinanceUtil::getBinanceRedis();
        $ticker = json_decode($client->get('ticker'), true);
        $tickerInfo = isset($ticker[$key]) ? $ticker[$key] : [];
        $this->success('', $tickerInfo);
    }
    public function ticker(): void
    {
        $orderBy = $this->request->param('orderBy', 'desc');
        $client = BinanceUtil::getBinanceRedis();
        $ticker = json_decode($client->get('ticker'), true);
        $coinList = json_decode($client->get('coinList') ?? '[]', true);
        if (!$coinList) {
            $coinList = Coin::where(['status' => '1'])->field('id,alias, logo_image, kline_type, margin')->order('margin ' . $orderBy)->select();
            $client->set('coinList', json_encode($coinList, true));
            if ($coinList) {
                $coinList = $coinList->toArray();
            }
        }
        $sort = $orderBy == 'desc' ? SORT_DESC : SORT_ASC;
        array_multisort(array_column($coinList,'margin'),$sort, $coinList);
        foreach ($coinList as &$coin) {
            $klineType = $coin['kline_type'];
            $klineType = strtolower(str_replace('/', '', $klineType)) . '@ticker';
            if (isset($ticker[$klineType])) {
                $coin['data'] = $ticker[$klineType]['data'];
            } else {
                $coin['data'] = [];
            }
        }
        $this->success('', array_values($coinList));
    }
    public function kline(): void
    {
        $interval = $this->request->param('interval');
        $klineType = $this->request->param('kline_type');
        $latestTime = $this->request->param('latestTime');
        $klineType = strtolower(str_replace('/', '', $klineType));
        $redisKey = $klineType . '_' . $interval;
        $client = BinanceUtil::getBinanceRedis();
        $klineData = $client->get($redisKey) ?? "[]";
        $klineData = json_decode($klineData, true);
        $klineDataLatest = [];
        if ($latestTime) {
            foreach ($klineData as $idx => $kline) {
                if ($kline[0] > $latestTime) {
                    $klineDataLatest = array_slice($klineData, $idx);
                    break;
                }
            }
        } else {
            $klineDataLatest = $klineData;
        }
        if (empty($klineDataLatest)) {
            $klineDataLatest = [];
        }
        $result = [
            'kline_data' => $klineDataLatest,
        ];
        $this->success('', $result);
    }
    public function depth(): void
    {
        $klineType = $this->request->param('kline_type');
        $klineType = strtolower(str_replace('/', '', $klineType));
        $client = BinanceUtil::getBinanceRedis();
        $depth = json_decode($client->get('depth'), true);
        $depthKey = $klineType . '@depth20';
        $result = [
            'depth_data' => isset($depth[$depthKey]) ? $depth[$depthKey]['data'] : [],
        ];
        $this->success('', $result);
    }
    public function trade(): void
    {
        $klineType = $this->request->param('kline_type');
        $klineType = strtolower(str_replace('/', '', $klineType));
        $client = BinanceUtil::getBinanceRedis();
        $trade = json_decode($client->get('trade'), true);
        $tradeKey = $klineType . '@aggTrade';
        $result = [
            'trade_data' => isset($trade[$tradeKey]) ? $trade[$tradeKey]['data'] : [],
        ];
        $this->success('', $result);
    }
    public function coinList()
    {
        $list = RedisUtil::remember(RedisKey::COIN_LIST, function() {
            return Coin::where(['status' => '1'])
                ->field('id, name, alias, logo_image, kline_type, margin')
                ->order('margin desc')
                ->select();
        }, 600);
//        $client = BinanceUtil::getBinanceRedis();
//        $ticker = json_decode($client->get('ticker'), true);
//        foreach ($list as $key => $coin) {
//            $klineType = $coin['kline_type'];
//            $klineType = strtolower(str_replace('/', '', $klineType)) . '@ticker';
//            if (!isset($ticker[$klineType])) {
//                $ticker[$klineType] = [];
//            }
//        }
//        array_multisort(array_column($list, 'margin'), SORT_DESC, $list);
        $result = ['list' => $list];
        $this->success('', $result);
    }
}
