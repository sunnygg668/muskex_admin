<?php

namespace app\admin\model\ba\financial;

use think\Model;

/**
 * Card
 */
class Card extends Model
{
    // 表名
    protected $name = 'financial_card';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    protected $append = ['status_text'];

    public function getStatusTextAttr($value, $data): string
    {
        return __('status ' . $data['status']);
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }

    public function financialBank(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\financial\Bank::class, 'financial_bank_id', 'id');
    }
}