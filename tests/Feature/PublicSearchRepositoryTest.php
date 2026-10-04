<?php

namespace Tests\Feature;

use App\Domain\Scammer\ValueObjects\Clue;
use App\Models\Scammer;
use App\Repositories\Organization\OrganizationCardRepositoryInterface;
use App\Repositories\Scammer\ScammerCardRepositoryInterface;
use App\Repositories\Search\PublicSearchRepository;
use Tests\TestCase;

class PublicSearchRepositoryTest extends TestCase
{
    public function test_total_reflects_all_matches_not_just_the_current_page(): void
    {
        foreach (range(1, 15) as $index) {
            Scammer::factory()->create(['name' => sprintf('Jane Doe %02d', $index)]);
        }

        $result = $this->repository()->find(new Clue('Jane Doe'), 1, 10);

        $this->assertSame(15, $result->total);
        $this->assertCount(10, $result->items);
    }

    public function test_soft_deleted_scammers_are_excluded_from_search(): void
    {
        $scammer = Scammer::factory()->create(['name' => 'Deleted Guy']);
        $scammer->delete();

        $result = $this->repository()->find(new Clue('Deleted Guy'), 1, 10);

        $this->assertSame(0, $result->total);
        $this->assertCount(0, $result->items);
    }

    public function test_cache_is_invalidated_when_a_matching_scammer_is_created(): void
    {
        Scammer::factory()->create(['name' => 'Cache Target One']);

        $first = $this->repository()->find(new Clue('Cache Target'), 1, 10);
        $this->assertSame(1, $first->total);

        Scammer::factory()->create(['name' => 'Cache Target Two']);

        $second = $this->repository()->find(new Clue('Cache Target'), 1, 10);
        $this->assertSame(2, $second->total);
    }

    private function repository(): PublicSearchRepository
    {
        return new PublicSearchRepository(
            app(ScammerCardRepositoryInterface::class),
            app(OrganizationCardRepositoryInterface::class),
        );
    }
}
