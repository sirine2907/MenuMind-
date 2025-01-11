<?php

namespace App\Console\Commands;

use App\Services\Search\SearchIndexer;
use App\Services\Search\TypesenseClient;
use Illuminate\Console\Command;

class ReindexCommand extends Command
{
    protected $signature = 'menumind:reindex
        {--keep : Keep existing collections and only upsert documents}
        {--wait=0 : Seconds to wait for Typesense to become healthy}';

    protected $description = 'Rebuild the Typesense dishes and doc_chunks collections from the database';

    public function handle(SearchIndexer $indexer, TypesenseClient $client): int
    {
        if (! config('typesense.enabled')) {
            $this->warn('Typesense is disabled (TYPESENSE_ENABLED=false); nothing to do.');

            return self::SUCCESS;
        }

        if (! $this->waitForTypesense($client, (int) $this->option('wait'))) {
            $this->error('Typesense is not reachable.');

            return self::FAILURE;
        }

        $this->info('Reindexing... (the first run downloads the embedding model, this can take a minute)');
        $started = microtime(true);

        $counts = $indexer->reindexAll(fresh: ! $this->option('keep'));

        $this->table(['Collection', 'Documents'], [
            ['dishes', $counts['dishes']],
            ['doc_chunks', $counts['doc_chunks']],
        ]);
        $this->info(sprintf('Done in %.1fs.', microtime(true) - $started));

        return self::SUCCESS;
    }

    private function waitForTypesense(TypesenseClient $client, int $seconds): bool
    {
        $deadline = time() + $seconds;

        do {
            if ($client->isHealthy()) {
                return true;
            }
            if ($seconds > 0) {
                sleep(2);
            }
        } while (time() < $deadline);

        return false;
    }
}
