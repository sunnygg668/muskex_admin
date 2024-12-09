<?php

namespace app\custom\library;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode as QR;
use OSS\OssClient;

class QrCode
{
    public static function generate($content, $subDirName, $prefix): string
    {
        $subDir = '/storage/axex/qrcode/' . $subDirName . '/';
        $dir = app()->getRootPath() . 'public' . $subDir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $name = $prefix . '_qrcode.png';
        $realFilePath = $dir . $name;
        $fileUrl = ltrim($subDir . $name, '/');
        if (!file_exists($realFilePath)) {
            $qrCode = new QR($content);
            $qrCode->setEncoding('UTF-8');
            $qrCode->setSize(200);
            $qrCode->setMargin(10);
            $qrCode->setForegroundColor(self::changeColor('rgba(0, 0, 0, 0)'));
            $qrCode->setBackgroundColor(self::changeColor('rgba(255, 255, 255, 0)'));
            $qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::QUARTILE());
            $qrCode->writeFile($realFilePath);
            OssUtil::upload($fileUrl);
        }
        return '/' . $fileUrl;
    }

    private static function changeColor($str): array
    {
        $str = substr($str, 5, -1);
        $arr = explode(',', $str);
        return [
            'r' => $arr[0],
            'g' => $arr[1],
            'b' => $arr[2],
            'a' => $arr[3]
        ];
    }
}
