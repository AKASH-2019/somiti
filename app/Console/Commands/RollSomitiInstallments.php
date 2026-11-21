<?php

namespace App\Console\Commands;

use App\Services\Somiti\SomitiScheduleService;
use Illuminate\Console\Command;

class RollSomitiInstallments extends Command
{
    protected $signature = 'somiti:roll';

    protected $description = 'Create today\'s Somiti daily deposit rows for every active account';

    public function handle(SomitiScheduleService $schedule): int
    {
        $created = $schedule->rollAll(today(), force: true);
        $this->info("Created {$created} installment rows.");

        return self::SUCCESS;
    }
}
