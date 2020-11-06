<?php

declare(strict_types=1);

namespace DOF\Traits;

use DOF\Container;
use DOF\Util\IS;
use DOF\Util\Str;
use DOF\Util\Arr;
use DOF\Util\XML;
use DOF\Util\JSON;
use DOF\Util\Annotation;

trait ObjectData
{
    public function __wakeup()
    {
        if (\method_exists($this, '__construct')) {
            $this->__construct(...Container::build(static::class, '__construct'));
        }
    }

    public function __sleep()
    {
        // https://www.php.net/manual/en/language.oop5.magic.php#object.sleep
        return \array_keys($this->__data__());
    }

    final public function __toString()
    {
        return \serialize($this);
    }

    final public function __toArray() : array
    {
        return \get_object_vars($this);
    }

    final public function __trim__(object $object = null) : object
    {
        $object = $object ?? $this;
        if (IS::closure($object)) {
            return $object;
        }

        list(, $properties, ) = Annotation::getByNamespace(\get_class($object));
        $_object = clone $object;

        foreach (\get_object_vars($object) as $key => $value) {
            if ($properties[$key] ?? null) {
                continue;
            }

            unset($_object->{$key});
        }

        return $_object;
    }

    final public function __data__(object $object = null) : array
    {
        $object = $object ?? $this;

        if (IS::closure($object)) {
            return [];
        }

        $data = [];
        list(, $properties, ) = Annotation::getByNamespace(\get_class($object));

        // Warning: \get_object_vars can only get public/protected properties
        // \get_object_vars($this) -> pubilc & protected
        // \get_object_vars($object) -> pubilc
        // see: https://www.php.net/manual/en/function.get-object-vars
        foreach (\get_object_vars($object) as $key => $value) {
            if ($properties[$key] ?? null) {
                $data[$key] = \is_object($value) ? $this->__data__($value) : $value;
            }
        }

        return $data;
    }

    final public function __toXml()
    {
        return XML::encode($this->__data__());
    }

    final public function __toJson()
    {
        return JSON::encode($this->__data__());
    }
}
