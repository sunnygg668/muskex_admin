<?php

namespace app\admin\model\ba\coin;

use think\facade\Cache;
use think\Model;

/**
 * Contract
 */
class Contract extends Model
{
    // 表名
    protected $name = 'coin_contract';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public static string $cacheTag = 'contract';

//    public static function onAfterWrite(Model $model): void
//    {
//        Cache::tag(self::$cacheTag)->set($model->coin_id . '_coin', null);
//    }

    public function getProfitRatioUpAttr($value): float
    {
        return (float)$value;
    }

    public function getProfitRatioDownAttr($value): float
    {
        return (float)$value;
    }

    public function getPurchaseUpAttr($value): float
    {
        return (float)$value;
    }

    public function getPurchaseDownAttr($value): float
    {
        return (float)$value;
    }

    public function getLossRatioUpAttr($value): float
    {
        return (float)$value;
    }

    public function getLossRatioDownAttr($value): float
    {
        return (float)$value;
    }

    public function getFeeRatioAttr($value): float
    {
        return (float)$value;
    }

    public function coin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'coin_id', 'id');
    }
}
