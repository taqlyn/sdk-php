<?php

declare(strict_types=1);

namespace Taqlyn;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $responseBody,
    ) {
        parent::__construct("Taqlyn API request failed with status {$statusCode}.");
    }
}
