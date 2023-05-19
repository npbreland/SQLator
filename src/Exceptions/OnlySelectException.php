<?php

declare(strict_types=1);

namespace NPBreland\SQLator\Exceptions;

class OnlySelectException extends \Exception
{
    public function __construct(
        $msg = "",
        $code = 0,
        \Exception $previous = null
    ) {
        $msg .= " (Your SQLator is currently configured to only read from the"
        . " database. A non-read action was requested.)";
        parent::__construct($msg, $code, $previous);
    }
}
