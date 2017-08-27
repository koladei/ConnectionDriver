<?php

namespace com\mainone\middleware;
include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/MiddlewareFilterBase.php');
use com\mainone\middleware\MiddlewareFilterBase;

interface IMiddlewareFilterGroup {
   
    const FRAGMENT_OR = 0;
    const FRAGMENT_AND = 1;

    public function addPart(MiddlewareFilterBase &$fragment, $type = self::FRAGMENT_AND);
    public function removePart(MiddlewareFilterBase &$fragment);
}
