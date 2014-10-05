<?php

namespace Twig\Extension;

use Twig_Extension;
use Twig_SimpleFilter;
use Twig_SimpleFunction;

/**
 * Class Twig_Extension_Basic
 *
 * @package Twig\Extension
 */
class Twig_Extension_Basic extends Twig_Extension
{
    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'basic';
    }

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        return parent::getFilters() + [
            new Twig_SimpleFilter('h', [$this, 'convenienceHtmlSpecialChars'])
        ];
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return parent::getFunctions() + [

            new Twig_SimpleFunction('h', [$this, 'convenienceHtmlSpecialChars']),
            new Twig_SimpleFunction('staticCall', function ($classNamespace, $method) {
                $args = func_get_args();
                unset($args[0]);
                unset($args[1]);

                return call_user_func_array($classNamespace . '::' . $method, $args);
            })
        ];
    }

    /**
     * Convenience method for htmlspecialchars.
     *
     * @return string Wrapped text
     */
    public function convenienceHtmlSpecialChars()
    {
        return call_user_func_array('h', func_get_args());
    }
}
