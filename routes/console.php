<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:generate-demand-forecasts')
    ->dailyAt('01:30')
    ->withoutOverlapping();

Schedule::command('app:inventory-generate-alerts')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('app:send-notification-digests')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('trash:cleanup', ['--days' => 30])
    ->daily()
    ->withoutOverlapping();

Schedule::command('app:prune-operational-data')
    ->weeklyOn(0, '03:30')
    ->withoutOverlapping();
