<?php

namespace app\admin\model\ba\user;

use think\Model;

/**
 * TeamLevel
 */
class TeamLevel extends Model
{
    // 表名
    protected $name = 'user_team_level';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }

    public function userLevel(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\user\Level::class, 'user_level', 'level');
    }
}