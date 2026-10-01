<?php

namespace App\Repositories\Organization;

use App\Models\Organization;

class OrganizationSuggestRepository implements OrganizationSuggestRepositoryInterface
{
    private const int LIMIT = 5;

    public function names(string $query): array
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);

        return Organization::query()
            ->withTrashed()
            ->whereRaw("name LIKE ? ESCAPE '!'", ["%{$escaped}%"])
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->pluck('name')
            ->all();
    }
}
