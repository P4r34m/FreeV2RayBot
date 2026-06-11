<?php

namespace App\Services\Exceptions;

use RuntimeException;

/** No active/healthy panel with capacity could be selected for issuance. */
class NoPanelAvailableException extends RuntimeException
{
}
