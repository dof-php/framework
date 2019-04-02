<?php

declare(strict_types=1);

namespace Loy\Framework;

use Loy\Framework\Facade\Annotation;

/**
 * Classes container - the key of dependency injection
 */
final class Container
{
    private static $classes    = [];
    private static $filenskv   = [];    // filepath => namespace
    private static $interfaces = [];

    /**
     * Dependency injection for injectable class or interface
     *
     * @param string $namespace: expected namespace of expected class|interface
     */
    public static function di(string $namespace)
    {
        $class = self::get($namespace);
        if (! ($ns = $class['namespace'] ?? false)) {
            exception('ClassNamespaceMissing', compact('namespace', 'class'));
        }

        // Get class constructor definition
        $constructor = $class['constructor']['self'] ?? false;
        if (! $constructor) {
            $constructor = $class['constructor']['parent'] ?? false;
            // If class constructor not defined(simpliest)
            // Then just initialize that class and return
            if (! $constructor) {
                return new $ns;
            }
        }

        // Do not initialize non-public constructor
        if (! in_array('public', ($constructor['modifiers'] ?? []))) {
            exception('UnInjectableDependency', [
                'error' => 'Non-public constructor',
                'class' => $ns
            ]);
        }

        // Parse class constructor parameters and di more classes recursively if necessary
        $params  = $constructor['parameters'] ?? [];
        $_params = [];    // Final parameters that $class constructor need
        foreach ($params as $param) {
            $name = $param['name'] ?? false;
            $type = $param['type']['type'] ?? false;
            if ((! $name) || (! $type)) {
                continue;
            }
            if ($param['optional'] ?? false) {
                break;
            }
            if ($param['type']['builtin'] ?? false) {
                if ($param['nullable'] ?? false) {
                    $_params[] = null;
                    continue;
                }
                exception('UnInjectableDependency', [
                    'error' => 'Constructor has builtin required parameter',
                    'class' => $ns,
                    'type'  => $type,
                    'name'  => $name,
                ]);
            }
            if (class_exists($type) || interface_exists($type)) {
                $_params[] = self::di($type);
            }
        }

        return new $ns(...$_params);
    }

    /**
     * Complete method/function actual require parameters from target parameters according do definition
     *
     * @return array: Final parameters method/funciton required
     */
    public static function complete()
    {
        // TODO
    }

    /**
     * Build container by namespaces
     */
    public static function build(array $dirs)
    {
        foreach ($dirs as $domain) {
            self::load($domain, $domain);
        }
    }

    /**
     * Load classes by domain
     */
    private static function load(string $dir, string $domain)
    {
        walk_dir($dir, function ($path) use ($domain) {
            $realpath = $path->getRealpath();
            if ($path->isDir()) {
                return self::load($realpath, $domain);
            }

            if ($path->isFile() && ('php' === $path->getExtension())) {
                $ns = get_namespace_of_file($realpath, true);
                if ($ns) {
                    self::add($ns, $realpath, $domain);
                }
            }
        });
    }

    /**
     * Get class in container by namespace
     */
    public static function get(string $namespace) : array
    {
        $class = self::$classes[$namespace] ?? false;
        if ($class) {
            return $class;
        }

        $implementor = self::$interfaces[$namespace]['implementor'] ?? false;
        if ($implementor) {
            $class = self::$classes[$implementor] ?? false;
            if ($class) {
                return $class;
            }
        }

        // Lazy loading - add class in container when really need it
        return self::add($namespace);
    }

    /**
     * Add one class information to container by the namespace of class or interface
     */
    public static function add(string $namespace, string $realpath = null, string $domain = null)
    {
        if (class_exists($namespace)) {
            return self::addByClass($namespace, $realpath, $domain);
        }

        if (interface_exists($namespace)) {
            return self::addByInterface($namespace, null, null);
        }

        exception('ClassOrInterfaceNotExists', ['namespace' => $namespace]);
    }

    /**
     * Add one class information to container by the namespace of interface
     */
    public static function addByInterface(string $namespace, string $realpath = null, string $domain = null)
    {
        if (! interface_exists($namespace)) {
            exception('InterfaceAddingToContainerNotFound', ['interface' => $namespace]);
        }

        list($reflection, , ) = Annotation::parseNamespace($namespace);
        $implementor = $reflection['doc']['IMPLEMENTOR'] ?? false;
        if ((! $implementor) || (! class_exists($implementor))) {
            exception('ImplementorNotExists', ['implementor' => $implementor]);
        }

        $class = self::$classes[$implementor] ?? false;
        if ($class) {
            return $class;
        }

        $_implementor = self::addByClass($implementor);

        self::$interfaces[$namespace] = $implementor;

        return $_implementor;
    }

    /**
     * Add one class information to container by the namespace of class
     */
    public static function addByClass(string $namespace, string $realpath = null, string $domain = null)
    {
        if (! class_exists($namespace)) {
            exception('ClassAddingToContainerNotFound', ['class' => $namespace]);
        }

        $realpath = $realpath ?: get_file_of_namespace($namespace);
        if (! $realpath) {
            exception('ClassFileNotFound', ['class' => $namespace]);
        }
        $domain = $domain ?: DomainManager::getByFile($realpath);
        if (! $domain) {
            exception('DomainNotFound', ['filepath' => $realpath]);
        }

        self::$filenskv[$realpath] = $namespace;
        self::$classes[$namespace] = $class = [
            'filepath'    => $realpath,
            'domain'      => $domain,
            'namespace'   => $namespace,
            'constructor' => Reflector::getClassConstructor($namespace),
        ];

        return $class;
    }

    public static function getClass($ns)
    {
        return self::$classes[$ns] ?? null;
    }

    public static function getFilenskv()
    {
        return self::$filenskv;
    }

    public static function getInterfaces()
    {
        return self::$interfaces;
    }

    public static function getClasses()
    {
        return self::$classes;
    }
}
