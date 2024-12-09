<?php

use ba\Filesystem;
use app\admin\library\module\Server;

if (!function_exists('get_account_verification_type')) {

    function get_account_verification_type(): array
    {
        $types = [];
        $sysMailConfig = get_sys_config('', 'mail');
        $configured    = true;
        foreach ($sysMailConfig as $item) {
            if (!$item) {
                $configured = false;
            }
        }
        if ($configured) {
            $types[] = 'email';
        }
        $sms = Server::getIni(Filesystem::fsFit(root_path() . 'modules/sms/'));
        if ($sms && $sms['state'] == 1) {
            $types[] = 'mobile';
        }

        return $types;
    }
}

if (!function_exists('match_wallet_address')) {

    function match_wallet_address($address): bool
    {
        $address = trim($address,' ');
        if (empty($address)) {
            return false;
        }
        $len = strlen($address);
        return $len == 42 || $len == 34;
    }
}
