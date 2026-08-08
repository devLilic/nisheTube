<?php

namespace App\Providers;

use App\Domain\Discovery\Contracts\ClusteringProvider;
use App\Domain\Discovery\Contracts\TopicExpansionProvider;
use App\Domain\Discovery\Services\DeterministicClusteringProvider;
use App\Domain\Discovery\Services\DeterministicTopicExpansionProvider;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\YouTube\Contracts\QuotaLedger;
use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Contracts\YouTubeApiClient;
use App\Domain\YouTube\Providers\YouTubeDataApiProvider;
use App\Domain\YouTube\Services\DatabaseQuotaLedger;
use App\Domain\YouTube\Services\LaravelYouTubeApiClient;
use App\Domain\YouTube\Services\YouTubeConfiguration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(YouTubeConfiguration::class, function (): YouTubeConfiguration {
            $configuration = config('youtube');

            if (! is_array($configuration)) {
                $configuration = [];
            }

            return YouTubeConfiguration::fromArray($configuration);
        });

        $this->app->singleton(QuotaLedger::class, DatabaseQuotaLedger::class);
        $this->app->singleton(YouTubeApiClient::class, LaravelYouTubeApiClient::class);
        $this->app->bind(VideoResearchProvider::class, YouTubeDataApiProvider::class);
        $this->app->bind(TopicExpansionProvider::class, DeterministicTopicExpansionProvider::class);
        $this->app->bind(ClusteringProvider::class, DeterministicClusteringProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap(collect(LibraryTargetType::cases())
            ->mapWithKeys(fn (LibraryTargetType $type): array => [$type->value => $type->modelClass()])
            ->all());

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
