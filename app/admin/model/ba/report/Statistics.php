<?php

namespace app\admin\model\ba\report;

use think\Model;

/**
 * Statistics
 */
class Statistics extends Model
{
    // 表名
    protected $name = 'report_statistics';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;


    public function getIncomeAttr($value): float
    {
        return (float)$value;
    }

    public function getConsumptionAttr($value): float
    {
        return (float)$value;
    }

    public function getRechargeCoinAttr($value): float
    {
        return (float)$value;
    }

    public function getManagementBuyAttr($value): float
    {
        return (float)$value;
    }

    public function getMinersBuyAttr($value): float
    {
        return (float)$value;
    }

    public function getWithdrawAttr($value): float
    {
        return (float)$value;
    }

    public function getRebateAttr($value): float
    {
        return (float)$value;
    }

    public function getActivityAttr($value): float
    {
        return (float)$value;
    }

    public function getFeeAttr($value): float
    {
        return (float)$value;
    }

    public function getPaymentAttr($value): float
    {
        return (float)$value;
    }

    public function getBonusAttr($value): float
    {
        return (float)$value;
    }

    public function getMinersProduceAttr($value): float
    {
        return (float)$value;
    }

    public function getManagementIncomeAttr($value): float
    {
        return (float)$value;
    }
}