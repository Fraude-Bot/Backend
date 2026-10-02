<?php

namespace App\Repositories\Organization;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\Map\ValueObjects\ContactNode;
use App\Domain\Map\ValueObjects\Edge;
use App\Domain\Map\ValueObjects\MapResult;
use App\Domain\Map\ValueObjects\PaymentMethodNode;
use App\Domain\Map\ValueObjects\ScammerNode;
use App\Domain\Search\ValueObjects\PaginatedResult;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Scammer;
use App\Repositories\Search\SearchCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    private const int CACHE_TTL_SECONDS = 3600;

    public function findOrganizationById(int $id): ?Organization
    {
        return Cache::remember(
            SearchCache::key("public:organization:{$id}"),
            self::CACHE_TTL_SECONDS,
            fn () => Organization::query()
                ->where('is_active', true)
                ->with(['reports' => fn ($query) => $query->where('is_active', true)->with('products')])
                ->withCount(['reports' => fn ($query) => $query->where('is_active', true)])
                ->find($id),
        );
    }

    public function findCalendarByOrganizationIdAndYear(int $id, int $year): ?Collection
    {
        return Cache::remember(SearchCache::key("public:organization:{$id}:calendar:{$year}"), self::CACHE_TTL_SECONDS, function () use ($id, $year) {
            $organization = Organization::query()->where('is_active', true)->with(['reports' => fn ($query) => $query->where('is_active', true)])->find($id);

            if (! $organization) {
                return null;
            }

            $monthsWithReports = $organization->reports->filter(fn ($report) => $report->created_at->year == $year)
                ->groupBy(fn ($report) => $report->created_at->format('n'))
                ->map(fn (Collection $reports) => $reports->count());

            $months = collect(range(1, 12))
                ->mapWithKeys(fn (int $month) => [$month => $monthsWithReports->get($month, 0)]);

            return $months;
        });
    }

    public function findContactsById(int $id): ?Collection
    {
        return Cache::remember(SearchCache::key("public:organization:{$id}:contacts"), self::CACHE_TTL_SECONDS, function () use ($id) {
            $organization = Organization::query()->where('is_active', true)->with(['contacts' => fn ($query) => $query->where('is_active', true)])->find($id);

            if (! $organization) {
                return null;
            }

            return $organization->contacts;
        });
    }

    public function findPaginatedContactsById(int $id, int $page, int $count, ?string $platform = null): ?PaginatedResult
    {
        $cacheKey = "public:organization:{$id}:contacts:{$page}:{$count}";

        if ($platform) {
            $cacheKey .= "_platform_{$platform}";
        }

        return Cache::remember(SearchCache::key($cacheKey), self::CACHE_TTL_SECONDS, function () use ($id, $page, $count, $platform) {
            $organization = Organization::query()->where('is_active', true)->find($id);

            if (! $organization) {
                return null;
            }

            $query = $organization->contacts()->where('is_active', true);

            if ($platform) {
                $platformType = PlatformType::tryFromName(Str::upper($platform));

                if (! $platformType) {
                    return PaginatedResult::empty();
                }

                $query->where('platform', $platformType);
            }

            $total = (clone $query)->count();
            $items = $query->forPage($page, $count)->get();

            return new PaginatedResult($items, $total);
        });
    }

    public function findPaginatedReportsById(int $id, int $page, int $count): ?PaginatedResult
    {
        $cacheKey = "public:organization:{$id}:reports:{$page}:{$count}";

        return Cache::remember(SearchCache::key($cacheKey), self::CACHE_TTL_SECONDS, function () use ($id, $page, $count) {
            $organization = Organization::query()->where('is_active', true)->find($id);

            if (! $organization) {
                return null;
            }

            $query = $organization->reports()
                ->where('is_active', true)
                ->orderByDesc('reports.created_at');

            $total = (clone $query)->count();
            $items = $query->forPage($page, $count)->get();

            return new PaginatedResult($items, $total);
        });
    }

    public function findMapById(int $id): ?MapResult
    {
        $organization = Organization::query()
            ->where('is_active', true)
            ->with([
                'contacts' => fn ($query) => $query->where('contacts.is_active', true),
                'paymentMethods' => fn ($query) => $query->where('payment_methods.is_active', true),
                'scammers' => fn ($query) => $query->where('scammers.is_active', true),
            ])
            ->find($id);

        if (! $organization) {
            return null;
        }

        $contacts = $organization->contacts->unique('id')->values()->ensure(Contact::class);
        $paymentMethods = $organization->paymentMethods->unique('id')->values()->ensure(PaymentMethod::class);
        $scammers = $organization->scammers->unique('id')->values()->ensure(Scammer::class);

        $centerNode = $organization->toNode()->centered();
        $scammerNodes = $scammers->map(fn (Scammer $scammer): ScammerNode => $scammer->toNode());
        $contactNodes = $contacts->map(fn (Contact $contact): ContactNode => $contact->toNode());
        $paymentMethodNodes = $paymentMethods->map(fn (PaymentMethod $paymentMethod): PaymentMethodNode => $paymentMethod->toNode());

        $nodes = Collection::mergeAll(
            collect([$centerNode]),
            $scammerNodes,
            $contactNodes,
            $paymentMethodNodes,
        );

        $scammerNodesById = $scammerNodes->keyBy(fn (ScammerNode $node) => $node->id);

        $sequence = 0;
        $edges = collect();

        foreach ($contactNodes as $contactNode) {
            $edges->push(Edge::contact(++$sequence, $contactNode, $centerNode));
        }

        foreach ($paymentMethodNodes as $paymentMethodNode) {
            $edges->push(Edge::payment(++$sequence, $paymentMethodNode, $centerNode));
        }

        foreach ($scammers as $scammer) {
            $scammerNode = $scammerNodesById->get((string) $scammer->id);

            if (! $scammerNode) {
                continue;
            }

            $edges->push(Edge::linked(++$sequence, $centerNode, $scammerNode));
        }

        return new MapResult($nodes, $edges);
    }

    public function suggest(string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
            return [];
        }

        return Cache::remember(
            SearchCache::key('organization:suggest:'.strtolower($query)),
            self::CACHE_TTL_SECONDS,
            function () use ($query): array {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);

                return Organization::query()
                    ->withTrashed()
                    ->whereRaw("name LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                    ->select('name')
                    ->distinct()
                    ->orderBy('name')
                    ->limit(5)
                    ->pluck('name')
                    ->all();
            },
        );
    }

    public function list(): Collection
    {
        return Organization::query()->get();
    }

    public function create(array $attributes): Organization
    {
        return Organization::create($attributes);
    }

    public function update(Organization $organization, array $attributes): Organization
    {
        $organization->update($attributes);

        return $organization;
    }

    public function delete(Organization $organization): void
    {
        $organization->delete();
    }

    public function restore(int $id): Organization
    {
        $organization = Organization::onlyTrashed()->findOrFail($id);
        $organization->restore();

        return $organization;
    }

    public function scammers(Organization $organization): Collection
    {
        return $organization->scammers;
    }

    public function attachScammer(Organization $organization, Scammer $scammer): void
    {
        $organization->scammers()->syncWithoutDetaching([$scammer->id]);
        SearchCache::invalidate();
    }

    public function attachContact(Organization $organization, int $contactId): void
    {
        $organization->contacts()->syncWithoutDetaching([$contactId]);
        SearchCache::invalidate();
    }

    public function attachPaymentMethod(Organization $organization, int $paymentMethodId): void
    {
        $organization->paymentMethods()->syncWithoutDetaching([$paymentMethodId]);
        SearchCache::invalidate();
    }
}
