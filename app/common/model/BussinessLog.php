<?php

namespace app\common\model;

use think\Model;
use think\facade\Log;

class BussinessLog extends Model
{
    protected $autoWriteTimestamp = true;

    public static function record($data,$type = 'error'){
        $data = !is_scalar($data) ? json_encode($data) : $data;
        Log::error($data);
        self::create([
            'url'       => substr(request()->url(), 0, 1500),
            'type'     => $type,
            'data'      => $data,
            'ip'        => get_client_ip(),
            'useragent' => substr(request()->server('HTTP_USER_AGENT'), 0, 255),
        ]);
    }

}
