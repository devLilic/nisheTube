<?php

namespace App\Domain\Audience\Data;

final readonly class AudienceSignalInput
{
    public function __construct(
        public int $commentId,
        public string $text,
    ) {}
}
