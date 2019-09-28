<?php

declare(strict_types=1);

if (! function_exists('bomb_class')) {
    function bomb_object()
    {
        return new class {
            public function __get($key)
            {
                exit;
            }
            public function __call($method, $argvs)
            {
                exit;
            }
        };
    }
}
if (! function_exists('zombie_object')) {
    function zombie_object()
    {
        return new class {
            public function __get(string $key)
            {
                return $this;
            }
            public function __call(string $method, array $argvs = [])
            {
                return $this;
            }
        };
    }
}
if (! function_exists('pt')) {
    function pt(...$vars)
    {
        $trace = debug_backtrace();
        if ($last = ($trace[0] ?? null)) {
            extract($last);
            if ($file === __FILE__) {
                $last = $trace[1] ?? null;
            }
        }

        extract($last);
        print_r([sprintf('%s#%s:%s', $file, $line, $function), unsplat($vars)]);

        return bomb_object();
    }
}
if (! function_exists('pd')) {
    function pd(...$vars)
    {
        pt(...$vars)->die();
    }
}
if (! function_exists('et')) {
    function et(...$vars)
    {
        foreach ($vars as $var) {
            $next = next($vars);
            if (is_scalar($var)) {
                echo $var;
                if ($next) {
                    is_scalar($next)
                        ? print($next)
                        : print_r($next);

                    echo PHP_EOL;
                }
            } else {
                print_r($var);
            }
        }

        echo PHP_EOL;

        return bomb_object();
    }
}
if (! function_exists('ee')) {
    function ee(...$vars)
    {
        et(...$vars)->die();
    }
}
if (! function_exists('pp')) {
    function pp(...$vars)
    {
        $trace = debug_backtrace();
        if ($last = ($trace[0] ?? null)) {
            extract($last);
            if ($file === __FILE__) {
                $last = $trace[1] ?? null;
            }
        }

        extract($last);
        var_dump([
            sprintf('%s#%s:%s', $file, $line, $function),
            unsplat($vars),
        ]);

        return bomb_object();
    }
}
if (! function_exists('dd')) {
    function dd(...$vars)
    {
        pp(...$vars)->die();
    }
}
if (! function_exists('load_php')) {
    function load_php(string $path) : array
    {
        if (! is_file($path)) {
            return [];
        }

        $ret = include $path;

        return (array) $ret;
    }
}
if (! function_exists('list_dir')) {
    function list_dir(string $dir, \Closure $callback)
    {
        if (! is_dir($dir)) {
            exception('ListDirNotExists', ['dir' => $dir]);
        }

        $list = (array) scandir($dir);

        $callback($list, $dir);
    }
}
if (! function_exists('walk_dir_recursive')) {
    function walk_dir_recursive(string $dir, \Closure $callback)
    {
        walk_dir($dir, function ($path) use ($callback) {
            if (in_array($path->getFileName(), ['.', '..'])) {
                return;
            }
            if ($path->isDir()) {
                walk_dir_recursive($path->getRealpath(), $callback);
                return;
            }

            $callback($path);
        });
    }
}
if (! function_exists('walk_dir')) {
    function walk_dir(string $dir, \Closure $callback)
    {
        if (! is_dir($dir)) {
            exception('WalkDirNotExists', ['dir' => $dir]);
        }

        $fsi = new \FilesystemIterator($dir);
        foreach ($fsi as $path) {
            $callback($path);
        }

        unset($fsi);
    }
}
if (! function_exists('get_file_of_namespace')) {
    function get_file_of_namespace(string $ns)
    {
        if (! namespace_exists($ns)) {
            return false;
        }

        return (new ReflectionClass($ns))->getFileName();
    }
}
if (! function_exists('get_namespace_of_file')) {
    function get_namespace_of_file(string $path, bool $withClass = false)
    {
        if (! is_file($path)) {
            return false;
        }

        $tokens = token_get_all(php_strip_whitespace($path));
        $cnt = count($tokens);
        $ns  = $cn = '';
        $nsIdx = $cnIdx = 0;
        $findingNS = $findingCN = true;
        for ($i = 0; $i < $cnt; ++$i) {
            $token = $tokens[$i] ?? false;
            $tname = $token[0] ?? false;
            if ($findingNS && ($tname === T_NAMESPACE)) {
                $nsIdx = $i;
                $findingNS = false;
                continue;
            }
            if ($findingCN && in_array($tname, [T_CLASS, T_INTERFACE, T_TRAIT])) {
                $cnIdx = $i;
                $findingCN = false;
                continue;
            }
        }
        if ($findingNS === false) {
            for ($j = $nsIdx; $j < $cnt; ++$j) {
                $token = $tokens[$j + 1] ?? false;
                if ($token === ';') {
                    break;
                }
                $ns .= ($token[1] ?? '');
            }
        }
        $ns = trim($ns);
        if (! $ns) {
            return false;
        }
        if (! $withClass) {
            return $ns ?: '\\';
        }
        $cnLine = [];
        if ($findingCN === false) {
            for ($k = $cnIdx; $k < $cnt; ++$k) {
                $token = $tokens[$k + 1] ?? false;
                if ($token === '{') {
                    break;
                }
                $cnLine[] = ($token[1] ?? '');
            }
        }
        $cnLine = array_values(array_filter($cnLine, function ($item) {
            return ! empty(trim($item));
        }));
        $cn = $cnLine[0] ?? '';
        if (! $cn) {
            return false;
        }
        $cn = join('\\', [$ns, $cn]);

        return (class_exists($cn) || interface_exists($cn) || trait_exists($cn)) ? $cn : false;
    }
}
if (! function_exists('get_used_classes')) {
    /**
     * Get classes used by class or interface
     *
     * @param string $target
     * @param bool $namespace: if target is a namespace
     * @return array|null
     */
    function get_used_classes(string $target, bool $namespace = true) : ?array
    {
        if ($namespace && (! ($target = get_file_of_namespace($target)))) {
            return null;
        }
        if (! is_file($target)) {
            return null;
        }

        $tokens = token_get_all(php_strip_whitespace($target));
        $usedClasses = [];
        $foundNamespace = false;
        $findingUsedClass = false;
        $findingAlias = false;
        $usedClass = [];
        foreach ($tokens as $token) {
            $tokenId = $token[0] ?? false;
            $hasNamespace = $tokenId === T_NAMESPACE;
            if ((! $foundNamespace) && (! $hasNamespace)) {
                continue;
            } else {
                $foundNamespace = true;
            }

            $foundClassname = $tokenId === T_CLASS;
            if ($foundClassname) {
                break;
            }
            if ($tokenId === T_USE) {
                $findingUsedClass = true;
                $findingAlias = false;
                continue;
            }
            if ($findingUsedClass) {
                if ($token === ';') {
                    $findingUsedClass = false;
                    if ($usedClass) {
                        $ns = join('', $usedClass['nspath']);
                        $alias = $usedClass['alias'] ?? null;
                        $usedClasses[$ns] = $alias;
                        $usedClass = [];
                    }
                    continue;
                }
                if (($tokenId !== T_WHITESPACE) && ($tokenName = ($token[1] ?? false))) {
                    if ($tokenId === T_AS) {
                        $findingAlias = true;
                        continue;
                    }

                    if ($findingAlias) {
                        $usedClass['alias'] = $tokenName;
                    } else {
                        $usedClass['nspath'][] = $tokenName;
                    }
                }
            }
        }

        return $usedClasses;
    }
}
if (! function_exists('arr2xml')) {
    function arr2xml(array $array, string &$xml) : string
    {
        foreach ($array as $key => &$val) {
            if (is_array($val)) {
                $_xml = '';
                $val  = arr2xml($val, $_xml);
            }
            $xml .= "<{$key}>{$val}</{$key}>";
        }
        unset($val);
        return $xml;
    }
}
if (! function_exists('enxml')) {
    // TODO&FIXME
    function enxml($data, bool $header = true)
    {
        $xml = $_xml = '';
        if (true
            && is_object($data)
            && (
                false
            || (method_exists($data, '__toArray') && is_array($ret = $data->__toArray()))
            || (method_exists($data, 'toArray') && is_array($ret = $data->toArray()))
            )
        ) {
            $_xml = arr2xml($ret, $xml);
        } elseif (is_array($data)) {
            $_xml = arr2xml($data, $xml);
        }
        unset($xml);

        $__xml = $header ? '<?xml version="1.0" encoding="utf-8"?>' : '';
        $__xml .= '<xml>'.$_xml.'</xml>';
        return $__xml;
    }
}
if (! function_exists('dexml')) {
    function dexml(string $xml, bool $loaded = false, bool $file = false) : array
    {
        if ($file) {
            if (is_file($xml)) {
                $xml = file_get_contents($xml);
                $loaded = false;
            }

            exception('XmlFileNotExists', ['file' => $xml]);
        }

        if (! extension_loaded('libxml')) {
            exception('MissingPHPExtension', ['extension' => 'libxml']);
        }
        
        libxml_use_internal_errors(true);
        $xml = $loaded
        ? $xml
        : simplexml_load_string(
            $xml,
            'SimpleXMLElement',
            LIBXML_NOCDATA
        );
        if (($error = libxml_get_last_error()) && isset($error->message)) {
            libxml_clear_errors();
            exception('IllegalXMLformat', ['xml' => $xml, 'error' => $error->message]);
        }

        return dejson(enjson($xml), true);
    }
}
if (! function_exists('json_pretty')) {
    function json_pretty($data) : string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if (! is_string($json)) {
            $error = JSON_ERROR_NONE === json_last_error() ? 'UnknownJsonEncodeError' : json_last_error_msg();

            trigger_error($error);

            return '';
        }

        return $json;
    }
}
if (! function_exists('xml_pretty')) {
    function xml_pretty($data) : string
    {
        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML(enxml($data, false));
        $dom->formatOutput = true;

        return $dom->saveXML();
    }
}
if (! function_exists('enjson')) {
    function enjson($data)
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return $json;
            case JSON_ERROR_DEPTH:
                return exception('JsonErrorDepth');
            case JSON_ERROR_STATE_MISMATCH:
                return exception('JsonErrorStateMismatch');
            case JSON_ERROR_CTRL_CHAR:
                return exception('JsonErrorCtrlChar');
            case JSON_ERROR_SYNTAX:
                return exception('JsonErrorSyntax');
            case JSON_ERROR_RECURSION:
                return exception('JsonErrorRecursion');
            case JSON_ERROR_INF_OR_NAN:
                return exception('JsonErrorInfOrNan');
            case JSON_ERROR_UNSUPPORTED_TYPE:
                return exception('JsonErrorUnsupportedType');
            case JSON_ERROR_INVALID_PROPERTY_NAME:
                return exception('JsonErrorUnsupportedType');
            case JSON_ERROR_UTF16:
                return exception('JsonErrorUTF16');
            case JSON_ERROR_UTF8:
                return exception('JsonErrorUTF8');
            default:
                return exception('UnknownJsonEncodeError');
        }

        return $json ?: '';
    }
}
if (! function_exists('dejson')) {
    function dejson(string $json, bool $assoc = true, bool $file = false)
    {
        if ($file) {
            if (! is_file($json)) {
                exception('JsonFileNotExists', ['file' => $json]);
            }

            $json = file_get_contents($json);
        }

        $res = json_decode($json, $assoc);

        return (is_array($res) || is_object($res)) ? $res : [];
    }
}
if (! function_exists('subsets')) {
    // See: <https://stackoverflow.com/questions/6092781/finding-the-subsets-of-an-array-in-php>
    function subsets(array $data, int $minLen = 1) : array
    {
        $count  = count($data);
        $times  = pow(2, $count);
        $result = [];
        for ($i = 0; $i < $times; ++$i) {
            // $bin = sprintf('%0'.$count.'b', $i);
            $tmp = [];
            for ($j = 0; $j < $count; ++$j) {
                // Use bitwise operation is more faster than sprintf
                if ($i >> $j & 1) {
                    // if ('1' == $bin{$j}) {    // get NO.$j letter in string $bin
                    $tmp[$j] = $data[$j];
                }
            }
            if (count($tmp) >= $minLen) {
                $result[] = $tmp;
            }
        }

        return $result;
    }
}
if (! function_exists('array_unique_merge')) {
    function array_unique_merge(...$arrs) : array
    {
        if (count($arrs) === 0) {
            return [];
        }

        return array_unique((array) array_merge(...$arrs));
    }
}
if (! function_exists('array_trim')) {
    function array_trim(array $arr, bool $preserveKeys = false) : array
    {
        array_filter($arr, function (&$val, $key) use (&$arr) {
            if (! is_scalar($val)) {
                return true;
            }
            $val = trim($val);
            if (empty($val)) {
                if (is_numeric($val)) {
                    return true;
                }

                unset($arr[$key]);
                return false;
            }

            $arr[$key] = $val;
            return true;
        }, ARRAY_FILTER_USE_BOTH);

        return $preserveKeys ? $arr : array_values($arr);
    }
}
if (! function_exists('array_trim_from_string')) {
    function array_trim_from_string(string $str, string $explode = ',') : array
    {
        $str = trim($str);
        $arr = explode($explode, $str);

        return array_trim($arr);
    }
}
if (! function_exists('array_stringify')) {
    function array_stringify(array $arr)
    {
        $level = 1;
        $str   = "[\n";
        $str  .= array_stringify_main($arr, $level);
        $str  .= ']';

        return $str;
    }
}
if (! function_exists('array_stringify_main')) {
    function array_stringify_main(array $arr, &$level)
    {
        $str = '';
        $margin = str_repeat("\t", $level++);
        foreach ($arr as $key => $val) {
            $key  = is_int($key) ? $key : "'{$key}'";
            $str .= $margin.$key.' => ';
            if (is_array($val)) {
                $str .= "[\n";
                $str .= array_stringify_main($val, $level);
                $str .= $margin."],\n";
                --$level;
            } else {
                if (is_string($val)) {
                    $val  = "'".addslashes($val)."'";
                } elseif (is_numeric($val)) {
                    $val = $val;
                } elseif (is_bool($val)) {
                    $val = $val ? 'true' : 'false';
                } elseif (is_null($val)) {
                    $val = 'null';
                } else {
                    $val  = "'".addslashes(stringify($val))."'";
                }

                $str .= $val.",\n";
            }
        }
        return $str;
    }
}
if (! function_exists('array2code')) {
    function array2code(array $data, string $path, bool $strip = true)
    {
        $code = array_stringify($data);
        $code = <<<ARR
<?php

return {$code};\n
ARR;
        file_put_contents($path, $code);
        if ($strip) {
            file_put_contents($path, php_strip_whitespace($path));
        }
    }
}
if (! function_exists('array_append_dynamic')) {
    function array_append_dynamic(array $data, $append, array $indexes)
    {
        $keys = $indexes;
        foreach ($indexes as $idx => $key) {
            $_data = $data[$key] ?? [];
            unset($keys[$idx]);
            if (false === next($indexes)) {
                $data[$key][] = $append;
            } else {
                $data[$key] = array_append_dynamic($_data, $append, $keys);
            }
            break;
        }

        return $data;
    }
}
if (! function_exists('array_unset')) {
    function array_unset(array &$data, ...$keys)
    {
        foreach ($keys as $key) {
            unset($data[$key]);
        }
    }
}
if (! function_exists('is_index_array')) {
    function is_index_array($val) : bool
    {
        if (! is_array($val)) {
            return false;
        }

        if ([] === $val) {
            return false;
        }

        return array_keys($val) === range(0, (count($val) - 1));
    }
}
if (! function_exists('is_assoc_array')) {
    function is_assoc_array(array $arr) : bool
    {
        if ([] === $arr) {
            return false;
        }

        return count(array_filter(array_keys($arr), 'is_string')) === count($arr);
    }
}
if (! function_exists('stringify')) {
    function stringify($value)
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_null($value)) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return enjson($value);
        }
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $res = $value->__toString();
                if (is_scalar($res)) {
                    return (string) $res;
                }
            }
            if (method_exists($value, '__toArray')) {
                $res = $value->__toArray();
                if (is_array($res)) {
                    return enjson($res);
                }
            }

            return get_class($value);
        }

        return '__UNSTRINGABLE_VALUE__';
    }
}
if (! function_exists('string_literal')) {
    function string_literal($val)
    {
        if (is_null($val)) {
            return 'null';
        }
        if (is_bool($val)) {
            return ($val ? 'true' : 'false');
        }
        if (is_scalar($val)) {
            return (string) $val;
        }
        if (is_array($val)) {
            return enjson($val);
        }
        if ($val instanceof \Closure) {
            return 'closure';
        }
        if (is_object($val)) {
            return get_class($val);
        }

        return '__UNKNOWN_VARIABLE_TYPE__';
    }
}
if (! function_exists('is_xml')) {
    function is_xml(string $xml, bool $returnError = false)
    {
        libxml_use_internal_errors(true);
        if (! ($doc = simplexml_load_string(
            $xml,
            'SimpleXMLElement',
            LIBXML_NOCDATA
        ))) {
            $error = libxml_get_last_error();    // LibXMLError object
            libxml_clear_errors();
            if ($error !== false) {
                return $returnError ? $error->message : false;
            }
        }

        return true;
    }
}
if (! function_exists('ci_equal')) {
    function ci_equal($a, $b) : bool
    {
        if ((! is_string($a)) && (! is_numeric($a))) {
            return false;
        }
        if ((! is_string($b)) && (! is_numeric($b))) {
            return false;
        }

        return strtolower(strval($b)) === strtolower(strval($a));
    }
}
if (! function_exists('ciin')) {
    function ciin($value, array $list, bool $convert = true) : bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        $value = strtolower((string) $value);

        if ($convert) {
            $list = array_map(function ($item) {
                return strtolower((string) $item);
            }, $list);
        }

        return in_array($value, $list);
    }
}
if (! function_exists('ciins')) {
    function ciins(array $value, array $list) : bool
    {
        $list = array_map(function ($item) {
            return strtolower((string) $item);
        }, $list);

        foreach ($value as $val) {
            if (! ciin($val, $list, false)) {
                return false;
            }
        }

        return true;
    }
}
if (! function_exists('classname')) {
    function classname($class) : ?string
    {
        if (! is_string($class)) {
            return null;
        }
        if (! class_exists($class)) {
            return null;
        }

        $ns = array_trim_from_string($class, '\\');

        return $ns[count($ns) - 1] ?? null;
    }
}
if (! function_exists('objectname')) {
    function objectname($object) : ?string
    {
        if (is_object($object)) {
            return (new \ReflectionClass($object))->getShortName();
        }
    }
}
if (! function_exists('ospath')) {
    function ospath(...$items) : string
    {
        $path = '';

        ospath_main($items, $path);

        return $path;
    }
}
if (! function_exists('ospath_main')) {
    function ospath_main(array $items, string &$path = '')
    {
        foreach ($items as $item) {
            if (is_scalar($item)) {
                $item = (string) $item;
                $join = ($path === '') ? [$item] : [$path, $item];
                $path = join(DIRECTORY_SEPARATOR, $join);
                continue;
            }
            if (is_array($item)) {
                ospath_main($item, $path);
                continue;
            }
        }
    }
}
if (! function_exists('fdate')) {
    function fdate(int $ts = null, string $format = null) : string
    {
        $format = $format ?: 'Y-m-d H:i:s';
        $ts = $ts ?: time();

        return date($format, $ts);
    }
}
if (! function_exists('microftime')) {
    function microftime(string $format = 'Y-m-d H:i:s', string $separate = '.', $raw = null) : string
    {
        $raw = $raw ? $raw : microtime(true);
        $mts = explode('.', (string) $raw);
        $ts = intval($mts[0] ?? time());
        $ms = intval($mts[1] ?? 0);

        return join($separate, [date($format, $ts), $ms]);
    }
}
if (! function_exists('timezone')) {
    /**
     * Get timezone abbreviation
     */
    function timezone(string $tz = null) : string
    {
        $tz = $tz ?: date_default_timezone_get();
        $dt = (new \Datetime())->setTimeZone((new \DateTimeZone($tz)));

        return $dt->format('T');
    }
}
if (! function_exists('is_date_format')) {
    function is_date_format(string $date, string $format = 'Y-m-d H:i:s') : bool
    {
        $dt = DateTime::createFromFormat($format, $date);

        return $dt && ($dt->format($format) == $date);
    }
}
if (! function_exists('is_closure')) {
    function is_closure($val) : bool
    {
        return is_object($val) && ($val instanceof \Closure);
    }
}
if (! function_exists('array_get_by_chain_key')) {
    function array_get_by_chain_key(
        $haystack,
        string $key = null,
        string $explode = '.',
        $default = null
    ) {
        if ((! $haystack) || is_null($key) || ($key === '') || ((! is_array($haystack)) && (! is_object($haystack)))) {
            return $default;
        }
        if (is_object($haystack)) {
            if (method_exists($haystack, 'toArray')) {
                $haystack = $haystack->toArray();
            } elseif (method_exists($haystack, '__toArray')) {
                $haystack = $haystack->__toArray();
            } else {
                return $default;
            }
        }
        if (array_key_exists($key, $haystack)) {
            return $haystack[$key] ?? $default;
        }

        $chain  = $_chain = array_trim_from_string($key, $explode);
        $query  = null;
        $tmparr = $haystack;
        foreach ($chain as $idx => $k) {
            $query = ($tmparr = ($tmparr[$k] ?? null));
            if ($query) {
                unset($_chain[$idx]);
                $key = join($explode, $_chain);
                return array_get_by_chain_key($query, $key, $explode);
            }
        }

        return is_null($query) ? $default : $query;
    }
}
if (! function_exists('is_throwable')) {
    function is_throwable($throwable) : bool
    {
        return is_object($throwable) && ($throwable instanceof \Throwable);
    }
}
if (! function_exists('is_anonymous')) {
    function is_anonymous($instance) : bool
    {
        return is_object($instance) && (new ReflectionClass($instance))->isAnonymous();
    }
}
if (! function_exists('parse_throwable')) {
    /**
     * Parse throwable and get context as previous
     *
     * @param \Throwable $throwable
     * @param array|null $context: Context saver
     */
    function parse_throwable(?\Throwable $throwable, ?array $context = []) : ?array
    {
        if (! is_throwable($throwable)) {
            return $context;
        }

        $_context = method_exists($throwable, 'getContext') ? $throwable->getContext() : [];
        $name = objectname($throwable);
        $file = $throwable->getFile();
        $line = $throwable->getLine();
        if (is_anonymous($throwable)) {
            $previous = $throwable->getTrace()[0] ?? [];
            $file = $previous['file'] ?? null;
            $line = $previous['line'] ?? null;
            $name = method_exists($throwable, 'getName') ? $throwable->getName() : $throwable->getMessage();
        }
        $_context['__name'] = $name;
        $_context['__info'] = $throwable->getMessage();
        $_context['__file'] = $file;
        $_context['__line'] = $line;

        if (is_null($context)) {
            return $_context;
        }

        $context['__trace']    = explode(PHP_EOL, $throwable->getTraceAsString());
        $context['__previous'] = $_context;

        return $context;
    }
}
if (! function_exists('exception')) {
    function exception(
        string $name,
        array $context = [],
        Throwable $previous = null
    ) {
        throw new class($name, $context, $previous) extends \Exception {
            protected $name;
            protected $context = [];
            public function __construct(
                string $name,
                array $context = [],
                Throwable $previous = null
            ) {
                $last = debug_backtrace()[1] ?? [];
                $file = $last['file'] ?? '?';
                $line = $last['line'] ?? '?';

                $this->name = $name;
                $this->message = "{$name}: {$file}#{$line}";
                $this->context = parse_throwable($previous, $context);
            }
            public function getContext() : array
            {
                return $this->context;
            }
            public function getName()
            {
                return $this->name;
            }
        };
    }
}
if (! function_exists('redirect')) {
    function redirect(string $url, int $status = 302) : array
    {
        header('Location: '.$url, true, $status);

        exit;
    }
}
if (! function_exists('getallheaders')) {
    // For nginx, compatible with apache format
    function getallheaders() : array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (mb_substr($name, 0, 5) === 'HTTP_') {
                $headers[mb_substr($name, 5)] = $value;
            }
        }
        return $headers;
    }
}
if (! function_exists('is_function_disabled')) {
    function is_function_disabled(string $name)
    {
        return in_array($name, explode(',', ini_get('disable_functions')));
    }
}
if (! function_exists('chownr')) {
    function chownr($path, string $owner)
    {
        if (is_function_disabled('chown') || is_function_disabled('lchown')) {
            return;
        }

        if (is_link($path) && is_writable($path)) {
            @lchown($path, $owner);
        }
        if (is_file($path) && is_writable($path)) {
            @chown($path, $owner);
            return;
        }
        if (! is_dir($path)) {
            return;
        }

        list_dir($path, function ($list) use ($path, $owner) {
            foreach ($list as $file) {
                if ($file === '.' || '..' === $file) {
                    continue;
                }
                $_path = ospath($path, $file);
                if (is_dir($_path)) {
                    chownr($_path, $owner);
                } elseif (is_link($_path) && is_writable($path)) {
                    @lchown($_path, $owner);
                } elseif (is_file($_path) && is_writable($path)) {
                    @chown($_path, $owner);
                }
            }
        });
    }
}
if (! function_exists('chgrpr')) {
    function chgrpr($path, string $group)
    {
        if (is_function_disabled('lchgrp') || is_function_disabled('chgrp')) {
            return;
        }

        if (is_link($path) && is_writable($path)) {
            @lchgrp($path, $mode);
            return;
        }
        if (is_file($path) && is_writable($path)) {
            @chgrp($path, $group);
            return;
        }
        if (! is_dir($path)) {
            return;
        }

        list_dir($path, function ($list) use ($path, $group) {
            foreach ($list as $file) {
                if ($file === '.' || '..' === $file) {
                    continue;
                }
                $_path = ospath($path, $file);
                if (is_dir($_path)) {
                    chgrpr($_path, $group);
                } elseif (is_link($_path) && is_writable($_path)) {
                    @lchgrp($_path, $group);
                } elseif (is_file($_path) && is_writable($_path)) {
                    @chown($_path, $group);
                }
            }
        });
    }
}
if (! function_exists('chmodr')) {
    function chmodr($path, int $mode)
    {
        if (is_function_disabled('chmod')) {
            return;
        }

        if (is_file($path) && is_writable($path)) {
            @chmod($path, $mode);
            return;
        }
        if (! is_dir($path)) {
            return;
        }

        list_dir($path, function ($list) use ($path, $mode) {
            foreach ($list as $file) {
                if ($file === '.' || '..' === $file) {
                    continue;
                }
                $_path = ospath($path, $file);
                if (is_dir($_path)) {
                    chmodr($_path, $mode);
                } elseif (is_file($_path) && is_writable($_path)) {
                    @chmod($_path, $mode);
                }
            }
        });
    }
}
if (! function_exists('instance_of')) {
    function instance_of(string $child = null, string $parent = null) : bool
    {
        if ((! $child) || (! $parent)) {
            return false;
        }

        $childIsClass      = class_exists($child);
        $childIsInterface  = interface_exists($child);
        $parentIsClass     = class_exists($parent);
        $parentIsInterface = interface_exists($parent);
        if ((! $childIsClass) && (! $childIsInterface)) {
            return false;
        }
        if ((! $parentIsClass) && (! $parentIsInterface)) {
            return false;
        }

        if ($childIsClass) {
            if ($parentIsClass) {
                return is_subclass_of($child, $parent);
            }
            if ($parentIsInterface) {
                return is_subinterface_of($child, $parent);
            }
        }
        if ($childIsInterface) {
            if ($parentIsClass) {
                return false;
            }
            return is_subinterface_of($child, $parent);
        }

        return false;
    }
}
if (! function_exists('is_subinterface_of')) {
    function is_subinterface_of(string $child, string $parent) : bool
    {
        if (! interface_exists($child)) {
            return false;
        }
        if (! interface_exists($parent)) {
            return false;
        }

        $reflector = new \ReflectionClass($child);

        return $reflector->implementsInterface($parent);
    }
}
if (! function_exists('get_php_user')) {
    function get_php_user() : string
    {
        if (extension_loaded('posix')) {
            $user = posix_getpwuid(posix_geteuid())['name'] ?? 'nobody';
        } else {
            $user = get_current_user();
        }

        return $user ? $user : 'nobody';
    }
}
if (! function_exists('unsplat')) {
    function unsplat($params)
    {
        return $params;
    }
}
if (! function_exists('to_array')) {
    function to_array($value) : array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return [$value];
        }
        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return to_array($value->toArray());
            }
            if (method_exists($value, '__toArray')) {
                return to_array($value->__toArray());
            }

            return [$value];
        }
    }
}
if (! function_exists('format_bytes')) {
    function format_bytes(int $bytes) : string
    {
        $s = array('B', 'Kb', 'MB', 'GB', 'TB', 'PB');
        $e = floor(log($bytes)/log(1024));
      
        return sprintf('%.6f '.$s[$e], ($bytes/pow(1024, floor($e))));
    }
}
if (! function_exists('get_os_bit_ver')) {
    function get_os_bit_ver()
    {
        return (PHP_INT_SIZE / 4) * 32;
    }
}
if (! function_exists('is_timestamp')) {
    function is_timestamp($var) : bool
    {
        if (! is_numeric($var)) {
            return false;
        }

        $_var = intval($var);
        if ($_var != $var) {
            return false;
        }

        return (-2147483649 <= $var) && ($_var <= 2147483649);
    }
}
if (! function_exists('debase64')) {
    function debase64(string $base64, bool $urlsafe = false) : string
    {
        if ($urlsafe) {
            return base64_decode(str_pad(strtr($base64, '-_', '+/'), strlen($base64) % 4, '=', STR_PAD_RIGHT));
        }

        return base64_decode($base64);
    }
}
if (! function_exists('enbase64')) {
    function enbase64(string $text, bool $urlsafe = false) : string
    {
        if ($urlsafe) {
            return rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
        }

        return base64_encode($text);
    }
}
if (! function_exists('get_buffer_string')) {
    function get_buffer_string(\Closure $action, \Closure $exception = null) : string
    {
        try {
            $level = ob_get_level();
            ob_start();

            $action();

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            if ($exception) {
                $exception($e);
            } else {
                exception('GetBufferStringException', [], $e);
            }
        }

        return '';
    }
}
if (! function_exists('render')) {
    function render(array $data, string $tpl, \Closure $exception = null) : string
    {
        if (! is_file($tpl)) {
            exception('RenderTemplateNotExists', compact('tpl'));
        }

        return get_buffer_string(function () use ($data, $tpl) {
            extract($data, EXTR_OVERWRITE);

            include $tpl;
        }, $exception);
    }
}
if (! function_exists('render_to')) {
    function render_to(array $data, string $tpl, string $dest, \Closure $exception = null)
    {
        save($dest, render($data, $tpl, $exception));
    }
}
if (! function_exists('save')) {
    function save(string $path, string $str, int $flag = 0)
    {
        $save = dirname($path);
        if (! is_dir($save)) {
            mkdir($save, 0775, true);
        }

        file_put_contents($path, $str, $flag);
    }
}
if (! function_exists('get_last_trace')) {
    function get_last_trace() : array
    {
        $trace = debug_backtrace();
        $last  = $trace[1] ?? [];

        array_unset($last, 'type', 'args');

        return $last;
    }
}
if (! function_exists('namespace_exists')) {
    function namespace_exists(string $ns = null) : bool
    {
        return $ns && (class_exists($ns) || interface_exists($ns) || trait_exists($ns));
    }
}
if (! function_exists('get_class_consts')) {
    function get_class_consts(string $ns) : ?array
    {
        if (! namespace_exists($ns)) {
            return null;
        }

        return (new \ReflectionClass($ns))->getConstants();
    }
}
if (! function_exists('fixed_string')) {
    function fixed_string(string $raw, int $limit, string $fill = ' ...... ') : string
    {
        $len = mb_strlen($fill);
        if ($limit < 1) {
            return '';
        }
        if ($limit < $len) {
            return mb_substr($raw, 0, $limit);
        }

        $length = mb_strlen($raw);
        if ($length <= ($len-2)) {
            return $raw;
        }
        if ($length <= $limit) {
            return $raw;
        }

        $limit = $limit - $len;

        $half = intval(abs(floor($limit/2)));

        $res  = mb_substr($raw, 0, $limit - $half);
        $res .= $fill;
        $res .= mb_substr($raw, -($half), $half);

        return $res;
    }
}
if (! function_exists('http_client_ip')) {
    function http_client_ip(bool $forward = false) : ?string
    {
        $try_client_ip_key = function ($residence) {
            return getenv($residence)
            ?: (
                $_SERVER[$residence] ?? null
            );
        };

        $keys = $forward ? [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
        ] : [
            'REMOTE_ADDR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
        ];

        foreach ($keys as $residence) {
            if ($ip = $try_client_ip_key($residence)) {
                return $ip;
            }
        }

        return null;
    }
}
if (! function_exists('http_client_os')) {
    function http_client_os() : string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (! ini_get('browscap')) {
            return 'unknown';
        }

        $browser = get_browser($ua);

        return $browser->platform ?? 'unknown';
    }
}
if (! function_exists('http_client_name')) {
    function http_client_name() : string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (! ini_get('browscap')) {
            return 'unknown';
        }

        $browser = get_browser($ua);

        return $browser->browser ?? 'unknown';
    }
}
if (! function_exists('swap')) {
    function swap(&$a, &$b)
    {
        $c = $a;
        $a = $b;
        $b = $c;
    }
}
if (! function_exists('path2ns')) {
    function path2ns(string $path, bool $full = false)
    {
        if ($full) {
            return str_replace(DIRECTORY_SEPARATOR, '\\', $path);
        }

        $namespace = str_replace(DIRECTORY_SEPARATOR, '\\', dirname($path));
        if ($namespace === '.') {
            return '';
        }

        return '\\'.$namespace;
    }
}
if (! function_exists('confirm')) {
    function confirm($value) : bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        $value = strval($value);

        return ciin($value, ['1', 'true', 'yes', 'y']);
    }
}
if (! function_exists('in_list')) {
    function in_list(string $value, string $list, string $separator = ',') : bool
    {
        return in_array($value, array_trim_from_string($list, $separator));
    }
}
if (! function_exists('char_case_is')) {
    function char_case_is(string $char, $case = 'upper') : bool
    {
        if (! in_array($case, ['upper', 'lower'])) {
            return false;
        }

        if ('upper' === $case) {
            $asciiStart = 64;
            $asciiEnd   = 91;
            $preg = '/[A-Z]/u';
        } else {
            $asciiStart = 96;
            $asciiEnd   = 123;
            $preg = '/[a-z]/u';
        }

        $arr = str_split($char);
        if (1 === count($arr)) {
            $ascii = ord($char);
            // A-Z ASCII number range => 65~90
            // a-z ASCII number range => 97~122
            return (
                (($asciiStart < $ascii) && ($ascii < $asciiEnd))
                || preg_match($preg, $char)
            );
        }

        return false;
    }
}
if (! function_exists('ucase_char')) {
    function ucase_char(string $char) : bool
    {
        return char_case_is($char, 'upper');
    }
}
if (! function_exists('lcase_char')) {
    function lcase_char(string $char) : bool
    {
        return char_case_is($char, 'lower');
    }
}
if (! function_exists('underline2camelcase')) {
    function underline2camelcase(string $underline = null) : string
    {
        if (! $underline) {
            return '';
        }
        $arr = str_split($underline);
        $len = count($arr);

        if ('_' != $arr[0]) {
            $arr[0] = strtoupper($arr[0]);
        }
        foreach ($arr as $key => $val) {
            if ('_' == $val) {
                if (($key < ($len-1))
                    && ('_' != $arr[$key+1])
                ) {
                    $arr[$key+1] = strtoupper($arr[$key+1]);
                }
                $arr[$key] = '';
            }
            // $camelcase .= $arr[$key];    // slower than implode()
        }
        return implode('', $arr);
    }
}
if (! function_exists('camelcase2underline')) {
    function camelcase2underline(string $camelcase) : string
    {
        $arr = str_split($camelcase);
        foreach ($arr as $key => $val) {
            if (0 == $key) {
                $arr[0] = strtolower($val);
            } else {
                if (ucase_char($val)) {
                    $arr[$key] = '_'.strtolower($val);
                }
            }
        }

        return implode('', $arr);
    }
}
if (! function_exists('milliseconds')) {
    function milliseconds() : int
    {
        return intval(microtime(true) * 1000);
    }
}
if (! function_exists('microseconds')) {
    function microseconds() : int
    {
        return intval(microtime(true) * 1000000);
    }
}
if (! function_exists('nanoseconds')) {
    function nanoseconds() : int
    {
        return intval(microtime(true) * 1000000000);
    }
}
