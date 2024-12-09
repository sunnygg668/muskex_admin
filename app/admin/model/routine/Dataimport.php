<?php

namespace app\admin\model\routine;

use think\Model;
use app\admin\model\Admin;
use think\model\relation\BelongsTo;

/**
 * Dataimport
 */
class Dataimport extends Model
{
    // 表名
    protected $name = 'dataimport';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $updateTime         = false;


    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }
}