<?php

namespace Twig\Lib;

/**
 * Class StaticCaller
 *
 * @package Twig\Lib
 */
class StaticCaller
{
    protected $className;

    public function __construct($className)
    {
        $this->className = $className;
    }

    public function __call($method, $arguments)
    {
        return call_user_func_array($this->className . '::' . $method, $arguments);
    }
}