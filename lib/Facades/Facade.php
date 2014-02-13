<?php namespace SimpleAR\Facades;

use \SimpleAR;

class Facade
{
    private static $_root;

    public static function bind(SimpleAR $root)
    {
        self::$_root = $root;
    }

    public static function __callStatic($method, $args)
    {
        $instance = self::$_root->{static::$_accessor};

        return call_user_func_array(array($instance, $method), $args);
    }
}
