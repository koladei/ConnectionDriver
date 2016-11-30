<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace com\mainone\middleware;

class MiddlewareQueryFragment {

    private $parent = NULL;
    public $field;
    public $value;
    public $operator;
    private $ors = [];
    private $ands = [];
    private $processor = NULL;

    const EQUAL_TO = 'eq';
    const LESS_THAN = 'eq';
    const GREATER_THAN = 'eq';
    const NOT_EQUAL_TO = 'eq';

    public function __construct($field, $value, $operator = 'eq', callable $processor = NULL) {
        $this->field = $field;
        $this->value = $value;
        $this->operator = $operator;
        $this->processor = $processor;
    }

    public static function getEmptyFragment() {
        $x = new MiddlewareQueryFragment('', '', '');
        return $x;
    }

    public function setProcessor(callable $processor = NULL) {
        $this->processor = $processor;
        foreach ($this->ors as &$or) {
            $or->setProcessor($this->processor);
        }

        foreach ($this->ands as &$and) {
            $and->setProcessor($this->processor);
        }
    }

    public function addToParent($parent, $addType) {
        $this->parent = $parent;
        if (!is_null($this->parent)) {
            $this->parent->{'add' . $addType . 'Branch'}($this);
        }
        return $this;
    }

    public function addOrBranch(MiddlewareQueryFragment &$fragment) {
        $fragment->setProcessor($this->processor);
        $this->ors[] = $fragment;
        return $this;
    }

    public function addAndBranch(MiddlewareQueryFragment &$fragment) {
        $fragment->setProcessor($this->processor);
        $this->ands[] = $fragment;
        return $this;
    }

    public function getField() {
        return $this->field;
    }

    public function getValue() {
        return $this->value;
    }

    public function getOperator($map) {
        return isset($map[$this->operator]) ? $map[$this->operator] : $this->operator;
    }

    public function getAnds() {
        return $this->ands;
    }

    public function getOrs() {
        return $this->ors;
    }

    /**
     * Converts this query fragment into a string
     * @return type string
     */
    public function toString() {
        if (is_null($this->processor)) {
            $s = "{$this->field} {$this->operator} {$this->value} ";
            $ands = '';
            $ors = '';
            foreach ($this->ands as $ind => $and) {
                $ands .= ' and ' . $and->toString();
            }

            foreach ($this->ors as $ind => $or) {
                $ors .= ' or ' . $or->toString();
            }

            $addBraces = strlen($ors) > 0 || strlen($ands) > 0 ? true : false;

            if ($addBraces) {
                return "({$s}{$ands}{$ors})";
            } else {
                return $s;
            }
        } else {
            $stringifier = $this->processor;
            return $stringifier($this);
        }
    }

}
