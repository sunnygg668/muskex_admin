<?php

namespace app\api\controller;

use app\admin\model\ba\coin\Coin;
use app\admin\model\ba\user\Assets;
use app\common\controller\Frontend;
use app\common\library\RedisKey;
use app\custom\library\BinanceUtil;
use app\custom\library\QrCode;
use app\custom\library\RedisUtil;
use think\db\Query;
use think\facade\Cache;

class UserAssets extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function mainCoinAssets(): void
    {
        $userId = $this->auth->id;
        $assets = Assets::mainCoinAssets($userId);
        $subDirName = $assets['coin']['name'] . '_address';
        $fileName = QrCode::generate($assets['address'], $subDirName, $assets['address']);
        $assets['qrcode'] = $fileName;
        $assets['qrcode_url'] = get_sys_config('upload_cdn_url') . $fileName;
//        $assets['qrcode'] = '';
//        $assets['qrcode_url'] = '';
        $uRechargeTip = get_sys_config('u_recharge_tip');
        $result = [
            'assets' => $assets,
            'uRechargeTip' => $uRechargeTip
        ];
        $this->success('', $result);
    }

    public function assetsInfo()
    {
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $mainCoinAssets = Assets::mainCoinAssets($userId);
        $mainCoinPrice = Assets::mainCoinPrice();
        $assetsList = Assets::with(['coin' => function (Query $query) {
            $query->field('id, name, logo_image, kline_type');
        }])
            ->where(['user_id' => $userId])
            /* ->where(function ($query) use ($mainCoinAssets) {
                 $query->where('balance', '>', 0)->whereOr('coin_id', $mainCoinAssets->coin_id);
             })*/
            ->select();
//        $client = BinanceUtil::getBinanceRedis();
//        $ticker = json_decode($client->get('ticker'), true);
//        foreach ($assetsList as $assets) {
//            if ($assets->id == $mainCoinAssets->id) {
//                $assets['price'] = $mainCoinPrice;
//            } else {
//                if (isset($assets->coin['kline_type'])) {
//                    $key = strtolower(str_replace('/', '', $assets->coin['kline_type'])) . '@ticker';
//                    if (isset($ticker[$key]['data']['c'])) {
//                        $price = $ticker[$key]['data']['c'];
//                        $assets['price'] = bcmul($mainCoinPrice, $price, 2);
//                    }
//                }
//
//            }
//        }

        $coinList = RedisUtil::remember(RedisKey::COIN_ASSETS_LIST, function() {
            return Coin::field('id, alias, logo_image, kline_type, margin, name')
                ->order('margin desc')
                ->select();
        }, 600);

        if ($coinList) {
            $assets = [];
            foreach ($assetsList as &$asset) {
                $assets[$asset['coin_id']] = $asset;
            }
            $assetsList = [];
            $assetsList[] = $assets[1];
            foreach ($coinList as &$coin) {
                if (isset($assets[$coin['id']]) && $assets[$coin['id']]->coin_id != 1) {
                    $assetsList[] = $assets[$coin['id']];
                }
            }
        }
        $mainCoinAssets['balance'] = bcadd($mainCoinAssets['balance'], $user->money, 2);
        $result = [
            'mainCoinAssets' => $mainCoinAssets,
            'mainCoinPrice' => $mainCoinPrice,
            'assetsList' => $assetsList,
        ];
        $this->success('', $result);
    }

}
