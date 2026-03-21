<?php

use App\Jobs\SendMonthlySummaryJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send monthly budget summary email on the user's budget_start_day.
// The job runs daily and self-filters: it only sends the email when
// today matches a user's effective budget start day.
Schedule::job(new SendMonthlySummaryJob)->daily()->name('monthly-summary');
