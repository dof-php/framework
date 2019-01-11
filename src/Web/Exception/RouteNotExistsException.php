<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Exception;
use ReflectionClass;
use Loy\Framework\Web\Response;

class RouteNotExistsException extends Exception
{
    public function __construct(string $route, int $code = 404)
    {
        $this->message = $route;
        $this->code    = $code;

        $error = strtoupper((new ReflectionClass($this))->getShortName()).': '.$this->message;

        Response::setBody($error)->setStatus($this->code)->send();
    }
}
