<?php

declare(strict_types=1);

namespace NPBreland\SQLator\Exceptions;

class DBException extends \Exception
{
    public function __construct(
        string $sql,
        $code = 0,
        \Exception $previous = null
    ) {
        $msg = '';
        if (!is_null($previous)) {
            $msg .= $previous->getMessage();
        }

        $msg .= "\nSQL: $sql";

        if (!is_null($previous) && !is_null($previous->errorInfo)) {
            $msg .= "\nError Info:";
            foreach ($previous->errorInfo as $key => $value) {
                $msg .= "\n$key: $value";
            }
        }

        parent::__construct($msg, $code, $previous);
    }
}
