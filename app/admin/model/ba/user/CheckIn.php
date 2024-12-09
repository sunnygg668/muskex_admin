<?php

namespace app\admin\model\ba\user;

use think\Model;

/**
 * CheckIn
 */
class CheckIn extends Model
{
    // 表名
    protected $name = 'user_check_in';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }
}