<?php

/* This file is part of Entities.
 *
 * (c) 2012 Kevin Herrera
 *
 * For the full copyright and license information, please
 * view the LICENSE file that was distributed with this
 * source code.
 */

namespace KevinGH\Entities;

use PHPUnit_Framework_TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Makes testing internal properties and methods easier.
 *
 * @author Kevin Herrera <kherrera@codealchemy.com>
 */
abstract class InternalTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * Returns an accessible reflection method.
     *
     * @param object $object Some object.
     * @param string $method The method name.
     *
     * @return ReflectionMethod The reflection method.
     */
    protected function getMethod($object, $method)
    {
        $m = new ReflectionMethod($object, $method);

        $m->setAccessible(true);

        return $m;
    }

    /**
     * Returns an accessible reflection property.
     *
     * @param object $object   Some object.
     * @param string $property The property name.
     *
     * @return ReflectionMethod The reflection property.
     */
    protected function getProperty($object, $property)
    {
        $p = new ReflectionProperty($object, $property);

        $p->setAccessible(true);

        return $p;
    }
}
