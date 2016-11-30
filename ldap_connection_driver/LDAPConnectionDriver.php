<?php

namespace com\mainone\middleware;

use com\mainone\middleware\MiddlewareConnectionDriver;
use com\mainone\middleware\MiddlewareQueryFragment;

/**
 * Description of LDAPConnectionDriver
 *
 * @author Kolade.Ige
 */
class LDAPConnectionDriver extends MiddlewareConnectionDriver {

    private $query = NULL;

    private static function stringifier($current) {
        $operator_map = [
            'eq' => '=',
            'gt' => '>',
            'lt' => '<',
            'ge' => '>=',
            'le' => '<=',
        ];
        
        $s = "({$current->getField()}{$current->getOperator($operator_map)}{$current->getValue()})";
        $ands = '';
        $ors = '';
        foreach ($current->getAnds() as $ind => $and) {
            $ands .= $and->toString();
        }

        if (strlen($ands) > 0) {
            $ands = "&{$ands}{$s}";
        }

        foreach ($current->getOrs() as $ind => $or) {
            $ors .= $or->toString();
        }

        if (strlen($ors) > 0) {
            $ors = "&{$ors}{$s}";
        }

        $addBraces = strlen($ors) > 0 || strlen($ands) > 0 ? true : false;

        if ($addBraces) {
            return "({$ands}{$ors})";
        } else {
            return $s;
        }
    }

    public function __construct() {
        
    }

    public static function getLDAPQueryFragment($field, $value, $operator) {
        $fragment = new MiddlewareQueryFragment($field, $value, $operator);
        $fragment->setProcessor(function() {
            return LDAPConnectionDriver::stringifier(...func_get_args());
        });
        return $fragment;
    }

}
