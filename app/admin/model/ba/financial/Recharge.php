<?php

namespace app\admin\model\ba\financial;

use think\Model;

/**
 * Recharge
 */
class Recharge extends Model
{
    // 表名
    protected $name = 'financial_recharge';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    protected $append = ['status_text'];

    public function getStatusTextAttr($value, $data): string
    {
        return __('status ' . $data['status']);
    }

    public function getAmountAttr($value): float
    {
        return (float)$value;
    }

    public function getFeeRatioAttr($value): float
    {
        return (float)$value;
    }

    public function getFeeAttr($value): float
    {
        return (float)$value;
    }

    public function getActualMoneyAttr($value): float
    {
        return (float)$value;
    }

    public function getMainCoinNumAttr($value): float
    {
        return (float)$value;
    }

    public function getCoinPriceAttr($value): float
    {
        return (float)$value;
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }

    public function financialPaymentMethod(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\financial\PaymentMethod::class, 'financial_payment_method_id', 'id');
    }
}