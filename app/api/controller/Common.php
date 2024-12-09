<?php

namespace app\api\controller;

use app\admin\library\Auth as AdminAuth;
use app\common\controller\Api;
use app\common\facade\Token;
use app\common\library\Auth as UserAuth;
use ba\Captcha;
use ba\ClickCaptcha;
use ba\Random;
use think\facade\Config;
use think\Response;

class Common extends Api
{
    public function captcha(): Response
    {
        $captchaId = $this->request->request('id');
        $config    = array(
            'codeSet'  => '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXY',
            'fontSize' => 22,
            'useCurve' => false,
            'useNoise' => true,
            'length'   => 6,
            'bg'       => array(255, 255, 255),
        );
        $captcha = new Captcha($config);
        return $captcha->entry($captchaId);
    }

    public function checkCaptcha(): void
    {
        $id      = $this->request->post('id/s');
        $code    = $this->request->post('code/s');

        $captcha = new Captcha();
        if ($captcha->check($code,$id,false)) $this->success('验证成功');
        $this->error('图形验证码错误或已过期');
    }

    public function clickCaptcha(): void
    {
        $id      = $this->request->request('id/s');
        $captcha = new ClickCaptcha();
        $this->success('', $captcha->creat($id));
    }
    public function checkClickCaptcha(): void
    {
        $id      = $this->request->post('id/s');
        $info    = $this->request->post('info/s');
        $unset   = $this->request->post('unset/b', false);
        $captcha = new ClickCaptcha();
        if ($captcha->check($id, $info, $unset)) $this->success();
        $this->error();
    }
    public function refreshToken(): void
    {
        $refreshToken = $this->request->post('refreshToken');
        $refreshToken = Token::get($refreshToken);
        if (!$refreshToken || $refreshToken['expire_time'] < time()) {
            $this->error(__('Login expired, please login again.'));
        }
        $newToken = Random::uuid();
        if ($refreshToken['type'] == AdminAuth::TOKEN_TYPE . '-refresh') {
            $baToken = get_auth_token();
            if (!$baToken) {
                $this->error(__('Invalid token'));
            }
            Token::delete($baToken);
            Token::set($newToken, AdminAuth::TOKEN_TYPE, $refreshToken['user_id'], (int)Config::get('buildadmin.admin_token_keep_time'));
        }
        if ($refreshToken['type'] == UserAuth::TOKEN_TYPE . '-refresh') {
            $baUserToken = get_auth_token(['ba', 'user', 'token']);
            if (!$baUserToken) {
                $this->error(__('Invalid token'));
            }
            Token::delete($baUserToken);
            Token::set($newToken, UserAuth::TOKEN_TYPE, $refreshToken['user_id'], (int)Config::get('buildadmin.user_token_keep_time'));
        }
        $this->success('', [
            'type'  => $refreshToken['type'],
            'token' => $newToken
        ]);
    }
}
