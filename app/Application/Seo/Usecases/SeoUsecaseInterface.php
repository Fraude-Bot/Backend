<?php

namespace App\Application\Seo\Usecases;

use App\Application\Seo\Commands\ListSeoProfilesCommand;

interface SeoUsecaseInterface
{
    /**
     * @return array{data: list<array{id: int, type: string, updatedAt: string}>, total: int, count: int}
     */
    public function list(ListSeoProfilesCommand $command): array;
}
