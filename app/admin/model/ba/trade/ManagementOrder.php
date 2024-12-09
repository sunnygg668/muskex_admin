<?php

namespace app\admin\model\ba\trade;

use app\admin\model\User;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * ManagementOrder
 */
class ManagementOrder extends Model
{
    // 表名
    protected $name = 'trade_management_order';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'expire_time' => 'timestamp:Y/m/d H:i:s',
    ];

    protected $append = ['status_text', 'income_type_text'];

    public function getStatusTextAttr($value, $data): string
    {
        return __('status ' . $data['status']);
    }

    public function getIncomeTypeTextAttr($value, $data): string
    {
        return __('income_type ' . $data['income_type']);
    }

    public function getPriceAttr($value): float
    {
        return (float)$value;
    }

    public function getTotalPriceAttr($value): float
    {
        return (float)$value;
    }

    public function getIncomeRatioAttr($value): float
    {
        return (float)$value;
    }

    public function getTotalIncomeAttr($value): float
    {
        return (float)$value;
    }

    public function getPaidIncomeAttr($value): float
    {
        return (float)$value;
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }

    public function coinManagement(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Management::class, 'coin_management_id', 'id');
    }

    public function settlementCoin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'settlement_coin_id', 'id');
    }

    public function incomeCoin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'income_coin_id', 'id');
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
