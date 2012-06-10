<?php

namespace Sly\UrlShortenerBundle\Router;

/**
 * Router service interface.
 *
 * @author Cédric Dugat <ph3@slynett.com>
 */
interface RouterInterface
{
    /**
     * @param object $object Object
     * 
     * @return string
     */
    public function getObjectShowRoute($object);
}