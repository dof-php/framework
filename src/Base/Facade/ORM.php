<?php

declare(strict_types=1);

namespace Loy\Framework\Base\Facade;

use Loy\Framework\Base\Facade;
use Loy\Framework\Base\ORM as Instance;

class ORM extends Facade
{
    public static $singleton = true;
    protected static $namespace = Instance::class;

    public static function __getDynamicProxyNamespace()
    {
        return static::class;
    }
}
