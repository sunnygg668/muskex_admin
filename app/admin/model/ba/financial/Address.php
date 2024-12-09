<?php

namespace app\admin\model\ba\financial;

use think\Model;

/**
 * Address
 */
class Address extends Model
{
    // 表名
    protected $name = 'financial_address';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }
}