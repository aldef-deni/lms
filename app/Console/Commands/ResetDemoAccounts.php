<?php

namespace App\Console\Commands;

use App\Services\DemoResetService;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Console\Command;

class ResetDemoAccounts extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Reset isolated demo accounts and their data to the default demo state';

    public function handle(DemoResetService $resetter): int
    {
        $this->info('Resetting demo-owned data...');
        $deleted = $resetter->reset();
        $this->call('db:seed', ['--class' => DemoAccountSeeder::class, '--force' => true]);
        $this->info('Demo accounts restored. Removed records: '.collect($deleted)->sum().'.');

        return self::SUCCESS;
    }
}
