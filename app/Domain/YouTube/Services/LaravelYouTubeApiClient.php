<?php

namespace App\Domain\YouTube\Services;

use App\Domain\YouTube\Contracts\QuotaLedger;
use App\Domain\YouTube\Contracts\YouTubeApiClient;
use App\Domain\YouTube\Data\ProviderRequestContext;
use App\Domain\YouTube\Data\QuotaAttempt;
use App\Domain\YouTube\Enums\QuotaUsageOutcome;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

class LaravelYouTubeApiClient implements YouTubeApiClient
{
    public function __construct(
        private readonly Factory $http,
        private readonly QuotaLedger $quotaLedger,
        private readonly YouTubeConfiguration $configuration,
        private readonly YouTubeErrorMapper $errorMapper,
    ) {}

    public function get(
        string $endpoint,
        array $query,
        ?ProviderRequestContext $context = null,
    ): array {
        $definition = $this->configuration->endpoint($endpoint);
        $apiKey = $this->configuration->apiKey();
        $context ??= new ProviderRequestContext;

        for ($attempt = 1; $attempt <= $this->configuration->maxAttempts; $attempt++) {
            $eventId = $this->quotaLedger->begin(new QuotaAttempt(
                provider: 'youtube',
                bucket: $definition->bucket,
                endpoint: $definition->name,
                estimatedCost: $definition->cost,
                userId: $context->userId,
                researchRunId: $context->researchRunId,
                collectionRunId: $context->collectionRunId,
            ));

            try {
                $response = $this->http
                    ->baseUrl($this->configuration->baseUrl)
                    ->acceptJson()
                    ->timeout($this->configuration->timeoutSeconds)
                    ->connectTimeout($this->configuration->connectTimeoutSeconds)
                    ->get($definition->path, [...$query, 'key' => $apiKey]);
            } catch (ConnectionException) {
                $error = new YouTubeProviderException(YouTubeErrorCode::Unavailable);
                $this->quotaLedger->complete($eventId, QuotaUsageOutcome::Failed, $error->providerCode);

                if ($this->shouldRetry($error, $attempt)) {
                    $this->pauseBeforeRetry($attempt);

                    continue;
                }

                throw $error;
            }

            if (! $response->successful()) {
                $error = $this->errorMapper->fromResponse($response);
                $this->quotaLedger->complete($eventId, QuotaUsageOutcome::Failed, $error->providerCode);

                if ($this->shouldRetry($error, $attempt)) {
                    $this->pauseBeforeRetry($attempt);

                    continue;
                }

                throw $error;
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                $error = new YouTubeProviderException(YouTubeErrorCode::PartialData);
                $this->quotaLedger->complete($eventId, QuotaUsageOutcome::Failed, $error->providerCode);

                throw $error;
            }

            $this->quotaLedger->complete($eventId, QuotaUsageOutcome::Succeeded);

            return $payload;
        }

        throw new YouTubeProviderException(YouTubeErrorCode::Unavailable);
    }

    private function shouldRetry(YouTubeProviderException $exception, int $attempt): bool
    {
        return $exception->isRetryable() && $attempt < $this->configuration->maxAttempts;
    }

    private function pauseBeforeRetry(int $attempt): void
    {
        $delay = $this->configuration->retryDelayMilliseconds * (2 ** ($attempt - 1));

        if ($delay > 0) {
            usleep($delay * 1000);
        }
    }
}
