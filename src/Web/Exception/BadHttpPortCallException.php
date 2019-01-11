<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Exception;
use ReflectionClass;
use Loy\Framework\Web\Response;

class BadHttpPortCallException extends Exception
{
    public function __construct(string $call, int $code = 500)
    {
        $this->message = $call;
        $this->code    = $code;

        $error = strtoupper((new ReflectionClass($this))->getShortName()).': '.$this->message;

        Response::setBody($error)->setStatus($this->code)->send();
    }
}
