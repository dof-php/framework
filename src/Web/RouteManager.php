<?php

declare(strict_types=1);

namespace Loy\Framework\Web;

use Loy\Framework\Base\Annotation;
use Loy\Framework\Base\Exception\DuplicateRouteDefinitionException;
use Loy\Framework\Base\Exception\DuplicateRouteAliasDefinitionException;

final class RouteManager
{
    const ROUTE_DIR = 'Http/Port';
    const REGEX = '#@([a-zA-z]+)\((.*)\)#';

    private static $aliases = [];
    private static $routes  = [];
    private static $dirs    = [];

    public static function findRouteByUriAndMethod(string $uri, string $method, ?array $mimes = [])
    {
        $route = self::$routes[$uri][$method] ?? false;
        if ($route) {
            return $route;
        }
        $hasSuffix = false;
        foreach ($mimes as $alias) {
            $_length = mb_strlen($uri);
            if (false === $_length) {
                continue;
            }
            $_alias = ".{$alias}";
            $length = mb_strlen($_alias);
            if (false === $length) {
                continue;
            }
            $suffix = mb_substr($uri, -$length, $length);
            if ($suffix === $_alias) {
                $hasSuffix = $alias;
                $uri   = mb_substr($uri, 0, ($_length - $length));
                $uri   = join('/', array_filter(explode('/', $uri)));
                $route = self::$routes[$uri][$method] ?? false;
                if (! $route) {
                    break;
                }
                if (in_array($alias, ($route['suffix']['allow'] ?? []))) {
                    $route['suffix']['current'] = $alias;
                    return $route;
                }

                return false;
            }
        }

        $arr = $_arr = array_reverse(explode('/', $uri));
        $cnt = count($arr);
        $set = subsets($arr);
        foreach ($set as $replaces) {
            $arr = $_arr;
            $replaced = [];
            foreach ($replaces as $idx => $replace) {
                $replaced[] = $arr[$idx];
                $arr[$idx] = '?';
            }
            $try = join('/', array_reverse($arr));
            $route = self::$routes[$try][$method] ?? false;
            if (! $route) {
                continue;
            }
            if ($hasSuffix) {
                if (! in_array($hasSuffix, ($route['suffix']['allow'] ?? []))) {
                    return false;
                }
                $route['suffix']['current'] = $hasSuffix;
            }

            $params = $route['params']['raw'] ?? [];
            if (count($params) === count($replaced)) {
                $params   = array_keys($params);
                $replaced = array_reverse($replaced);
                $route['params']['kv']  = array_combine($params, $replaced);
                $route['params']['res'] = $replaced;
            }
            return $route;
        }

        return false;
    }

    public static function compile(array $dirs)
    {
        if (count($dirs) < 1) {
            return;
        }

        self::$dirs = array_map(function ($item) {
            return join(DIRECTORY_SEPARATOR, [$item, self::ROUTE_DIR]);
        }, $dirs);

        // Excetions may thrown but let invoker to catch for different scenarios
        //
        // use Loy\Framework\Base\Exception\InvalidAnnotationDirException;
        // use Loy\Framework\Base\Exception\InvalidAnnotationNamespaceException;
        Annotation::parseClassDirs(self::$dirs, self::REGEX, function ($annotations) {
            if ($annotations) {
                list($ofClass, $ofMethods) = $annotations;
                self::assembleRoutesFromAnnotations($ofClass, $ofMethods);
            }
        }, __CLASS__);
    }

    public static function assembleRoutesFromAnnotations(array $ofClass, array $ofMethods)
    {
        $classNamespace = $ofClass['namespace'] ?? '?';
        $routePrefix    = $ofClass['ROUTE']     ?? null;
        $middlewares    = $ofClass['PIPE']      ?? [];
        $defaultVerbs   = $ofClass['VERB']      ?? [];
        $defaultSuffix  = $ofClass['SUFFIX']    ?? [];
        $defaultMimein  = $ofClass['MIMEIN']    ?? null;
        $defaultMimeout = $ofClass['MIMEOUT']   ?? null;
        $defaultWrapin  = $ofClass['WRAPIN']    ?? null;
        $defaultWrapout = $ofClass['WRAPOUT']   ?? null;
        $defaultWraperr = $ofClass['WRAPERR']   ?? null;

        foreach ($ofMethods as $method => $attrs) {
            $notroute = $attrs['NOTROUTE'] ?? false;
            if ($notroute) {
                continue;
            }
            $route   = $attrs['ROUTE']   ?? '';
            $alias   = $attrs['ALIAS']   ?? null;
            $verbs   = $attrs['VERB']    ?? $defaultVerbs;
            $mimein  = $attrs['MIMEIN']  ?? $defaultMimein;
            $mimein  = ($mimein === '_') ? null : $mimein;
            $mimeout = $attrs['MIMEOUT'] ?? $defaultMimeout;
            $mimeout = ($mimeout === '_') ? null : $mimeout;
            $wrapin  = $attrs['WRAPIN']  ?? $defaultWrapin;
            $wrapin  = ($wrapin === '_') ? null : $wrapin;
            $wrapout = $attrs['WRAPOUT'] ?? $defaultWrapout;
            $wrapout = ($wrapout === '_') ? null : $wrapout;
            $wraperr = $attrs['WRAPERR'] ?? $defaultWraperr;
            $wraperr = ($wraperr === '_') ? null : $wraperr;
            $suffix  = $attrs['SUFFIX']  ?? $defaultSuffix;

            $params  = [];
            $middles = $attrs['PIPE'] ?? [];
            $middles = array_unique(array_merge($middlewares, $middles));
            $urlpath = $routePrefix ? join('/', [$routePrefix, $route]) : $route;
            $urlpath = array_filter(explode('/', $urlpath));
            array_walk($urlpath, function (&$val, $key) use (&$params) {
                $matches = [];
                if (1 === preg_match('#{([a-z]\w+)}#', $val, $matches)) {
                    if ($_param = ($matches[1] ?? false)) {
                        $params[$_param] = null;
                        $val = '?';
                    }
                }
            });
            $urlpath = join('/', $urlpath);
            foreach ($verbs as $verb) {
                if (self::$routes[$urlpath][$verb] ?? false) {
                    throw new DuplicateRouteDefinitionException("{$verb} {$urlpath} ({$classNamespace}@{$method})");
                    continue;
                }
                if ($alias && ($_alias = (self::$aliases[$alias] ?? false))) {
                    $_urlpath = $_alias['urlpath'] ?? '?';
                    $_verb    = $_alias['verb']    ?? '?';
                    $_route   = self::$routes[$_urlpath][$_verb] ?? [];
                    $_classns = $_route['class']   ?? '?';
                    $_method  = $_route['method']  ?? '?';
                    throw new DuplicateRouteAliasDefinitionException(
                        "{$alias} => ({$verb} {$urlpath} | {$classNamespace}@{$method}) <=> ({$_verb} {$_urlpath} | {$_classns}@{$_method})"
                    );
                }

                if ($alias) {
                    self::$aliases[$alias] = [
                        'urlpath' => $urlpath,
                        'verb'    => $verb,
                    ];
                }

                self::$routes[$urlpath][$verb] = [
                    'urlpath' => $urlpath,
                    'suffix'  => [
                        'allow'   => $suffix,
                        'current' => null,
                    ],
                    'verb'    => $verb,
                    'alias'   => $alias,
                    'class'   => $classNamespace,
                    'method'  => [
                        'name'   => $method,
                        'params' => $attrs['parameters'] ?? [],
                    ],
                    'pipes'   => $middles,
                    'params'  => [
                        'raw' => $params,
                        'res' => [],
                        'api' => [],
                        'kv'  => [],
                    ],
                    'mimein'  => $mimein,
                    'mimeout' => $mimeout,
                    'wrapin'  => $wrapin,
                    'wrapout' => $wrapout,
                    'wraperr' => $wraperr,
                ];
            }
        }
    }

    public static function filterAnnotationPipe(string $val) : array
    {
        return array_trim(explode(',', trim($val)));
    }

    public static function filterAnnotationSuffix(string $val) : array
    {
        return array_trim(explode(',', strtolower(trim($val))));
    }

    public static function filterAnnotationVerb(string $val) : array
    {
        return array_trim(explode(',', strtoupper(trim($val))));
    }

    public static function filterAnnotationRoute(string $val)
    {
        return join('/', array_trim(explode('/', trim($val))));
    }

    public static function getAliases() : array
    {
        return self::$aliases;
    }

    public static function getRoutes() : array
    {
        return self::$routes;
    }
}
