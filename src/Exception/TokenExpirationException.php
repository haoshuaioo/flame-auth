<?php

namespace FlameModule\Auth\Exception;

use Exception;
use FlameModule\Auth\Controller\AuthApiController;

/**
 * Token过期异常
 */
class TokenExpirationException extends Exception
{
    public function __construct($message = "Token has expired", $code = 401, $previous = null)
    {
        parent::__construct($message, $code, $previous);
        AuthApiController::response(null, $message, 1, $code);
    }
}