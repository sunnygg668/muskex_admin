<?php

namespace app\admin\model\ba\market;

use think\Model;

/**
 * LotteryAward
 */
class LotteryAward extends Model
{
    // 表名
    protected $name = 'market_lottery_award';

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

    public function getAmountDownAttr($value): float
    {
        return (float)$value;
    }

    public function getAmountUpAttr($value): float
    {
        return (float)$value;
    }

    public function coin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'coin_id', 'id');
    }
}