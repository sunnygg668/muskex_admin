<?php

namespace app\custom\library;

class NumberUtil
{
    public static function generateRand($m, $n): float
    {
        if ($m > $n) {
            $numMax = $m;
            $numMin = $n;
        } else {
            $numMax = $n;
            $numMin = $m;
        }
        $rand = $numMin + mt_rand() / mt_getrandmax() * ($numMax - $numMin);
        return floatval(number_format($rand, 2));
    }

    public static function bcAddBatch(array $numbers, int $scale = 2): string
    {
        if ($numbers && count($numbers) >= 2) {
            $return = '0';
            foreach ($numbers as $item) {
                $return = bcadd($item, $return, $scale);
            }
            return $return;
        }
        return bcadd('0', $numbers[0] ?? '0', $scale);
    }

}