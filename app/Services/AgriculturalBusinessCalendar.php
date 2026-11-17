<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class AgriculturalBusinessCalendar
{
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('agriculture.business_timezone'))->startOfDay();
    }
}
