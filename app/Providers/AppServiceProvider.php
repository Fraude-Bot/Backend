<?php

namespace App\Providers;

use App\Application\Media\TemporaryImageStorageInterface;
use App\Http\Controllers\Public;
use App\Infrastructure\Facebook\FacebookService;
use App\Infrastructure\Facebook\FacebookServiceInterface;
use App\Infrastructure\Instagram\InstagramService;
use App\Infrastructure\Instagram\InstagramServiceInterface;
use App\Infrastructure\Storage\PublicDiskTemporaryImageStorage;
use App\Infrastructure\TikTok\TikTokService;
use App\Infrastructure\TikTok\TikTokServiceInterface;
use App\Infrastructure\Youtube\YoutubeService;
use App\Infrastructure\Youtube\YoutubeServiceInterface;
use App\Models\User;
use App\OpenApi\OpenApiDocument;
use App\Repositories\Organization\OrganizationCardRepository;
use App\Repositories\Organization\OrganizationCardRepositoryInterface;
use App\Repositories\Organization\OrganizationRepositoryInterface;
use App\Repositories\Organization\PublicOrganizationRepository;
use App\Repositories\Scammer\PublicScammerRepository;
use App\Repositories\Scammer\ScammerCardRepository;
use App\Repositories\Scammer\ScammerCardRepositoryInterface;
use App\Repositories\Scammer\ScammerRepositoryInterface;
use App\Repositories\Search\PublicSearchRepository;
use App\Repositories\Search\SearchRepositoryInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Scalar\Facades\Scalar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Model::preventLazyLoading(true);

        $this->app->singleton(ImageManager::class, fn () => ImageManager::usingDriver(GdDriver::class));

        $this->app->singleton(FacebookServiceInterface::class, FacebookService::class);
        $this->app->singleton(YoutubeServiceInterface::class, YoutubeService::class);
        $this->app->singleton(InstagramServiceInterface::class, InstagramService::class);
        $this->app->singleton(TikTokServiceInterface::class, TikTokService::class);
        
        $this->app->bind(TemporaryImageStorageInterface::class, PublicDiskTemporaryImageStorage::class);

        $this->app->bind(OrganizationCardRepositoryInterface::class, OrganizationCardRepository::class);
        $this->app->bind(ScammerCardRepositoryInterface::class, ScammerCardRepository::class);

        $this->app->when(Public\OrganizationController::class)
            ->needs(OrganizationRepositoryInterface::class)
            ->give(PublicOrganizationRepository::class);

        $this->app->when(Public\ReportController::class)
            ->needs(SearchRepositoryInterface::class)
            ->give(PublicSearchRepository::class);

        $this->app->when(Public\ScammerController::class)
            ->needs(ScammerRepositoryInterface::class)
            ->give(PublicScammerRepository::class);

        $this->app->singleton(OpenApiDocument::class, fn () => OpenApiDocument::fromBasePath());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define(
            'manage-fraud-data',
            fn (User $user) => $user->is_active && in_array($user->role, ['admin', 'moderator'], true),
        );

        RateLimiter::for('public-api', fn (Request $request) => [
            Limit::perMinute(120)->by($request->ip()),
        ]);

        RateLimiter::for('public-search', fn (Request $request) => [
            Limit::perMinute(30)->by($request->ip()),
        ]);

        RateLimiter::for('admin', fn (Request $request) => [
            Limit::perMinute(120)->by((string) ($request->user()?->id ?? $request->ip())),
        ]);

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by(mb_strtolower((string) $request->input('email', $request->ip()))),
        ]);

        RateLimiter::for('dev-token', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
        ]);

        Scalar::document('Fraudebot API')
            ->content($this->app->make(OpenApiDocument::class)->bundledJson());

        Collection::macro('mergeAll', fn (Collection ...$collections): Collection => collect($collections)->collapse());
    }
}
