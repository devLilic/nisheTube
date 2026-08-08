<?php

namespace App\Domain\Research\Data;

use InvalidArgumentException;

final readonly class RunFailure
{
    public string $code;

    public string $message;

    public function __construct(string $code, string $message)
    {
        $code = trim($code);
        $message = trim($message);

        if (preg_match('/^[a-z0-9_]{1,64}$/', $code) !== 1) {
            throw new InvalidArgumentException('A safe run error code is required.');
        }

        if ($message === '' || mb_strlen($message) > 1000) {
            throw new InvalidArgumentException('A safe run error message between 1 and 1000 characters is required.');
        }

        $this->code = $code;
        $this->message = $message;
    }
}
