<?php

namespace App\Domain\YouTube\Exceptions;

use App\Domain\YouTube\Enums\YouTubeErrorCode;
use RuntimeException;

class YouTubeProviderException extends RuntimeException
{
    public function __construct(
        public readonly YouTubeErrorCode $providerCode,
    ) {
        parent::__construct($providerCode->safeMessage());
    }

    public function isRetryable(): bool
    {
        return $this->providerCode->isRetryable();
    }
}
