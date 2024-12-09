<?php

namespace app\custom\library;

use Udun\Dispatch\UdunDispatch;

class UDun
{
    protected static ?UdunDispatch $uDunDispatch = null;

    public static function uDunDispatch(): UdunDispatch
    {
        if (!static::$uDunDispatch) {
            static::$uDunDispatch = new UdunDispatch([
                'merchant_no' => get_sign_sys_config('udun_merchant_no')['udun_merchant_no']['value'] ?? '',
                'api_key' => get_sign_sys_config('udun_api_key')['udun_api_key']['value'] ?? '',
                'gateway_address' => get_sign_sys_config('udun_gateway_address')['udun_gateway_address']['value'] ?? '',
                'callUrl' => get_sign_sys_config('udun_callback_url')['udun_callback_url']['value'] ?? '',
                'debug' => false
            ]);
        }

        return static::$uDunDispatch;
    }

}
