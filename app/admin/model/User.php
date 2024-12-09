<?php

namespace app\admin\model;

use app\admin\model\ba\user\CoinChange;
use app\admin\model\ba\user\CommissionChange;
use app\admin\model\ba\user\Level;
use app\admin\model\ba\user\ManagementChange;
use ba\Exception;
use ba\Random;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * User 模型
 */
class User extends Model
{
    protected $autoWriteTimestamp = true;

    public function getAvatarAttr($value): string
    {
        return full_url($value, false, config('buildadmin.default_avatar'));
    }

    public function setAvatarAttr($value): string
    {
        return $value == full_url('', false, config('buildadmin.default_avatar')) ? '' : $value;
    }

    public function getMoneyAttr($value): string
    {
        // return bcdiv($value, 100, 2);
        return $value;
    }

    public function setMoneyAttr($value): string
    {
        // return bcmul($value, 100, 2);
        return $value;
    }

    /**
     * 重置用户密码
     * @param int|string $uid         用户ID
     * @param string     $newPassword 新密码
     * @return int|User
     */
    public function resetPassword(int|string $uid, string $newPassword): int|User
    {
        $salt   = Random::build('alnum', 16);
        $passwd = encrypt_password($newPassword, $salt);

        $changePwdWithdrawalInterval = get_sys_config('change_pwd_withdrawal_interval');
        $limitWithdrawTime = strtotime('+' . $changePwdWithdrawalInterval . ' hour');
        return $this->where(['id' => $uid])->update(['password' => $passwd, 'salt' => $salt, 'limit_withdraw_time' => $limitWithdrawTime]);
    }

    /**
     * 重置用户密码
     * @param int|string $uid         用户ID
     * @param string     $newFundPassword 新安全密码
     * @return int|User
     */
    public function resetFundPassword(int|string $uid, string $newFundPassword): int|User
    {
        $salt   = Random::build('alnum', 16);
        $passwd = encrypt_password($newFundPassword, $salt);

        $changePwdWithdrawalInterval = get_sys_config('change_pwd_withdrawal_interval');
        $limitWithdrawTime = strtotime('+' . $changePwdWithdrawalInterval . ' hour');
        return $this->where(['id' => $uid])->update(['fund_password' => $passwd, 'fund_salt' => $salt, 'limit_withdraw_time' => $limitWithdrawTime]);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'group_id');
    }

    public function refereeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refereeid');
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level', 'level');
    }

    public static function updateCommission($userId, $amount, $type, $fromUserId = null, $remark = null)
    {
        $user = User::find($userId);
        if ($amount < 0 && $user->commission_pool < abs($amount)) {
            throw new Exception('佣金池余额不足');
        }

        $before = $user->commission_pool;
        $user->commission_pool += $amount;
        $user->save();

        // 保存佣金账变记录
        $commissionChange = [
            'user_id' => $userId,
            'amount' => $amount,
            'before' => $before,
            'after' => $user->commission_pool,
            'type' => $type,
            'from_user_id' => $fromUserId,
            'remark' => $remark,
        ];
        CommissionChange::create($commissionChange);
    }

    public static function updateManagement($userId, $amount, $type, $fromUserId = null, $remark = null)
    {
        $user = User::find($userId);
        if ($amount < 0 && $user->money < abs($amount)) {
            throw new Exception('理财钱包余额不足');
        }

        $before = $user->money;
        $user->money += $amount;
        $user->save();
        $mainCoin = get_sys_config('main_coin');

        // 保存理财钱包账变记录
        $managementChange = [
            'user_id' => $userId,
            'coin_id' => $mainCoin,
            'amount' => $amount,
            'before' => $before,
            'after' => $user->money,
            'type' => $type,
            'from_user_id' => $fromUserId,
            'remark' => $remark,
        ];
        ManagementChange::create($managementChange);
    }

}
