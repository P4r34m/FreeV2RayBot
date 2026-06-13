<?php

namespace App\Services\Exceptions;

use RuntimeException;

/** Raised when a user tries to spend more coins than they have. */
class InsufficientCoinsException extends RuntimeException {}
