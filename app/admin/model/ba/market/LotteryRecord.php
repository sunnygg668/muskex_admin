<?php

namespace app\admin\model\ba\market;

use think\Model;

/**
 * LotteryRecord
 */
class LotteryRecord extends Model
{
    // 表名
    protected $name = 'market_lottery_record';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;


    public function getAmountAttr($value): float
    {
        return (float)$value;
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }

    public function marketLotteryAward(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\market\LotteryAward::class, 'market_lottery_award_id', 'id');
    }

    public function coin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'coin_id', 'id');
    }
}