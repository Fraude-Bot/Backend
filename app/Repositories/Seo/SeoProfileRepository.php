<?php

namespace App\Repositories\Seo;

use App\Models\Organization;
use App\Models\Scammer;
use App\Repositories\Search\SearchCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SeoProfileRepository implements SeoProfileRepositoryInterface
{
    private const int CACHE_TTL_SECONDS = 604800;

    private const string TYPE_SCAMMER = 'scammer';

    private const string TYPE_ORGANIZATION = 'organization';

    /**
     * @return array{data: list<array{id: int, type: string, updatedAt: string}>, total: int, count: int}
     */
    public function list(): array
    {
        return Cache::remember(
            SearchCache::key('public:seo:profiles'),
            self::CACHE_TTL_SECONDS,
            fn () => $this->query(),
        );
    }

    /**
     * @return array{data: list<array{id: int, type: string, updatedAt: string}>, total: int, count: int}
     */
    private function query(): array
    {
        $scammers = $this->profileQuery(Scammer::query(), self::TYPE_SCAMMER);
        $organizations = $this->profileQuery(Organization::query(), self::TYPE_ORGANIZATION);

        $rows = DB::query()
            ->fromSub($scammers->unionAll($organizations), 'seo_profiles')
            ->orderByRaw("CASE profile_type WHEN 'scammer' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();

        $data = $rows->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'type' => (string) $row->profile_type,
            'updatedAt' => Carbon::parse($row->updated_at)->utc()->format('Y-m-d\TH:i:s\Z'),
        ])->all();

        $count = count($data);

        return [
            'data' => $data,
            'total' => $count,
            'count' => $count,
        ];
    }

    private function profileQuery(Builder $query, string $type): QueryBuilder
    {
        // toBase() must run before select() so SoftDeletingScope is applied
        // and soft-deleted rows do not leak into the union.
        return $query
            ->where('is_active', true)
            ->toBase()
            ->select([
                'id',
                DB::raw("'{$type}' as profile_type"),
                'updated_at',
            ]);
    }
}
