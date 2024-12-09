<?php

namespace app\admin\model\ba\financial;

use app\admin\model\User;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * Withdraw
 */
class Withdraw extends Model
{
    // 表名
    protected $name = 'financial_withdraw';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    protected $append = ['status_text'];

    public function getStatusTextAttr($value, $data): string
    {
        return __('status ' . $data['status']);
    }

    public function getMoneyAttr($value): float
    {
        return (float)$value;
    }

    public function getCoinNumAttr($value): float
    {
        return (float)$value;
    }

    public function getPriceAttr($value): float
    {
        return (float)$value;
    }

    public function getFeeRatioAttr($value): float
    {
        return (float)$value;
    }

    public function getFeeMoneyAttr($value): float
    {
        return (float)$value;
    }

    public function getFeeCoinAttr($value): float
    {
        return (float)$value;
    }

    public function getActualMoneyAttr($value): float
    {
        return (float)$value;
    }

    public function getActualCoinAttr($value): float
    {
        return (float)$value;
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }

    public function coin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'coin_id', 'id');
    }

    public function financialCard(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\financial\Card::class, 'financial_card_id', 'id');
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