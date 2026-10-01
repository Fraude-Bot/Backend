<?php

namespace App\Repositories\Organization;

interface OrganizationSuggestRepositoryInterface
{
    /**
     * Distinct organization names containing the query, ordered by name.
     *
     * @return list<string>
     */
    public function names(string $query): array;
}
