<?php

namespace App\Domain\Thumbnails\Exceptions;

use RuntimeException;

final class ThumbnailImageException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
