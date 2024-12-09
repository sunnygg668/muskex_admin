<?php

namespace app\admin\model\ba\user;

use app\custom\library\UDun;
use ba\Exception;
use think\db\Query;
use think\Model;

/**
 * Assets
 */
class Assets extends Model
{
    // 表名
    protected $name = 'user_assets';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function getBalanceAttr($value): float
    {
        return (float)$value;
    }

    public function getFreezeAttr($value): float
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

    /**
     * 获取主币种价格
     *
     * @return float
     */
    public static function mainCoinPrice(): float
    {
        return get_sys_config('main_coin_price') ?? 7.3;
    }

    /**
     * 获取主币种账户
     *
     * @param $userId
     * @return Assets
     */
    public static function mainCoinAssets($userId): ?Assets
    {
        $mainCoin = get_sys_config('main_coin');
        return static::coinAssets($userId, $mainCoin);
    }

    /**
     * 获取币种账户
     *
     * @param $userId
     * @param $coinId
     * @return Assets
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function coinAssets($userId, $coinId): ?Assets
    {
        return static::with(['coin' => function (Query $query) {
                $query->field('id, name, logo_image, kline_type');
            }])
            ->where(['user_id' => $userId, 'coin_id' => $coinId])
            ->find();
    }

    /**
     * 更新主币种账户余额
     *
     * @param $userId
     * @param $amount
     * @param $type
     * @return void
     * @throws Exception
     */
    public static function updateMainCoinAssetsBalance($userId, $amount, $type, $fromUserId = null): ?Assets
    {
        $mainCoin = get_sys_config('main_coin');
        return static::updateCoinAssetsBalance($userId, $mainCoin, $amount, $type, $fromUserId);
    }

    /**
     * 更新币种账户余额
     *
     * @param $userId
     * @param $coinId
     * @param $amount
     * @param $type
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function updateCoinAssetsBalance($userId, $coinId, $amount, $type, $fromUserId = null, $remark = null): ?Assets
    {
        $assets = static::coinAssets($userId, $coinId);
        if (!$assets) {
            $assets = [
                'user_id' => $userId,
                'coin_id' => $coinId,
            ];
            $assets = static::create($assets);
        }
        if ($amount == 0) {
            return $assets;
        }

        if ($amount < 0 && $assets->balance < abs($amount)) {
            throw new Exception('币种余额不足', 205);
        }

        $before = $assets->balance;
        $assets->balance += $amount;
        $assets->save();

        // 保存虚拟币账变记录
        $coinChange = [
            'user_id' => $userId,
            'coin_id' => $coinId,
            'amount' => $amount,
            'before' => $before,
            'after' => $assets->balance,
            'type' => $type,
            'from_user_id' => $fromUserId,
            'remark' => $remark
        ];
        CoinChange::create($coinChange);

        return $assets;
    }
}
