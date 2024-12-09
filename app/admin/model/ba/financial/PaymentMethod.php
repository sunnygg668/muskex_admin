<?php

namespace app\admin\model\ba\financial;

use think\Model;

/**
 * PaymentMethod
 */
class PaymentMethod extends Model
{
    // 表名
    protected $name = 'financial_payment_method';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function channel(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\financial\PaymentChannel::class, 'channel_id', 'id');
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

    public function getMinAmountAttr($value): float
    {
        return (float)$value;
    }

    public function getMaxAmountAttr($value): float
    {
        return (float)$value;
    }

    public function getFeeRatioAttr($value): float
    {
        return (float)$value;
    }
}