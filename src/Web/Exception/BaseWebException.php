<?php

declare(strict_types=1);

namespace Loy\Framework\Web\Exception;

use Exception;
use Loy\Framework\Web\Response;

class BaseWebException extends Exception
{
    public function __construct(string $message, int $code)
    {
        $this->message = $message;
        $this->code    = $code;

        Response::setStatus($code);
        Response::send([$code, objectname($this), $message], true);
    }
}