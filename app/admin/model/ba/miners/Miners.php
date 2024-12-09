<?php

namespace app\admin\model\ba\miners;

use think\Model;

/**
 * Miners
 */
class Miners extends Model
{
    // 表名
    protected $name = 'miners';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

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

    public function getPriceAttr($value): float
    {
        return (float)$value;
    }

    public function settlementCoin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'settlement_coin_id', 'id');
    }

    public function produceCoin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'produce_coin_id', 'id');
    }
}