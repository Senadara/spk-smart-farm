<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

\Illuminate\Support\Facades\Schedule::command('iot:poll')->everyMinute();
\Illuminate\Support\Facades\Schedule::command('spk:evaluate-environment --notify --include-global')
    ->everyFiveMinutes()
    ->withoutOverlapping();
