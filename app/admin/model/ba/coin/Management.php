<?php

namespace app\admin\model\ba\coin;

use think\Model;

/**
 * Management
 */
class Management extends Model
{
    // 表名
    protected $name = 'coin_management';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'begin_time' => 'timestamp:Y-m-d H:i:s',
        'end_time'   => 'timestamp:Y-m-d H:i:s',
    ];

    protected $append = ['income_type_text'];

    public function getIncomeTypeTextAttr($value, $data): string
    {
        return __('income_type ' . $data['income_type']);
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

    public function getPriceAttr($value): float
    {
        return (float)$value;
    }

    public function getIncomeRatioAttr($value): float
    {
        return (float)$value;
    }

    public function settlementCoin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'settlement_coin_id', 'id');
    }

    public function incomeCoin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'income_coin_id', 'id');
    }
}