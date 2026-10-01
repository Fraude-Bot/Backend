<?php

namespace App\Repositories\Suggest;

use App\Repositories\Organization\OrganizationSuggestRepositoryInterface;
use App\Repositories\Search\SearchCache;
use Illuminate\Support\Facades\Cache;

class PublicSuggestRepository
{
    private const int CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private OrganizationSuggestRepositoryInterface $organizationSuggestRepository,
    ) {}

    /**
     * @return list<string>
     */
    public function organizations(string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
            return [];
        }

        return Cache::remember(
            SearchCache::key('organization:suggest:'.strtolower($query)),
            self::CACHE_TTL_SECONDS,
            fn () => $this->organizationSuggestRepository->names($query),
        );
    }
}
