<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:generate-demand-forecasts')
    ->dailyAt('01:30')
    ->withoutOverlapping();

Schedule::command('app:inventory-generate-alerts')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('trash:cleanup', ['--days' => 30])
    ->daily()
    ->withoutOverlapping();
