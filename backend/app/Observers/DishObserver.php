<?php

namespace App\Observers;

use App\Models\Dish;
use App\Services\Search\SearchIndexer;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

// Keeps the Typesense index up to date when a dish is saved or deleted.
// "AfterCommit" = it only runs once the database transaction is committed.
class DishObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private SearchIndexer $indexer) {}

    public function saved(Dish $dish): void
    {
        $this->indexer->indexDish($dish);
    }

    public function deleted(Dish $dish): void
    {
        $this->indexer->removeDish($dish->id);
    }
}
