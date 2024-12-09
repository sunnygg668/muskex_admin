<?php

namespace app\admin\model\ba\miners;

use think\Model;

/**
 * Exchange
 */
class Exchange extends Model
{
    // 表名
    protected $name = 'miners_exchange';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function getDiscountRatioAttr($value): float
    {
        return (float)$value;
    }

    public function miners(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\miners\Miners::class, 'miners_id', 'id');
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }
}