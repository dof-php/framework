<?php

declare(strict_types=1);

namespace Loy\Framework;

use Loy\Framework\Facade\Annotation;

final class WrapperManager
{
    const WRAPPER_DIR = ['Http', 'Wrapper'];

    private static $dirs = [];
    private static $wrappers = [
        'in'  => [],
        'out' => [],
        'err' => [],
    ];

    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        array_map(function ($item) {
            $dir = ospath($item, self::WRAPPER_DIR);
            if (is_dir($dir)) {
                self::$dirs[] = $dir;
            }
        }, $dirs);

        // Exceptions may thrown but let invoker to catch for different scenarios
        Annotation::parseClassDirs(self::$dirs, function ($annotations) {
            if ($annotations) {
                list($ofClass, , $ofMethods) = $annotations;
                self::assemble($ofClass, $ofMethods);
            }
        }, __CLASS__);
    }

    /**
     * Assemble Wrappers From Annotations
     */
    public static function assemble(array $ofClass, array $ofMethods)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if ((! $namespace) || (! class_exists($namespace))) {
            exception('InvalidWrapperNamespace', ['namespace' => $namespace]);
        }

        $namePrefix  = $ofClass['doc']['PREFIX'] ?? null;
        $typeDefault = $ofClass['doc']['TYPE']   ?? null;

        $ofMethods = $ofMethods['self'] ?? [];
        foreach ($ofMethods as $method => $_attrs) {
            $attrs = $_attrs['doc'] ?? [];
            $notwrapper = $attrs['NOTWRAPPER'] ?? false;
            if ($notwrapper) {
                continue;
            }
            $type = $attrs['TYPE'] ?? $typeDefault;
            if (! $type) {
                continue;
            }
            $name = $attrs['NAME'] ?? '';
            if ('_' === $name) {
                continue;
            }
            $name = $namePrefix ? join('.', [$namePrefix, $name]) : $name;
            if ($exists = (self::$wrappers[$type][$name] ?? false)) {
                $_class  = $exists['class']  ?? '?';
                $_method = $exists['method'] ?? '?';
                exception('DuplicateWrapperDefinition', [
                    'name'   => $name,
                    'current' => [
                        'class'  => $namespace,
                        'method' => $method,
                    ],
                    'previous' => [
                        'class'  => $_class,
                        'method' => $_method,
                    ],
                ]);
            }
            self::$wrappers[$type][$name] = [
                'class'  => $namespace,
                'method' => $method,
            ];
        }
    }

    public static function hasWrapperErr(string $err) : bool
    {
        return !is_null(self::getWrapperErr($err));
    }

    public static function getWrapperErr(?string $err) : ?array
    {
        return $err ? (self::$wrappers['err'][$err] ?? null) : $arr;
    }

    public static function hasWrapperOut(string $out) : bool
    {
        return !is_null(self::getWrapperOut($out));
    }

    public static function getWrapperOut(?string $out) : ?array
    {
        return $out ? (self::$wrappers['out'][$out] ?? null) : $arr;
    }

    public static function hasWrapperIn(string $in) : bool
    {
        return !is_null(self::getWrapperIn($in));
    }

    public static function getWrapperIn(?string $in) : ?array
    {
        return $in ? (self::$wrappers['in'][$in] ?? null) : $arr;
    }

    /**
     * Get the final wrapper format array by wapper class and method
     *
     * @param array $wrapper: Wrapper location (format: ['class' => ?, 'method' => ?])
     * @return array|null: The final wrapper array format
     */
    public static function getWrapperFinal(array $wrapper = null) : ?array
    {
        if (! $wrapper) {
            return null;
        }
        $class  = $wrapper['class']  ?? false;
        $method = $wrapper['method'] ?? false;
        if (false
            || (! $class)
            || (! $method)
            || (! class_exists($class))
            || (! method_exists($class, $method))
        ) {
            return null;
        }

        $result = (new $class)->{$method}();

        return is_array($result) ? $result : null;
    }

    public static function getWrapper(string $type, ?string $name) : ?array
    {
        return self::$wrappers[$type][$name] ?? null;
    }

    public static function getWrappers()
    {
        return self::$wrappers;
    }
}