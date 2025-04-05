<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::command('attachments:cleanup --days=30')
    ->weekly()
    ->sundays()
    ->at('00:00')
    ->onOneServer()
    ->emailOutputOnFailure(config('app.admin_email'));
