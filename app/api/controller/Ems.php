<?php

namespace app\api\controller;

use Throwable;
use ba\Captcha;
use ba\ClickCaptcha;
use think\facade\Validate;
use app\common\model\User;
use app\common\library\Email;
use app\common\controller\Frontend;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Ems extends Frontend
{
    protected array $noNeedLogin = ['send'];

    public function initialize(): void
    {
        parent::initialize();
    }
    public function send(): void
    {
        $params = $this->request->post(['email', 'event', 'captchaId', 'captchaInfo']);
        $mail   = new Email();
        if (!$mail->configured) {
            $this->error(__('Mail sending service unavailable'));
        }
        $validate = Validate::rule([
            'email'       => 'require|email',
            'event'       => 'require',
            'captchaId'   => 'require',
            'captchaInfo' => 'require'
        ])->message([
            'email'       => 'email format error',
            'event'       => 'Parameter error',
            'captchaId'   => 'Captcha error',
            'captchaInfo' => 'Captcha error'
        ]);
        if (!$validate->check($params)) {
            $this->error(__($validate->getError()));
        }
        $captchaObj   = new Captcha();
        $clickCaptcha = new ClickCaptcha();
        if (!$clickCaptcha->check($params['captchaId'], $params['captchaInfo'])) {
            $this->error(__('Captcha error'));
        }
        $captcha = $captchaObj->getCaptchaData($params['email'] . $params['event']);
        if ($captcha && time() - $captcha['create_time'] < 60) {
            $this->error(__('Frequent email sending'));
        }
        $userInfo = User::where('email', $params['email'])->find();
        if ($params['event'] == 'user_register' && $userInfo) {
            $this->error(__('Email has been registered, please log in directly'));
        } elseif ($params['event'] == 'user_change_email' && $userInfo) {
            $this->error(__('The email has been occupied'));
        } elseif (in_array($params['event'], ['user_retrieve_pwd', 'user_email_verify']) && !$userInfo) {
            $this->error(__('Email not registered'));
        }
        if ($params['event'] == 'user_email_verify') {
            if (!$this->auth->isLogin()) {
                $this->error(__('Please login first'));
            }
            if ($this->auth->email != $params['email']) {
                $this->error(__('Please use the account registration email to send the verification code'));
            }
            $password = $this->request->post('password');
            if ($this->auth->password != encrypt_password($password, $this->auth->salt)) {
                $this->error(__('Password error'));
            }
        }
        $code    = $captchaObj->create($params['email'] . $params['event']);
        $subject = __($params['event']) . '-' . get_sys_config('site_name');
        $body    = __('Your verification code is: %s', [$code]);
        try {
            $mail->isSMTP();
            $mail->addAddress($params['email']);
            $mail->isHTML();
            $mail->setSubject($subject);
            $mail->Body = $body;
            $mail->send();
        } catch (PHPMailerException) {
            $this->error($mail->ErrorInfo);
        }
        $this->success(__('Mail sent successfully~'));
    }
}