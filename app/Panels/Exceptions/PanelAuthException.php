<?php

namespace App\Panels\Exceptions;

/**
 * Thrown when authenticating to a panel fails (bad credentials, expired
 * token, disabled admin). Triggers a re-login / token refresh upstream.
 */
class PanelAuthException extends PanelException
{
}
