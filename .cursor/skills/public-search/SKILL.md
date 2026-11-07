---
name: public-search
description: >-
  Explains Fraudebot public search: Clue-based scammer/organization card search,
  report title search, SearchCache versioning, and how to add a new searcher.
  Use when implementing search, debugging cache, extending ClueType, or when
  the user mentions PublicSearchRepository, card search, or search-by-title.
disable-model-invocation: true
icon: search
---

# Public search in Fraudebot

Read `architecture.mdc` and `api-guidelines.mdc` before changing code. Payment and contact clue rules live in `payment-methods.mdc`.

Fraudebot has **two public search endpoints**. Do not confuse them with **profile list** reads (`GET .../scammers/{id}/reports`), which paginate links on one entity and are not global search.

| Endpoint | Query | Result | Repository entry |
|----------|-------|--------|------------------|
| `GET /api/public/reports` | `q` → `Clue` | Scammer + organization **cards** (`ReportCardResource`) | `SearchRepositoryInterface` → `PublicSearchRepository` |
| `GET /api/public/reports/search-by-title` | `q` title substring | **Report** rows (`ReportResource`, `PaginatedReports`) | `ReportRepository::findPaginatedByTitleSearch` |

Name **suggest** endpoints (`GET .../scammers/suggest`, `.../organizations/suggest`) are separate autocomplete reads, not these searchers.

## Layer flow

```
Controller → Command → Use case → Repository → (optional) Cache::remember(SearchCache::key(...))
```

- Card search: `ReportController::index` → `SearchReportsCommand` → `ReportUsecase::search` → `PublicSearchRepository::find`.
- Title search: `ReportController::searchByTitle` → `SearchReportsByTitleCommand` → `ReportUsecase::searchByTitle` → `ReportRepository::findPaginatedByTitleSearch`.

Bind `SearchRepositoryInterface` to `PublicSearchRepository` in `AppServiceProvider`. Card matching stays in **`ScammerCardRepository`** and **`OrganizationCardRepository`** (aggregate card repos), not inside `app/Repositories/Search/` except orchestration.

Register static paths before `reports/{...}` in `routes/api.public.php` (e.g. `reports/search-by-title` before `reports`).

## Card search (`PublicSearchRepository`)

1. `ClueType::NOTHING` → empty `CardSearchResult`.
2. Cache key: `SearchCache::key("{lowercase clue}:{page}:{count}")`, TTL **3600** seconds.
3. `matchQuery(Clue)` on both card repos → optional Eloquent `Builder` per side.
4. **Total** = sum of both `count()`s.
5. **Page slice**: `UNION ALL` of `(id, source_type, updated_at)` from each builder, ordered `updated_at DESC, id DESC`, `offset`/`limit` in SQL (`rankedPage`). Use `toBase()` before `select()` so soft-delete scopes apply.
6. **Hydrate** ids per side with `hydrate()`, reorder rows to match the union.

`Clue` (`app/Domain/Scammer/ValueObjects/Clue.php`) classifies trimmed `q` into `ClueType` (email, URL, CLABE, card, account, phone, wallet, name, nothing). Card repos map each type to `matchBy…` methods (contacts, payment methods, `LIKE` name with `ESCAPE '!'`).

`CardPreviewLoader` loads truncated org/product names during **hydrate**, not during match.

`ClueSearchInterface` documents per-type `matchBy…` helpers; card repository interfaces expose `matchQuery` + `hydrate`.

## Report title search

- Empty trimmed `q` → `PaginatedResult::empty()` **without** caching.
- Otherwise: `SearchCache::key("public:reports:title:{lowercase q}:{page}:{count}")`, TTL 3600.
- Active reports only: `is_active`, soft deletes via Eloquent; `title LIKE %…%` with same `ESCAPE '!'` pattern as name search on cards.
- Order: `updated_at DESC`, `id DESC`.

OpenAPI describes case-insensitive match; SQL uses plain `LIKE` (behavior follows DB collation). Align spec or SQL if product requires explicit case folding.

## `SearchCache`

Version key `search:public:cache_version`. `SearchCache::key($suffix)` hashes `search:public:v{version}:{$suffix}`. `invalidate()` increments version so **all** keyed public caches miss at once without enumerating Redis keys. Stale blobs expire by TTL.

**Invalidation**

- Models with `InvalidatesPublicCache` (`Report`, `Scammer`, `Organization`, …) on save/delete.
- Explicit `SearchCache::invalidate()` in aggregate repos (e.g. attach product on report, org/scammer pivot writes).
- Card search and title search share this version namespace.

List endpoints on profiles (contacts, reports on one scammer, etc.) also use `SearchCache::key()` with their own suffixes; same invalidation rules.

## Adding a new searcher

Pick the shape first:

**A. New global search returning scammer/org cards (same JSON as `GET /public/reports`)**

- Extend `Clue` / `ClueType` if needed (see `payment-methods.mdc`).
- Implement matching on **both** `ScammerCardRepository` and `OrganizationCardRepository` (`matchQuery` + any new `matchBy…`).
- No change to `PublicSearchRepository` unless you add a third `source_type` (new table) — then extend union + hydrate like existing scammer/organization sources.

**B. New global search returning another resource shape (e.g. report rows)**

- Add a **repository read** on the aggregate repo (pattern: `findPaginatedByTitleSearch`).
- New command + use-case method + controller action + route + OpenAPI + feature test.
- Use `PaginatedResult` and existing resource (`ReportResource`, etc.).
- Cache with `SearchCache::key` and ensure writes call `invalidate()` (model trait or repository).

**C. New search only on one aggregate’s list API**

- Add `findPaginated…` on `ScammerRepository` / `OrganizationRepository`, not under `app/Repositories/Search/`.

Do **not** call Eloquent from controllers. Do **not** put HTTP in use cases.

## Tests and OpenAPI

- Card search: `tests/Feature/PublicReportControllerTest.php`, `tests/Feature/PublicSearchRepositoryTest.php`.
- Title search: `tests/Feature/PublicReportSearchByTitleTest.php`.
- Document new operations in `openapi.yaml`; extend `tests/Unit/OpenApiDocumentTest.php` for `operationId`.

Use `throttle:public-search` + `AuditApiRequest` for global search routes (same as existing `GET public/reports`).

## Debugging cache

Use the **access-to-redis** skill. Search entries use hashed keys from `SearchCache::key`; bump invalidation or `FLUSHDB` on the cache Redis DB only after warning the user that every checkout shares Redis.
