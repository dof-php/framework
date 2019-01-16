<?php

declare(strict_types=1);

namespace Loy\Framework\Base\Exception;

use Exception;

class TypeHintConvertException extends Exception
{
    public function __construct(string $message, int $code = 400)
    {
        $this->message = $message;
        $this->code    = $code;
    }
}