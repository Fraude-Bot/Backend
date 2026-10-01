<?php

namespace App\Repositories\Scammer;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\Map\ValueObjects\ContactNode;
use App\Domain\Map\ValueObjects\Edge;
use App\Domain\Map\ValueObjects\MapResult;
use App\Domain\Map\ValueObjects\OrganizationNode;
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

class ScammerRepository implements ScammerRepositoryInterface
{
    private const int CACHE_TTL_SECONDS = 3600;

    public function findScammerById(int $id): ?Scammer
    {
        return Cache::remember(
            SearchCache::key("public:scammer:{$id}"),
            self::CACHE_TTL_SECONDS,
            fn () => Scammer::query()
                ->where('is_active', true)
                ->with(['reports' => fn ($query) => $query->where('is_active', true)->with('products')])
                ->withCount(['reports' => fn ($query) => $query->where('is_active', true)])
                ->find($id),
        );
    }

    public function findCalendarByScammerIdAndYear(int $id, int $year): ?Collection
    {
        return Cache::remember(SearchCache::key("public:scammer:{$id}:calendar:{$year}"), self::CACHE_TTL_SECONDS, function () use ($id, $year) {
            $scammer = Scammer::query()->where('is_active', true)->with(['reports' => fn ($query) => $query->where('is_active', true)])->find($id);

            if (! $scammer) {
                return null;
            }

            $monthsWithReports = $scammer->reports->filter(fn ($report) => $report->created_at->year == $year)
                ->groupBy(fn ($report) => $report->created_at->format('n'))
                ->map(fn (Collection $reports) => $reports->count());

            $months = collect(range(1, 12))
                ->mapWithKeys(fn (int $month) => [$month => $monthsWithReports->get($month, 0)]);

            return $months;
        });
    }

    public function findContactsById(int $id): ?Collection
    {
        return Cache::remember(SearchCache::key("public:scammer:{$id}:contacts"), self::CACHE_TTL_SECONDS, function () use ($id) {
            $scammer = Scammer::query()->where('is_active', true)->with(['contacts' => fn ($query) => $query->where('is_active', true)])->find($id);

            if (! $scammer) {
                return null;
            }

            return $scammer->contacts;
        });
    }

    public function findPaginatedContactsById(int $id, int $page, int $count, ?string $platform = null): ?PaginatedResult
    {
        $cacheKey = "public:scammer:{$id}:contacts:{$page}:{$count}";

        if ($platform) {
            $cacheKey .= "_platform_{$platform}";
        }

        return Cache::remember(SearchCache::key($cacheKey), self::CACHE_TTL_SECONDS, function () use ($id, $page, $count, $platform) {
            $scammer = Scammer::query()->where('is_active', true)->find($id);

            if (! $scammer) {
                return null;
            }

            $query = $scammer->contacts()->where('is_active', true);

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
        $cacheKey = "public:scammer:{$id}:reports:{$page}:{$count}";

        return Cache::remember(SearchCache::key($cacheKey), self::CACHE_TTL_SECONDS, function () use ($id, $page, $count) {
            $scammer = Scammer::query()->where('is_active', true)->find($id);

            if (! $scammer) {
                return null;
            }

            $query = $scammer->reports()
                ->where('is_active', true)
                ->orderByDesc('reports.created_at');

            $total = (clone $query)->count();
            $items = $query->forPage($page, $count)->get();

            return new PaginatedResult($items, $total);
        });
    }

    public function findMapById(int $id): ?MapResult
    {
        $scammer = Scammer::query()
            ->where('is_active', true)
            ->with([
                'contacts' => fn ($query) => $query->where('contacts.is_active', true),
                'paymentMethods' => fn ($query) => $query->where('payment_methods.is_active', true),
                'organizations' => fn ($query) => $query->where('organizations.is_active', true),
            ])
            ->find($id);

        if (! $scammer) {
            return null;
        }

        $contacts = $scammer->contacts->unique('id')->values()->ensure(Contact::class);
        $paymentMethods = $scammer->paymentMethods->unique('id')->values()->ensure(PaymentMethod::class);
        $organizations = $scammer->organizations->unique('id')->values()->ensure(Organization::class);

        $centerNode = ScammerNode::from($scammer)->center();
        $organizationNodes = OrganizationNode::fromCollection($organizations);
        $contactNodes = ContactNode::fromCollection($contacts);
        $paymentMethodNodes = PaymentMethodNode::fromCollection($paymentMethods);

        $nodes = Collection::mergeAll(
            collect([$centerNode]),
            $organizationNodes,
            $contactNodes,
            $paymentMethodNodes,
        );

        $organizationNodesById = $organizationNodes->keyBy(fn (OrganizationNode $node) => $node->id);

        $sequence = 0;
        $edges = collect();

        foreach ($contactNodes as $contactNode) {
            $edges->push(Edge::contact(++$sequence, $contactNode, $centerNode));
        }

        foreach ($paymentMethodNodes as $paymentMethodNode) {
            $edges->push(Edge::payment(++$sequence, $paymentMethodNode, $centerNode));
        }

        foreach ($organizations as $organization) {
            $organizationNode = $organizationNodesById->get((string) $organization->id);

            if (! $organizationNode) {
                continue;
            }

            $edges->push(Edge::linked(++$sequence, $centerNode, $organizationNode));
        }

        return new MapResult($nodes, $edges);
    }

    public function list(): Collection
    {
        return Scammer::with(['contacts', 'paymentMethods', 'organizations'])->get();
    }

    public function loadDetails(Scammer $scammer): Scammer
    {
        return $scammer->load(['contacts', 'paymentMethods', 'organizations']);
    }

    public function create(array $attributes): Scammer
    {
        return Scammer::create($attributes);
    }

    public function update(Scammer $scammer, array $attributes): Scammer
    {
        $scammer->update($attributes);

        return $scammer;
    }

    public function delete(Scammer $scammer): void
    {
        $scammer->delete();
    }

    public function restore(int $id): Scammer
    {
        $scammer = Scammer::onlyTrashed()->findOrFail($id);
        $scammer->restore();

        return $scammer;
    }

    public function loadStored(Scammer $scammer): Scammer
    {
        return $scammer->load(['contacts', 'paymentMethods']);
    }

    public function attachContact(Scammer $scammer, int $contactId): void
    {
        $scammer->contacts()->syncWithoutDetaching([$contactId]);
        SearchCache::invalidate();
    }

    public function attachPaymentMethod(Scammer $scammer, int $paymentMethodId): void
    {
        $scammer->paymentMethods()->syncWithoutDetaching([$paymentMethodId]);
        SearchCache::invalidate();
    }

    public function hasContact(Scammer $scammer, Contact $contact): bool
    {
        return $scammer->contacts()->whereKey($contact->id)->exists();
    }
}
