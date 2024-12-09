<?php

namespace app\admin\model\ba\user;

use think\Model;

/**
 * CommissionChange
 */
class CommissionChange extends Model
{
    // 表名
    protected $name = 'user_commission_change';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function getAmountAttr($value): float
    {
        return (float)$value;
    }

    public function getBeforeAttr($value): float
    {
        return (float)$value;
    }

    public function getAfterAttr($value): float
    {
        return (float)$value;
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }

    public function fromUser(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'from_user_id', 'id');
    }

    public function coinChangeTypes(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\user\CoinChangeTypes::class, 'type', 'type_key');
    }
}