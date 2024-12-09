<?php

namespace app\api\validate;

use think\Validate;

class Account extends Validate
{
    protected $failException = true;

    protected $rule = [
        'username' => 'require|regex:^[a-zA-Z][a-zA-Z0-9_]{2,15}$|unique:user',
        'nickname' => 'require|chsDash',
        'birthday' => 'date',
        'email'    => 'require|email|unique:user',
        'mobile'   => 'require|mobile|unique:user',
        'password' => 'require|regex:^(?!.*[&<>"\'\n\r]).{6,32}$',
        'fund_password' => 'require|regex:^(?!.*[&<>"\'\n\r]).{6,32}$',
        'account'  => 'require',
        'captcha'  => 'require',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'edit'             => ['nickname', 'birthday'],
        'changePassword'   => ['password'],
        'retrievePassword' => ['account', 'captcha', 'password'],
//        'retrieveFundPassword' => ['account', 'captcha', 'fund_password'],
        'retrieveFundPassword' => ['fund_password', 'fund_password2'],
    ];

    public function __construct()
    {
        $this->field   = [
            'username' => __('username'),
            'nickname' => __('nickname'),
            'birthday' => __('birthday'),
            'email'    => __('email'),
            'mobile'   => __('mobile'),
            'password' => __('password'),
            'fund_password' => __('fund_password'),
        ];
        $this->message = array_merge($this->message, [
            'nickname.chsDash' => __('nicknameChsDash'),
            'password.regex'   => __('Please input correct password')
        ]);
        parent::__construct();
    }
}
