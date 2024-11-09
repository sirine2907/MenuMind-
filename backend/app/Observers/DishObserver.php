<?php

namespace App\Observers;

use App\Models\Dish;
use App\Services\Search\SearchIndexer;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Mirrors every dish write into Typesense. Runs after the surrounding
 * transaction commits, so a rolled-back write never reaches the index.
 */
class DishObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly SearchIndexer $indexer) {}

    public function saved(Dish $dish): void
    {
        $this->indexer->indexDish($dish);
    }

    public function deleted(Dish $dish): void
    {
        $this->indexer->removeDish($dish->id);
    }
}
