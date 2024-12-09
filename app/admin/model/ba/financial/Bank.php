<?php

namespace app\admin\model\ba\financial;

use think\Model;

/**
 * Bank
 */
class Bank extends Model
{
    // 表名
    protected $name = 'financial_bank';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function getLogoAttr($value): string
    {
        return !$value ? '' : get_sys_config('upload_cdn_url') . $value;
    }

}
