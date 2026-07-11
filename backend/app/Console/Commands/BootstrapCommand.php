<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent container start-up: wait for the database, migrate, seed only
 * when the database is empty, and build the OpenAPI document.
 */
class BootstrapCommand extends Command
{
    protected $signature = 'menumind:bootstrap {--wait=60 : Seconds to wait for the database} {--no-seed : Never seed}';

    protected $description = 'Prepare the application on container start (migrate, seed once, OpenAPI docs)';

    public function handle(): int
    {
        if (! $this->waitForDatabase((int) $this->option('wait'))) {
            $this->error('Database is not reachable.');

            return self::FAILURE;
        }

        $this->call('migrate', ['--force' => true]);

        if (! $this->option('no-seed') && Restaurant::query()->doesntExist()) {
            $this->info('Empty database: seeding demo data...');
            $this->call('db:seed', ['--force' => true]);
        }

        $this->call('l5-swagger:generate');

        return self::SUCCESS;
    }

    private function waitForDatabase(int $seconds): bool
    {
        $deadline = time() + $seconds;

        do {
            try {
                DB::connection()->getPdo();

                return true;
            } catch (\Throwable) {
                $this->line('Waiting for the database...');
                sleep(2);
            }
        } while (time() < $deadline);

        return false;
    }
}
