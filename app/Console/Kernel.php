<?php

namespace App\Console;

use App\Services\AdControllerService;
use App\Services\DealControllerService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            (new DealControllerService())->checkWaitingDeals();
        })->everyMinute();

        $schedule->call(function () {
            (new AdControllerService())->checkAdDeadline();
        })->everyMinute();

        $schedule->call(function () {
            (new DealControllerService())->checkDealsInProcess();
        })->everyTwoHours();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
