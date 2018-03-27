<?php

namespace com\mainone\middleware;

class EncoderDecoder{
    public static function escape($value){
        return str_replace(['\\(', '\\)', "\\'",'\\{', '\\}'], ['_y0028_','_y0029_', '_y0027_', '_y007B_','_y007D_'], $value);
    }

    public static function unescape($value, $niddle = [], $replacement = [], $enforce = FALSE){
        if($enforce){
            return str_replace($niddle, $replacement, $value);
        } else {
            return str_replace(array_merge(['_y0028_','_y0029_', '_y0027_', '_y007B_','_y007D_'], $niddle), array_merge(['(', ')', "\'", '{', '}'], $replacement), $value);
        }
    }

    public static function escapeinner($value, $niddle = [], $replacement = [], $enforce = FALSE){
        if($enforce){
            return str_replace($niddle, $replacement, $value);
        } else {
            return str_replace(array_merge(['(', ')', "'", '{', '}'], $niddle), array_merge(['_y0028_','_y0029_', '_y0027_', '_y007B_','_y007D_'], $replacement), $value);
        }
    }

    public static function encode($value){
        return str_replace(['(', ')', "'", '{', '}'], ['_x0028_','_x0029_', '_x0027_', '_y007B_','_y007D_'], $value);
    }

    public static function decode($value){
        return str_replace(['_x0028_','_x0029_', '_x0027_', '_y007B_','_y007D_'], ['(', ')', "'", '{', '}'], $value);
    }

    public static function unescapeall($value){
        $ret = self::unescape($value);
        $ret = self::decode($ret);
        return $ret;
    }

}