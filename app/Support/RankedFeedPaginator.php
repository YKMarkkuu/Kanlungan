<?php

namespace App\Support;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;

/**
 * A simple paginator whose "next page" link carries our snapshot cursor (?feed=...)
 * instead of ?page=N. Same API the views and community.js already use:
 * hasMorePages(), nextPageUrl(), appends(), count(), getCollection().
 */
class RankedFeedPaginator extends Paginator
{
    private ?string $nextCursor;

    /**
     * @param  \Illuminate\Support\Collection|array  $items  at most $perPage items
     */
    public function __construct($items, int $perPage, bool $hasMore, ?string $nextCursor, array $options = [])
    {
        parent::__construct($items, $perPage, 1, $options);

        $this->hasMore = $hasMore;
        $this->nextCursor = $hasMore ? $nextCursor : null;
    }

    public function nextPageUrl(): ?string
    {
        if (! $this->hasMore || $this->nextCursor === null) {
            return null;
        }

        $path = $this->path();
        $query = Arr::query(array_merge($this->query, ['feed' => $this->nextCursor]));

        return $path.(str_contains($path, '?') ? '&' : '?').$query.$this->buildFragment();
    }
}
