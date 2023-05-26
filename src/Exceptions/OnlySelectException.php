<?php

declare(strict_types=1);

namespace NPBreland\SQLator\Exceptions;

class OnlySelectException extends \Exception
{
    public function __construct(
        string $command,
        string $response,
        $code = 0,
        \Exception $previous = null
    ) {
        $msg = "Your SQLator is currently configured to only read from the"
        . " database. A non-read action was requested.";
        $msg .= "\nOriginal Command: $command";
        $msg .= "\nAI Response: $response";
        parent::__construct($msg, $code, $previous);
    }
}
