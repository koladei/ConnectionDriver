<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace com\mainone\middleware;

use com\mainone\middleware\MiddlewareQueryFragment;

/**
 * Description of MiddlewareOdataFilterParser
 *
 * @author Kolade.Ige
 */
class MiddlewareOdataFilterParser {

    public static function reduce($filter) {
        $r1 = [];
        $r2 = [];

        $count1 = 0;
        $count2 = 0;

        // Break down the expression to the list parenthesis.        
        do {
            $filter = preg_replace_callback('/(\\()([^\\(\\)]+)(\\))/', function($groups) use(&$r2) {
                $p = count($r2);
                $r2[] = $groups[2];
                return $p;
            }, $filter, -1, $count1);
        } while ($count1 > 0);

        // Break down the expression to the list parenthesis.
        do {
            $filter = preg_replace_callback('/(\\()([^\\(\\)]+)(\\))/', function($groups) use(&$r1) {
                $p = count($r1);
                $r1[] = $groups[2];
                return $p;
            }, $filter, -1, $count2);
        } while ($count2 > 0);

        // Iterate over the segments and generate a query fragment from them.
        $x = MiddlewareQueryFragment::getEmptyFragment();


        return print_r($x->toString(), true);
    }

    public static function parse($filter, $fields) {
        

        $return = $filter;
        $pr = MiddlewareOdataFilterParser::getParseArray();

        foreach ($pr as $p) {

            //get the field name in the expression.
            $matss = [];
            preg_match_all($p['pattern'], $return, $matss, PREG_SET_ORDER);

            foreach ($matss as $mats) {
                $f = isset($mats[$p['field_name']]) ? $mats[$p['field_name']] : '';

                if (strlen($f) > 0) {
                    //get the mapped name of the field
                    $w = [$mats[$p['field_name']]];

                    ldap_connection_driver__rename_select_fields($w, $fields);
                    $r = isset($w[0]) ? $w[0] : '';

                    if (strlen($r) > 0) {
                        $return = preg_replace($p['pattern'], $p['replacement'], str_replace($f, $r, $return));

                        //fix date fields.
                        if (isset($p['is_date'])) {
                            $return = $fix_date($return);
                        }
                    }
                }
            }
        }

        return $return;
    }

    public static function getParseArray() {
        $field_selector = '[\\w][\\w\\d\\_]+';
        $value_selector = '[\\w\\d\\.\\s\\-_\\$\\,\\.]{0,}';
        $in_value_selector = '[\\w\\d\\.\\s\\,\'\\-_\\$#@\\^\\&\\(\\)\\~`]+[^\\)]{0,}';
        $int_value_selector = '[\\d]+';
        $datetime_value_selector_0 = '(([\\d]{4})\\-([\\d]{2})\\-([\\d]{2})(\\T([\\d]{2})\\:([\\d]{2})(\\:([\\d]{2}))?)?)';
        $datetime_value_selector_1 = '(([\\d]{4})\\-([\\d]{2})\\-([\\d]{2})(\\T([\\d]{2})\\:([\\d]{2})))';
        $datetime_value_selector_2 = '([\\d]{4})\\-([\\d]{2})\\-([\\d]{2})';

        $fix_date = function($date) use($field_selector, $datetime_value_selector_0, $datetime_value_selector_1, $datetime_value_selector_2) {

            return preg_replace([
                '/(' . $field_selector . ')[\s]+([><=]+)[\s]+(' . $datetime_value_selector_1 . ')Z/',
                '/(' . $field_selector . ')[\s]+([><=]+)[\s]+(' . $datetime_value_selector_2 . ')Z/'
                    ], [
                '${1} ${2} ${3}:00',
                '${1} ${2} ${3}T00:00:00'
                    ], $date);
        };
        
        return [
            [
                'pattern' => '/(' . $field_selector . ')[\\s]+([\\w]+)[\\s]+(datetime([\'])' . $datetime_value_selector_0 . '(\\4))/',
                'replacement' => '${1} ${2} ${3}',
                'field' => 1,
                'operator' => 2,
                'value' => 3,
                'is_date' => TRUE
            ],            
            [
                'pattern' => '/(' . $field_selector . ')[\\s]+([\\w]+)[\\s]+((([\'"])(' . $value_selector . ')(\\5))|(' . $int_value_selector . '))/',
                'replacement' => '${1} ${2} ${3}',
                'field_name' => 1,
                'operator' => 2,
                'value' => 3,
                'type' => 'comparison',
                'is_date' => FALSE
            ],            
            [
                'pattern' => '/([\\w][\\w\\d]+)(\\()[\\s]*(' . $field_selector . ')[\\s]*\\,[\\s]*((\'|")(' . $value_selector . ')(\\5))[\s]*(\\))/',
                'replacement' => '',
                'field_name' => 2,
                'operator' => 1,
                'value' => 3,
                'is_date' => FALSE,
            ],
        ];
    }
}
