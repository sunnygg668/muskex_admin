<?php

namespace app\admin\model\ba\coin;

use think\facade\Cache;
use think\Model;

/**
 * Coin
 */
class Coin extends Model
{
    // 表名
    protected $name = 'coin';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public static string $cacheTag = 'coin';

    protected $append = [
        'logo_image',
    ];

    public function getLogoImageAttr($value, $data): string
    {
        return "https://image.tecajx.vip/images/{$data['name']}.png";
    }

    protected static function onAfterInsert($model)
    {
        if ($model->weigh == 0) {
            $pk = $model->getPk();
            if (strlen($model[$pk]) >= 19) {
                $model->where($pk, $model[$pk])->update(['weigh' => $model->count()]);
            } else {
                $model->where($pk, $model[$pk])->update(['weigh' => $model[$pk]]);
            }
        }
    }

//    public static function onAfterWrite(Model $model): void
//    {
//        Cache::tag(self::$cacheTag)->set($model->kline_type, null);
//    }

    public function getMarginAttr($value): float
    {
        return (float)$value;
    }

    public function getTransferRateAttr($value): float
    {
        return (float)$value;
    }

    public function getTransferMinNumAttr($value): float
    {
        return (float)$value;
    }

    /**
     * 获取主币种
     *
     * @return array|mixed
     * @throws \Throwable
     */
    public static function mainCoin()
    {
        $mainCoinId = get_sys_config('main_coin');
        return static::find($mainCoinId);
    }
}
