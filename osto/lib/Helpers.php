<?php

namespace osto;



abstract class Helpers
{
    private static $cache = array();



    public static function fromCamelCase($name)
    {
        return \strtolower(\preg_replace('/(?<=[^_])([A-Z])/', '_\1', $name));
    }



    public static function toCamelCase($name)
    {
        if (!isset(self::$cache[$name])) {
            self::$cache[$name] = \preg_replace_callback('/(?<=[^_])_([^_])/', function($matches) {
                    return \strtoupper($matches[1]);
                }, $name);
        }

        return self::$cache[$name];
    }



    public static function setter($name)
    {
        return 'set' . \ucfirst(self::toCamelCase($name));
    }



    public static function getter($name)
    {
        return 'get' . \ucfirst(self::toCamelCase($name));
    }



    public static function fromGetter($name)
    {
        if (\preg_match('/^(is|has|get)(.*)$/', $name, $matches))
            return \lcfirst($matches[1]);
        else
            return FALSE;
    }

}

