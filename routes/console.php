<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:inventory-generate-alerts')
    ->daily()
    ->withoutOverlapping();

Schedule::command('trash:cleanup', ['--days' => 30])
    ->daily()
    ->withoutOverlapping();
