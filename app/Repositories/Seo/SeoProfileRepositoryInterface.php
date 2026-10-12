<?php

namespace App\Repositories\Seo;

interface SeoProfileRepositoryInterface
{
    /**
     * @return array{data: list<array{id: int, type: string, updatedAt: string}>, total: int, count: int}
     */
    public function list(): array;
}
