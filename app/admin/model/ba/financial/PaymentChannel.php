<?php

namespace app\admin\model\ba\financial;

use think\Model;

/**
 * PaymentMethod
 */
class PaymentChannel extends Model
{
    // 表名
    protected $name = 'financial_payment_channel';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

}
