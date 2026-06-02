<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:inventory-generate-alerts')
    ->daily()
    ->withoutOverlapping();
