<?php

/*
 * This file is part of the overtrue/easy-sms.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\library;

use Overtrue\EasySms\Contracts\MessageInterface;
use Overtrue\EasySms\Contracts\PhoneNumberInterface;
use Overtrue\EasySms\Support\Config;

/**
 * Class EasySms.
 */
class CustomSms
{
    protected $config;

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }


    public function send($to, $message)
    {
        $gateway= ucfirst($this->config['default']['gateways'][0]);
        $className = "\\app\\common\\library\\sms\\{$gateway}Gateway";
        $class = new $className($this->config);

        $result = $class->send($to, $message,$this->config);
        return $result;
    }
}
