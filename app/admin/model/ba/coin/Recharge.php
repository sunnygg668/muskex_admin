<?php

namespace app\admin\model\ba\coin;

use think\Model;

/**
 * Recharge
 */
class Recharge extends Model
{
    // 表名
    protected $name = 'coin_recharge';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;


    public function getAmountAttr($value): float
    {
        return (float)$value;
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }
}