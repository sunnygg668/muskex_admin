<?php

namespace app\admin\model\ba\user;

use think\Model;

/**
 * CoinChange
 */
class CoinChange extends Model
{
    // 表名
    protected $name = 'user_coin_change';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;


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

    public function coin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\coin\Coin::class, 'coin_id', 'id');
    }

    public function coinChangeTypes(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\user\CoinChangeTypes::class, 'type', 'type_key');
    }

    public function fromUser(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'from_user_id', 'id');
    }

    public function toUser(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'to_user_id', 'id');
    }

    public static function getRecordFrom($userId, $fromUserId, $type): ?static
    {
        $coinChange = static::where([
            'user_id' => $userId,
            'from_user_id' => $fromUserId,
            'type' => $type
        ])->find();

        return $coinChange;
    }

    public static function getRecordTo($userId, $toUserId, $type): ?static
    {
        $coinChange = static::where([
            'user_id' => $userId,
            'to_user_id' => $toUserId,
            'type' => $type
        ])->find();

        return $coinChange;
    }
}