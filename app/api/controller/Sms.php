<?php

namespace app\api\controller;

use app\common\library\RedisKey;
use app\common\model\BussinessLog;
use app\custom\library\RedisUtil;
use think\facade\Log;
use Throwable;
use ba\Captcha;
use ba\ClickCaptcha;
use think\facade\Event;
use app\common\model\User;
use modules\sms\Sms as smsLib;
use app\common\controller\Frontend;
use think\facade\Validate;

class Sms extends Frontend
{
    protected array $noNeedLogin = ['send'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function send()
    {
        $params   = $this->request->post(['mobile', 'template_code', 'captchaId', 'captcha']);
        $validate = Validate::rule([
            'mobile'        => 'require|mobile',
            'template_code' => 'require',
        ])->message([
            'mobile'        => 'Mobile format error',
            'template_code' => 'Parameter error',
        ]);
        if (!$validate->check($params)) {
            $this->error(__($validate->getError()));
        }

        $lockKey = RedisKey::SEND_SMS_LOCK . $params['mobile'].'_'.$params['template_code'];
        $is_lock = RedisUtil::set($lockKey, 1,'EX',20,'NX');
        if (!$is_lock) {
            $this->error('请勿频繁操作');
        }

        if (in_array($params['template_code'],['user_register','user_retrieve_pwd'])) {//用户注册，忘记登录密码  需传图形验证码
            $captchaObj = new Captcha();
            if (!$captchaObj->check($params['captcha'], $params['captchaId'])) {
                $this->error(__('Please enter the correct verification code'));
            }
        }else{//非注册必须登录
            if (!$this->auth->isLogin()) {
                $this->error(__('Please login first'));
            }
        }

        $userInfo = User::where('mobile', $params['mobile'])->find();
        if ($params['template_code'] == 'user_register' && $userInfo) {
            $this->error(__('Mobile number has been registered, please log in directly'));
        } elseif ($params['template_code'] == 'user_change_mobile' && $userInfo) {
            $this->error(__('The mobile number has been occupied'));
        } elseif (in_array($params['template_code'], ['user_retrieve_pwd', 'user_retrieve_fund_pwd', 'user_mobile_verify', 'user_change_mobile_old', 'user_login', 'user_change_pwd']) && !$userInfo) {
            $this->error(__('Mobile number not registered'));
        }

        if ($params['template_code'] == 'user_mobile_verify') {
            if ($this->auth->mobile != $params['mobile']) {
                $this->error(__('Please use the account registration mobile to send the verification code'));
            }
            $password = $this->request->post('password');
            if ($this->auth->password != encrypt_password($password, $this->auth->salt)) {
                $this->error(__('Password error'));
            }
        }
        Event::listen('TemplateAnalysisAfter', function ($templateData) use ($params) {
            // 存储验证码
            if (array_key_exists('code', $templateData['variables'])) {
                (new Captcha())->create($params['mobile'] . $params['template_code'], $templateData['variables']['code']);
            }
            if (array_key_exists('alnum', $templateData['variables'])) {
                (new Captcha())->create($params['mobile'] . $params['template_code'], $templateData['variables']['alnum']);
            }
        });

        //判断频率
        $phoneNumber = $params['mobile'];
        $limit = get_sys_config('sms_limit_num_hour'); // 允许的发送次数
        $interval = 3600; // 时间限制，这里是一个小时
        // 键名用于Redis中存储计数器的值
        $key = RedisKey::SMS_LIMIT.$phoneNumber;
        // 检查计数器是否存在，如果不存在，则设置初始值为0，并设置过期时间
        if (!RedisUtil::exists($key)) {
            RedisUtil::setEx($key,$interval, 0);
        }


        // 检查计数器的值是否超过限制
        $count = RedisUtil::getValue($key);
        if ($limit >0 && $count > $limit) {
            $this->error(__("短信发送次数超出限制。一小时内限制{$limit}次"));
        }

        try {
            smsLib::send($params['template_code'], $params['mobile']);

            // 增加计数器的值
            RedisUtil::incr($key);
        } catch (Throwable $e) {
            if (!env('APP_DEBUG', false)) {
                BussinessLog::record($e->getMessage());
                $this->error(__('Failed to send SMS. Please contact the website administrator'));
            } else {
                // throw new Exception($e->getMessage());
                BussinessLog::record($e->getMessage());
                $this->error(__($e->getMessage()));
            }
        }
        RedisUtil::del($lockKey); //释放锁
        $this->success(__('SMS sent successfully'));
    }
}
