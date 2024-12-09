<?php

namespace app\admin\model\ba\miners;

use app\admin\model\User;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * Order
 */
class Order extends Model
{
    // 表名
    protected $name = 'miners_order';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'expire_time' => 'timestamp:Y-m-d H:i:s',
    ];


    public function getPriceAttr($value): float
    {
        return (float)$value;
    }

    public function getTotalPriceAttr($value): float
    {
        return (float)$value;
    }

    public function getGainedIncomeAttr($value): float
    {
        return (float)$value;
    }

    public function getPendingIncomeAttr($value): float
    {
        return (float)$value;
    }

    public function getBonusAttr($value): float
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

    public function settlementCoin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'settlement_coin_id', 'id');
    }

    public function produceCoin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'produce_coin_id', 'id');
    }

    public function refereeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refereeid');
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }
}