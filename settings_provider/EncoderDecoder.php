<?php

namespace com\mainone\middleware;

class EncoderDecoder{
    public static function escape($value){
        return str_replace(['\\(', '\\)', '\\\''], ['_y0028_','_y0029_', '_y0027_'], $value);
    }

    public static function unescape($value){
        return str_replace(['_y0028_','_y0029_', '_y0027_'], ['(', ')', '\\\''], $value);
    }

    public static function escapeinner($value){
        $return = str_replace(['(', ')', "'"], ['_y0028_','_y0029_', '_y0027_'], $value);
        return $return;
    }

    public static function encode($value){
        return str_replace(['(', ')', "'"], ['_x0028_','_x0029_', '_x0027_'], $value);
    }

    public static function decode($value){
        return str_replace(['_x0028_','_x0029_', '_x0027_'], ['(', ')', "'"], $value);
    }

    public static function unescapeall($value){
        $ret = self::unescape($value);
        $ret = self::decode($ret);
        return $ret;
    }

}