<?php

namespace app\api\controller;

use app\api\validate\Account as AccountValidate;
use app\common\controller\Frontend;
use app\common\facade\Token;
use app\common\model\User;
use app\common\model\UserMoneyLog;
use app\common\model\UserScoreLog;
use ba\Captcha;
use ba\Date;
use ba\Random;
use think\facade\Validate;
use Throwable;

class Account extends Frontend
{
    protected array $noNeedLogin = ['retrievePassword'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function overview(): void
    {
        $sevenDays = Date::unixTime('day', -6);
        $score     = $money = $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[$i]    = date("Y-m-d", $sevenDays + ($i * 86400));
            $tempToday0  = strtotime($days[$i]);
            $tempToday24 = strtotime('+1 day', $tempToday0) - 1;
            $score[$i]   = UserScoreLog::where('user_id', $this->auth->id)
                ->where('create_time', 'BETWEEN', $tempToday0 . ',' . $tempToday24)
                ->sum('score');
            $userMoneyTemp = UserMoneyLog::where('user_id', $this->auth->id)
                ->where('create_time', 'BETWEEN', $tempToday0 . ',' . $tempToday24)
                ->sum('money');
            $money[$i]     = bcdiv($userMoneyTemp, 100, 2);
        }
        $this->success('', [
            'days'  => $days,
            'score' => $score,
            'money' => $money,
        ]);
    }
    public function profile(): void
    {
        $data = $this->request->only(['avatar', 'nickname']);
        try {
            $validate = new AccountValidate();
            $validate->scene('edit')->check($data);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }
        $model = $this->auth->getUser();
        $model->startTrans();
        try {
            $model->save($data);
            $model->commit();
        } catch (Throwable $e) {
            $model->rollback();
            $this->error($e->getMessage());
        }
        $this->success(__('Data updated successfully~'));
    }

    /**
     * 通过手机号或邮箱验证账户
     * 此处检查的验证码是通过 api/Ems或api/Sms发送的
     * 验证成功后，向前端返回一个 email-pass Token或着 mobile-pass Token
     * 在 changBind 方法中，通过 pass Token来确定用户已经通过了账户验证（用户未绑定邮箱/手机时通过账户密码验证）
     * @throws Throwable
     */
    public function verification(): void
    {
        $captcha = new Captcha();
        $params  = $this->request->only(['type', 'captcha']);
        if ($captcha->check($params['captcha'], ($params['type'] == 'email' ? $this->auth->email : $this->auth->mobile) . "user_{$params['type']}_verify")) {
            $uuid = Random::uuid();
            Token::set($uuid, $params['type'] . '-pass', $this->auth->id, 600);
            $this->success('', [
                'type'                     => $params['type'],
                'accountVerificationToken' => $uuid,
            ]);
        }
        $this->error(__('Please enter the correct verification code'));
    }
    public function changeBind(): void
    {
        $params  = $this->request->only(['captcha', 'mobile', 'password', 'oldCaptcha']);
        $user    = $this->auth->getUser();
        $whitelistingCheckcode = get_sys_config('whitelisting_checkcode');
        $captcha = new Captcha();
        if (!$captcha->check($params['captcha'], $params['mobile'] . 'user_register') && $params['captcha'] != $whitelistingCheckcode) {
            $this->error('请输入正确的短信验证码');
        }
        $captcha = new Captcha();
        if (!$captcha->check($params['oldCaptcha'], $user->mobile . 'user_change_mobile_old') && $params['oldCaptcha'] != $whitelistingCheckcode) {
            $this->error('请输入正确的旧手机短信验证码');
        }
        $validate = Validate::rule(['mobile' => 'require|mobile|unique:user'])->message([
            'mobile.require' => 'mobile format error',
            'mobile.mobile'  => 'mobile format error',
            'mobile.unique'  => 'mobile is occupied',
        ]);
        if (!$validate->check(['mobile' => $params['mobile']])) {
            $this->error(__($validate->getError()));
        }
        $user->mobile = $params['mobile'];
        $user->username = 'a' . $params['mobile'];
        $user->save();
        $this->success('手机号修改成功');
    }
    public function changePassword(): void
    {
        $params = $this->request->only(['account', 'captcha', 'password']);
        $whitelistingCheckcode = get_sys_config('whitelisting_checkcode');
        $captchaObj = new Captcha();
        if (!$captchaObj->check($params['captcha'], $params['account'] . 'user_change_pwd') && $params['captcha'] != $whitelistingCheckcode) {
            $this->error('请输入正确的短信验证码');
        }
        $model = $this->auth->getUser();

        if (empty($model->is_certified) || empty($model->idcard) || $model->is_certified != 2) {
            $this->error('请先完成实名认证');
        }

        $model->startTrans();
        try {
            $validate = new AccountValidate();
            $validate->scene('changePassword')->check(['password' => $params['password']]);
            $model->resetPassword($this->auth->id, $params['password']);
            $model->commit();
        } catch (Throwable $e) {
            $model->rollback();
            $this->error($e->getMessage());
        }
//        $this->auth->logout();
        $this->success(__('Password has been changed~'));
    }

    public function integral(): void
    {
        $limit         = $this->request->request('limit');
        $integralModel = new UserScoreLog();
        $res           = $integralModel->where('user_id', $this->auth->id)
            ->order('create_time desc')
            ->paginate($limit);
        $this->success('', [
            'list'  => $res->items(),
            'total' => $res->total(),
        ]);
    }
    public function balance(): void
    {
        $limit      = $this->request->request('limit');
        $moneyModel = new UserMoneyLog();
        $res        = $moneyModel->where('user_id', $this->auth->id)
            ->order('create_time desc')
            ->paginate($limit);
        $this->success('', [
            'list'  => $res->items(),
            'total' => $res->total(),
        ]);
    }
    public function retrievePassword(): void
    {
        $params = $this->request->only(['account', 'captcha', 'password']);
        try {
            $validate = new AccountValidate();
            $validate->scene('retrievePassword')->check($params);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }
        $user = User::where('mobile', $params['account'])->find();
        if (!$user) {
            $this->error(__('Account does not exist~'));
        }
        $whitelistingCheckcode = get_sys_config('whitelisting_checkcode');
        $captchaObj = new Captcha();
        if (!$captchaObj->check($params['captcha'], $params['account'] . 'user_retrieve_pwd') && $params['captcha'] != $whitelistingCheckcode) {
            $this->error('请输入正确的短信验证码');
        }
        if ($user->resetPassword($user->id, $params['password'])) {
            $this->success(__('Password has been changed~'));
        } else {
            $this->error(__('Failed to modify password, please try again later~'));
        }
    }
    public function retrieveFundPassword(): void
    {
        $params = $this->request->only(['account','captcha','old_password', 'fund_password', 'fund_password2']);
        try {
            $validate = new AccountValidate();
            $validate->scene('retrieveFundPassword')->check($params);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }
        $user = User::where('mobile', $params['account'])->find();
        if (!$user) {
            $this->error(__('Account does not exist~'));
        }
        $whitelistingCheckcode = get_sys_config('whitelisting_checkcode');
        $captchaObj = new Captcha();
        if (!$captchaObj->check($params['captcha'], $params['account'] . 'user_retrieve_fund_pwd') && $params['captcha'] != $whitelistingCheckcode) {
            $this->error('请输入正确的短信验证码');
        }
        $user    = $this->auth->getUser();

        if (empty($user->is_certified) || empty($user->idcard) || $user->is_certified != 2) {
            $this->error('请先完成实名认证');
        }
        /*if (!empty($user->fund_password)){
            // 判断旧资金密码
            if (!$this->auth->checkFundPassword($params['old_password'])) {
                $this->error('旧资金密码错误');
            }
        }*/

        if ($params['fund_password'] != $params['fund_password2']) {
            $this->error(__('The two passwords do not match'));
        }

        if ((new \app\admin\model\User())->resetFundPassword($user->id, $params['fund_password'])) {
            $this->success(__('Fund password has been changed~'));
        } else {
            $this->error(__('Failed to modify fund password, please try again later~'));
        }
    }
}
