<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace com\mainone\middleware;

include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/MiddlewareFilter.php');
include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/MiddlewareFilterGroup.php');
include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/EncoderDecoder.php');

use com\mainone\middleware\MiddlewareFilter;
use com\mainone\middleware\MiddlewareFilterGroup;
use com\mainone\middleware\EncoderDecoder;

/**
 * Description of MiddlewareODataFilterProcessor
 *
 * @author Kolade.Ige
 */
class MiddlewareODataFilterProcessor {

    private $fragments = [];
    private $groups = [];
    private $stringerType = NULL;
    private $expressionStringer = NULL;
    private $expressionGroupStringer = NULL;
    private $lastGroupKey = NULL;
    private $valueContext = NULL;

    public static function convert(EntityDefinitionBrowser $entityDefinition = NULL, $expression, $context = NULL, $stringerType = MiddlewareFilterBase::DEFAULT_STRINGER, callable $expressionStringer = NULL, callable $expressionGroupStringer = NULL) {
        $filterExpression = new MiddlewareODataFilterProcessor($entityDefinition, $expression, $context, $stringerType);
        return $filterExpression;
    }

    private function __construct(EntityDefinitionBrowser $entityDefinition = NULL, $expression, $context = NULL, $stringerType = MiddlewareFilterBase::DEFAULT_STRINGER, callable $expressionStringer = NULL, callable $expressionGroupStringer = NULL) {

        $this->stringerType = $stringerType;
        $this->expressionStringer = $expressionStringer;
        $this->expressionGroupStringer = $expressionGroupStringer;
        $this->valueContext = $context;

        // Excape special characters
        $expression = EncoderDecoder::escape($expression);

        // In operator
        $matchs = [];
        
        preg_match_all('/([\w][\w\d\/]*[^\/\s])\s+(in)\s*(\()\s*(([\'"]?)([^\n\r\)]*))(\5)(\s*\))/i', $expression, $matchs, PREG_SET_ORDER);
        foreach ($matchs as $mat) {
            $place = count($this->fragments);
            $key = "#{$place}#";
            $v = preg_split("/({$mat[5]})\s*\,\s*(\\1)/", $mat[6]);
            $this->fragments[$key] = new MiddlewareFilter($entityDefinition, $mat[1], $v, $mat[2], $mat[5], '', $this->valueContext, $this->stringerType, $this->expressionStringer);
            $expression = self::str_replace_first($mat[0], $key, $expression);
        }

        // String match operations
        $matchs = [];
        preg_match_all('/((substringof|startswith|endswith)\s*\()\s*([\w][\w\d\/]*[^\/\s])\s*\,\s*(([\'"])(\\5)\s*(\)))/', $expression, $matchs, PREG_SET_ORDER);
        foreach ($matchs as $mat) {
            $place = count($this->fragments);
            $key = "#{$place}#";
            $this->fragments[$key] = new MiddlewareFilter($entityDefinition, $mat[3], '', $mat[2], $mat[5], '', $this->valueContext, $this->stringerType, $this->expressionStringer);
            $expression = self::str_replace_first($mat[0], $key, $expression);
        }

        // String match operations
        $matchs = [];
        preg_match_all('/((substringof|startswith|endswith)\s*\()\s*([\w][\w\d\/]*[^\/\s])\s*\,\s*(([\'"])([^\'"\)]{0,})(\5))\s*(\))/i', $expression, $matchs, PREG_SET_ORDER);

        foreach ($matchs as $mat) {
            $place = count($this->fragments);
            $key = "#{$place}#";
            $this->fragments[$key] = new MiddlewareFilter($entityDefinition, $mat[3], $mat[6], $mat[2], $mat[5], '', $this->valueContext, $this->stringerType, $this->expressionStringer);
            $expression = self::str_replace_first($mat[0], $key, $expression);
        }

        // Date and String comparisons
        $matchs = [];
        preg_match_all('/([\w][\w\d\/]*[^\/\s])\s+([\w]{2})\s+(((datetime)([\'"]))|([\'"]))((4)?([\d\-:]+|[^\'"]{0,}))((\6)?(\6)|(\7))/', $expression, $matchs, PREG_SET_ORDER);

        foreach ($matchs as $mat) {
            $place = count($this->fragments);
            $key = "#{$place}#";
            $this->fragments[$key] = new MiddlewareFilter($entityDefinition, $mat[1], $mat[8], $mat[2], $mat[11], $mat[5], $this->valueContext, $this->stringerType, $this->expressionStringer);
            $expression = self::str_replace_first($mat[0], $key, $expression);
        }
        
        // Integer and Constants comparisons
        $matchs = [];
        preg_match_all('/([\w][\w\d\/]*[^\/\s])\s+([\w]{2})\s+((([\-])?[\d]+(\.[\d]+)?)|(\$[\w]+\$))/', $expression, $matchs, PREG_SET_ORDER);

        foreach ($matchs as $mat) {
            $place = count($this->fragments);
            $key = "#{$place}#";
            $this->fragments[$key] = new MiddlewareFilter($entityDefinition, $mat[1], $mat[3], $mat[2], '', '', $this->valueContext, $this->stringerType, $this->expressionStringer);
            $expression = self::str_replace_first($mat[0], $key, $expression);
        }               

        // Field to field comparisons
        $matchs = [];
        preg_match_all('/([\w][\w\d\/]*[^\/\s])\s+([\w]{2})\s+(([\-])?[\w\d\_]+)/', $expression, $matchs, PREG_SET_ORDER);

        foreach ($matchs as $mat) {
            $place = count($this->fragments);
            $key = "#{$place}#";
            $this->fragments[$key] = new MiddlewareFilter($entityDefinition, $mat[1], $mat[3], $mat[2], '', 'field', $this->valueContext, $this->stringerType, $this->expressionStringer);
            $expression = self::str_replace_first($mat[0], $key, $expression);
        }

        $expression = trim($expression);
        if (strlen($expression) > 0) {
            if (substr($expression, 0) != '(') {
                $expression = "({$expression})";
            }
        }

        $parenPos = strpos($expression, '(');
        while ($parenPos > -1) {
            $matchs = [];
            
            preg_match_all('/([\(])([^\)\(]{0,})([\)])/', $expression, $matchs, PREG_SET_ORDER);
            
            foreach ($matchs as $mat) {
                $place = count($this->groups);
                $this->lastGroupKey = "@{$place}@";
                $group = new MiddlewareFilterGroup($this->stringerType, $this->expressionGroupStringer);
                $this->buildFragmentGroup($group, $mat[2]);

                $this->groups[$this->lastGroupKey] = $group;
                $expression = self::str_replace_first($mat[0], $this->lastGroupKey, $expression);
                $parenPos = strpos($expression, '(');
            }
        }
    }

    public function __toString() {
        if (!is_null($this->lastGroupKey)) {
            $rootGroup = $this->groups[$this->lastGroupKey];
            return "{$rootGroup}";
        }
        return '';
    }

    public function getRootGroup() {
        if (!is_null($this->lastGroupKey)) {
            $rootGroup = $this->groups[$this->lastGroupKey];
            return $rootGroup;
        }
        return NULL;
    }

    private function buildFragmentGroup(MiddlewareFilterGroup &$group, $expression) {
        $matchs = [];
        preg_match_all('/(and|or)?\s*((#|@)([\d]+)(\3))/', $expression, $matchs, PREG_SET_ORDER);

        foreach ($matchs as $mat) {
            $switch = $mat[1] . $mat[3];

            switch ($switch) {
                case '@': {
                        $group->addPart($this->groups[$mat[2]], MiddlewareFilterGroup::FRAGMENT_AND);
                        break;
                    }
                case 'or@': {
                        $group->addPart($this->groups[$mat[2]], MiddlewareFilterGroup::FRAGMENT_OR);
                        break;
                    }
                case 'and@': {
                        $group->addPart($this->groups[$mat[2]], MiddlewareFilterGroup::FRAGMENT_AND);
                        break;
                    }
                case '#': {
                        $group->addPart($this->fragments[$mat[2]], MiddlewareFilterGroup::FRAGMENT_AND);
                        break;
                    }
                case 'or#': {
                        $group->addPart($this->fragments[$mat[2]], MiddlewareFilterGroup::FRAGMENT_OR);
                        break;
                    }
                case 'and#': {
                        $group->addPart($this->fragments[$mat[2]], MiddlewareFilterGroup::FRAGMENT_AND);
                        break;
                    }
            }
        }
    }

    protected static function str_replace_first($from, $to, $subject) {
        $from = '/' . preg_quote($from, '/') . '/';

        return preg_replace($from, $to, $subject, 1);
    }

    public function getFragments() {
        return $this->fragments;
    }

}
