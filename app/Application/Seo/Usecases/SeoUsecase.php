<?php

namespace App\Application\Seo\Usecases;

use App\Application\Seo\Commands\ListSeoProfilesCommand;
use App\Repositories\Seo\SeoProfileRepositoryInterface;

class SeoUsecase implements SeoUsecaseInterface
{
    public function __construct(private SeoProfileRepositoryInterface $profiles) {}

    /**
     * @return array{data: list<array{id: int, type: string, updatedAt: string}>, total: int, count: int}
     */
    public function list(ListSeoProfilesCommand $command): array
    {
        return $this->profiles->list();
    }
}
