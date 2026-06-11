<?php

namespace App\Panels\Exceptions;

use RuntimeException;

/**
 * Base exception for any panel-driver failure. Carries an optional
 * machine context for logging and a user-safe flag.
 */
class PanelException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
