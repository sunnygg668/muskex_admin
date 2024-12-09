<?php

namespace app\admin\controller\auth;

use app\common\controller\Backend;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;
use app\admin\model\Admin as AdminModel;


class GoogleAuth extends Backend
{
    public function setupGoogleAuth()
    {
        // 生成一个密钥
        $g = new GoogleAuthenticator();
        $secret = $g->generateSecret();
        // 生成二维码 URL
        $qrCodeUrl = GoogleQrUrl::generate('udun', $secret, get_sys_config('site_name'));
        // 将密钥存储在数据库或用户 session 中
        if ($this->auth->username === 'admin') {
            $this->model = new AdminModel();
            $row = $this->model->find($this->auth->id);
            $row->save(['google_secret' => aes_encrypt($secret)]);
            $this->success('', [
                'secret'    => $secret,
                'qrCodeUrl' => $qrCodeUrl
            ]);
        } else {
            $this->error();
        }

    }
}
