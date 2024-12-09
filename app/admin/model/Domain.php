<?php

namespace app\admin\model;

use think\Model;

class Domain extends Model
{
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'team_leader_id', 'id');
    }
}
