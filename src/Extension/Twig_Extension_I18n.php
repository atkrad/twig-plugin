<?php

namespace Twig\Extension;

use Twig_Extension;
use Twig_SimpleFunction;

/**
 * Class Twig_Extension_I18n
 *
 * @package Twig\Extension
 */
class Twig_Extension_I18n extends Twig_Extension
{
    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'i18n';
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return parent::getFunctions() + [
            /**
             * Returns a translated string if one is found; Otherwise, the submitted message.
             */
            new Twig_SimpleFunction('__', function () {
                return call_user_func_array('__', func_get_args());
            }),
            /**
             * Returns correct plural form of message identified by $singular and $plural for count $count.
             * Some languages have more than one form for plural messages dependent on the count.
             */
            new Twig_SimpleFunction('__n', function () {
                return call_user_func_array('__n', func_get_args());
            }),
            /**
             * Allows you to override the current domain for a single message lookup.
             */
            new Twig_SimpleFunction('__d', function () {
                return call_user_func_array('__d', func_get_args());
            }),
            /**
             * Allows you to override the current domain for a single plural message lookup.
             * Returns correct plural form of message identified by $singular and $plural for count $count
             * from domain $domain.
             */
            new Twig_SimpleFunction('__dn', function () {
                return call_user_func_array('__dn', func_get_args());
            }),
            /**
             * Allows you to override the current domain for a single message lookup.
             * It also allows you to specify a category.
             */
            new Twig_SimpleFunction('__dc', function () {
                return call_user_func_array('__dc', func_get_args());
            }),
            /**
             * Allows you to override the current domain for a single plural message lookup.
             * It also allows you to specify a category.
             * Returns correct plural form of message identified by $singular and $plural for count $count
             * from domain $domain.
             */
            new Twig_SimpleFunction('__dcn', function () {
                return call_user_func_array('__dcn', func_get_args());
            }),
            /**
             * The category argument allows a specific category of the locale settings to be used for fetching a message.
             * Valid categories are: LC_CTYPE, LC_NUMERIC, LC_TIME, LC_COLLATE, LC_MONETARY, LC_MESSAGES and LC_ALL.
             */
            new Twig_SimpleFunction('__c', function () {
                return call_user_func_array('__c', func_get_args());
            }),
            /**
             * Returns a translated string if one is found; Otherwise, the submitted message.
             */
            new Twig_SimpleFunction('__x', function () {
                return call_user_func_array('__x', func_get_args());
            }),
        ];
    }
}

