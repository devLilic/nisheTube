<?php

namespace Tests\Feature\Semantic;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Exports\Actions\CreateSemanticPerformanceExport;
use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\ReadModels\BuildResearchRunExportDataset;
use App\Domain\Exports\ReadModels\BuildSemanticPerformanceExportDataset;
use App\Domain\Exports\Services\ExportWriterManager;
use App\Domain\Semantic\Actions\CalculateSemanticPerformance;
use App\Domain\Semantic\Actions\CalculateSemanticTopicProfile;
use App\Http\ViewModels\AnalyzerRunViewModel;
use App\Jobs\Exports\GenerateResearchExport;
use App\Models\AnalyzerRunVideo;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\SemanticPerformanceProfile;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SemanticPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_deterministic_typed_topic_and_title_pattern_aggregates(): void
    {
        $owner = User::factory()->create();
        $run = app(CreateAnalyzerRun::class)->handleTarget($owner, 'channel', 'performance-channel', CollectionCachePolicy::AllowFreshCache);
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'performance-channel',
            'title' => 'Apartment Lab',
        ]);
        $channelSnapshot = ChannelSnapshot::query()->create([
            'channel_id' => $channel->id,
            'collection_run_id' => $run->collection_run_id,
            'subscriber_count_hidden' => false,
            'collected_at' => now(),
        ]);
        $run->update(['channel_id' => $channel->id, 'channel_snapshot_id' => $channelSnapshot->id]);

        $fixtures = [
            ['How to organize small apartment storage', 100, 10.0, 'normal'],
            ['How to improve small apartment storage?', 200, 20.0, 'breakout'],
            ['5 small apartment storage ideas', 300, 30.0, 'strong'],
            ['7 small apartment storage hacks', 500, 50.0, 'breakout'],
            ['Apartment storage makeover', null, null, null],
        ];
        foreach ($fixtures as $index => [$title, $views, $viewsPerDay, $class]) {
            $video = Video::query()->create([
                'provider' => 'youtube',
                'provider_video_id' => "performance-video-{$index}",
                'channel_id' => $channel->id,
                'title' => $title,
                'published_at' => now()->subDays($index + 1),
            ]);
            $snapshot = VideoSnapshot::query()->create([
                'video_id' => $video->id,
                'collection_run_id' => $run->collection_run_id,
                'view_count' => $views,
                'views_per_day' => $viewsPerDay,
                'collected_at' => now(),
            ]);
            AnalyzerRunVideo::query()->create([
                'analyzer_run_id' => $run->id,
                'video_id' => $video->id,
                'video_snapshot_id' => $snapshot->id,
                'channel_snapshot_id' => $channelSnapshot->id,
                'role' => AnalyzerVideoRole::ChannelRecentUpload,
                'source_position' => $index + 1,
                'channel_median_ratio' => $class === null ? null : 1,
                'breakout_class' => $class,
                'threshold_version' => $class === null ? null : 'video-relative-performance-v1',
            ]);
        }

        $topicProfile = app(CalculateSemanticTopicProfile::class)->handle($run);
        $profile = app(CalculateSemanticPerformance::class)->handle($run);
        $sameProfile = app(CalculateSemanticPerformance::class)->handle($run);
        $run->update(['status' => 'completed', 'progress_percent' => 100, 'completed_at' => now()]);

        $this->assertSame($profile->id, $sameProfile->id);
        $this->assertSame($owner->id, $profile->user_id);
        $this->assertSame($topicProfile->id, $profile->semantic_topic_profile_id);
        $this->assertSame('semantic-performance-v1', $profile->calculation_version);
        $this->assertSame('semantic-title-terms-v1', $profile->topic_version);
        $this->assertSame('editorial-title-patterns-v1', $profile->title_pattern_version);
        $this->assertSame(2, $profile->minimum_sample_size);

        $howTo = $profile->aggregates()->where('group_type', 'title_pattern')->where('label_key', 'how_to')->firstOrFail();
        $this->assertSame(2, $howTo->sample_count);
        $this->assertSame('150.0000', $howTo->median_views);
        $this->assertSame('150.0000', $howTo->average_views);
        $this->assertSame('15.000000', $howTo->median_views_per_day);
        $this->assertSame('50.0000', $howTo->breakout_rate_percent);

        $unclassified = $profile->aggregates()->where('group_type', 'title_pattern')->where('is_unclassified', true)->firstOrFail();
        $this->assertSame(1, $unclassified->sample_count);
        $this->assertFalse($unclassified->meets_minimum_sample);
        $this->assertNull($unclassified->median_views);
        $this->assertNull($unclassified->breakout_rate_percent);
        $this->assertTrue($profile->aggregates()->where('group_type', 'topic')->where('meets_minimum_sample', true)->exists());

        $payload = app(AnalyzerRunViewModel::class)->toArray($run->fresh());
        $this->assertSame('semantic-performance-v1', $payload['topic_performance']['calculation_version']);
        $this->assertSame(5, $payload['topic_performance']['cohort_video_count']);

        $dataset = app(BuildSemanticPerformanceExportDataset::class)->handle($owner, $profile->public_id);
        $this->assertContains('Performance version', $dataset->headers);
        $this->assertContains('Observed association within one frozen recent cohort; not evidence of causation.', array_merge(...$dataset->rows));

        Queue::fake();
        $export = app(CreateSemanticPerformanceExport::class)->handle($owner, $run, ExportFormat::Csv);
        $this->assertSame('semantic_performance', $export->selection['type']);
        $this->assertSame($profile->public_id, $export->selection['semantic_performance_profile_id']);
        Queue::assertPushed(GenerateResearchExport::class, 1);
        Storage::fake('local');
        config(['exports.disk' => 'local']);
        (new GenerateResearchExport($export->id))->handle(
            app(BuildResearchRunExportDataset::class),
            app(ExportWriterManager::class),
            app(BuildSemanticPerformanceExportDataset::class),
        );
        $export->refresh();
        $this->assertSame('completed', $export->status->value);
        $this->assertNotNull($export->path);
        Storage::disk('local')->assertExists($export->path);

        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.topic_performance.calculation_version', 'semantic-performance-v1')
                ->where('run.topic_performance.minimum_sample_size', 2));
        $this->actingAs($owner)->post(route('analyzer.runs.performance-export', $run), ['format' => 'xlsx'])
            ->assertRedirect(route('exports.index'));
        Queue::assertPushed(GenerateResearchExport::class, 2);
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('analyzer.runs.show', $run))->assertForbidden();
        $this->actingAs($other)->post(route('analyzer.runs.performance-export', $run), ['format' => 'csv'])->assertForbidden();

        $this->expectException(DomainException::class);
        $profile->update(['minimum_sample_size' => 3]);
    }

    public function test_new_analyzer_attempt_creates_a_new_performance_version_without_rewriting_history(): void
    {
        $owner = User::factory()->create();
        $first = app(CreateAnalyzerRun::class)->handleTarget($owner, 'channel', 'version-channel', CollectionCachePolicy::AllowFreshCache);
        $second = app(CreateAnalyzerRun::class)->handleTarget($owner, 'channel', 'version-channel', CollectionCachePolicy::ForceRefresh);

        foreach ([$first, $second] as $run) {
            $profile = app(CalculateSemanticPerformance::class)->handle($run);
            $this->assertSame($run->id, $profile->analyzer_run_id);
        }

        $this->assertDatabaseCount('semantic_performance_profiles', 2);
        $this->assertSame(2, SemanticPerformanceProfile::query()->where('user_id', $owner->id)->distinct()->count('analyzer_run_id'));
    }
}
