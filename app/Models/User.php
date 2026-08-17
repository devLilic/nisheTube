<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $timezone
 * @property string|null $default_market_key
 * @property int $default_result_depth
 * @property CarbonImmutable|null $completed_run_notifications_read_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'timezone', 'default_market_key', 'default_result_depth'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @return HasMany<ResearchProject, $this> */
    public function researchProjects(): HasMany
    {
        return $this->hasMany(ResearchProject::class);
    }

    /** @return HasMany<ResearchQuery, $this> */
    public function researchQueries(): HasMany
    {
        return $this->hasMany(ResearchQuery::class);
    }

    /** @return HasMany<ResearchRun, $this> */
    public function researchRuns(): HasMany
    {
        return $this->hasMany(ResearchRun::class);
    }

    /** @return HasMany<CollectionRun, $this> */
    public function collectionRuns(): HasMany
    {
        return $this->hasMany(CollectionRun::class);
    }

    /** @return HasMany<AnalyzerRun, $this> */
    public function analyzerRuns(): HasMany
    {
        return $this->hasMany(AnalyzerRun::class);
    }

    /** @return HasMany<SemanticTopicProfile, $this> */
    public function semanticTopicProfiles(): HasMany
    {
        return $this->hasMany(SemanticTopicProfile::class);
    }

    /** @return HasMany<SemanticPerformanceProfile, $this> */
    public function semanticPerformanceProfiles(): HasMany
    {
        return $this->hasMany(SemanticPerformanceProfile::class);
    }

    /** @return HasMany<AnalyzerCuration, $this> */
    public function analyzerCurations(): HasMany
    {
        return $this->hasMany(AnalyzerCuration::class);
    }

    /** @return HasMany<WatchlistItem, $this> */
    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class);
    }

    /** @return HasMany<WatchlistRefreshRun, $this> */
    public function watchlistRefreshRuns(): HasMany
    {
        return $this->hasMany(WatchlistRefreshRun::class);
    }

    /** @return HasMany<TopicWorkspace, $this> */
    public function topicWorkspaces(): HasMany
    {
        return $this->hasMany(TopicWorkspace::class);
    }

    /** @return HasMany<DiscoveryRun, $this> */
    public function discoveryRuns(): HasMany
    {
        return $this->hasMany(DiscoveryRun::class);
    }

    /** @return HasMany<Favorite, $this> */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /** @return HasMany<SavedCommentIdea, $this> */
    public function savedCommentIdeas(): HasMany
    {
        return $this->hasMany(SavedCommentIdea::class);
    }

    /** @return HasMany<ExplorePreset, $this> */
    public function explorePresets(): HasMany
    {
        return $this->hasMany(ExplorePreset::class);
    }

    /** @return HasMany<Tag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /** @return HasMany<ResearchExport, $this> */
    public function researchExports(): HasMany
    {
        return $this->hasMany(ResearchExport::class, 'user_id');
    }

    /** @return HasMany<CleanupRun, $this> */
    public function cleanupRuns(): HasMany
    {
        return $this->hasMany(CleanupRun::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'default_result_depth' => 'integer',
            'completed_run_notifications_read_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }
}
