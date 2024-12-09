<?php

namespace app\admin\model\ba\trade;

use app\admin\model\User;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * ContractOrder
 */
class ContractOrder extends Model
{
    // 表名
    protected $name = 'trade_contract_order';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 字段类型转换
    protected $type = [
        'buy_time'  => 'timestamp:Y-m-d H:i:s',
        'sell_time' => 'timestamp:Y-m-d H:i:s',
    ];

    protected $append = ['status_text', 'payment_status_text'];

    public function getStatusTextAttr($value, $data): string
    {
        return __('status ' . $data['status']);
    }

    public function getPaymentStatusTextAttr($value, $data): string
    {
        return __('payment_status ' . $data['payment_status']);
    }

    public function getNumAttr($value): float
    {
        return (float)$value;
    }

    public function getBuyPriceAttr($value): float
    {
        return (float)$value;
    }

    public function getInvestedCoinNumAttr($value): float
    {
        return (float)$value;
    }

    public function getFeeAttr($value): float
    {
        return (float)$value;
    }

    public function getFeeRatioAttr($value): float
    {
        return (float)$value;
    }

    public function getSellPriceAttr($value): float
    {
        return (float)$value;
    }

    public function getIncomeAttr($value): float
    {
        return (float)$value;
    }

    public function getIncomeRatioAttr($value): float
    {
        return (float)$value;
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }

    public function contract(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Contract::class, 'contract_id', 'id');
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