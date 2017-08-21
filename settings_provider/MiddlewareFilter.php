<?php

namespace com\mainone\middleware;

include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/MiddlewareFilterBase.php');
include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/EntityDefinitionBrowser.php');

use com\mainone\middleware\MiddlewareFilterBase;
use com\mainone\middleware\EntityDefinitionBrowser;

class MiddlewareFilter extends MiddlewareFilterBase {

    protected $parent = NULL;
    public $field;
    public $value;
    public $operator;
    private $quote = '';
    private $fieldInfo = NULL;
    private $ors = [];
    private $ands = [];
    private $processor = NULL;

    const EQUAL_TO = 'eq';
    const LESS_THAN = 'lt';
    const LESS_THAN_EQUAL_TO = 'le';
    const GREATER_THAN = 'gt';
    const GREATER_THAN_EQUAL_TO = 'ge';
    const NOT_EQUAL_TO = 'ne';
    const STARTS_WITH = 'startswith';
    const ENDS_WITH = 'endswith';
    const SUBSTRING_OF = 'substringof';
    const IN = 'in';

    public function __construct(EntityDefinitionBrowser $entityDefinition = NULL, $field, $value, $operator = EQUAL_TO, $quote = '', $formater = '', $context = NULL, $behaviour = self::DEFAULT_STRINGER, callable $stringer = NULL) {
        parent::__construct($behaviour, $stringer);
        // var_dump($field, $value, $operator, $quote);

        if ($operator == self::IN) {
            if (!is_array($value)) {
                throw new \Exception("IN filter expects an array value, {gettype($value)} given.");
            }
        }

        // Get the internal name of the field
        $fieldInfo = !is_null($entityDefinition) ? $entityDefinition->getFieldByDisplayName($field) : NULL;
        $this->fieldInfo = $fieldInfo;
        $this->field = !is_null($fieldInfo) ? $fieldInfo->getQueryName() : $field;
        $this->quote = $quote;

        if ($formater == 'datetime') {
            $this->value = $this->getDateTime($value);
        } else if (is_string($value) && strtolower($value) == '$now$') {
            $this->value = new \DateTime();
            $this->quote = '\'';
        } else if (is_string($value) && strtolower($value) == '$today$') {
            $this->value = new \DateTime();
            $this->value->setTime(0, 0);
            $this->quote = '\'';
        } else if (is_string($value) && strtolower($value) == '$null$') {
            $this->value = NULL;
        } else if (is_string($value) && strtolower($value) == '$blank$') {
            $this->value = '';
            $this->quote = '\'';
        } else {
            $this->value = $value;
        }
        $this->operator = strtolower($operator);

        if (is_null($fieldInfo)) {
            // var_dump($fieldInfo);
            $this->quote = (strlen($quote) > 0) ? '\'' : '';
        } else {
            if (!is_null($this->value)) {
                if ($fieldInfo->getDataType() == 'int' && strlen($this->quote) > 0) {
                    throw new \Exception("Field {$fieldInfo->getDisplayName()} is an integer field. Quotes are not allowed for integer fields.");
                } else if ($fieldInfo->getDataType() != 'int' && strlen($this->quoteValue()) < 1) {
                    throw new \Exception("Field {$fieldInfo->getDisplayName()} requires that it's values be quoted. {$value}");
                } else if (($fieldInfo->getDataType() != 'int' && strlen($this->quote) > 1) || ($fieldInfo->getDataType() != 'int' && ($this->quote != '"' && $this->quote != '\''))) {
                    
                    throw new \Exception("Field {$fieldInfo->getDisplayName()} only supports qoutes of type ''' or '\"'.");
                } else {
                    $this->quote = (strlen($this->quote) > 0) ? '\'' : '';
                }
            }
        }
    }

    private function quoteValue() {
        // Implement checking if field is meant to be a string or otherwise
        $backslash = '\\';
        if (is_array($this->value)) {
            // $im = implode("{$this->quote},{$this->quote}", $this->value);
            $im = implode("_x0027_,_x0027_", $this->value);
            $im = str_replace("{$this->quote}", "{$backslash}{$this->quote}", $im);
            $im = str_replace("_x0027_", "{$this->quote}", $im);

            return "{$this->quote}{$im}{$this->quote}";
        } else if ($this->value instanceof \DateTime) {
            return $this->value->format('Y-m-d\\TH:i:s');
        } else {
            $return = "_x0027_{$this->value}_x0027_";
            $return = str_replace("{$this->quote}", "{$backslash}{$this->quote}", $return);
            $return = str_replace("_x0027_", "{$this->quote}", $return);
            // $return = "{$this->quote}{$this->value}{$this->quote}";

            return $return;
        }
    }

    private function quoteValueIn(){
        // Implement checking if field is meant to be a string or otherwise
        if (is_array($this->value)) {
            $im = implode("{$this->quote},{$this->quote}", $this->value);
            $im = str_replace('\'\',', '', $im);
            $im = str_replace('\'\'', '', $im);
            return $im != '\'\''? "{$this->quote}{$im}{$this->quote}":'';
        } else if ($this->value instanceof \DateTime) {
            return $this->value->format('Y-m-d\\TH:i:s');
        } else {
            return "{$this->quote}{$this->value}{$this->quote}";
        }
    }

    private function getDateTime($value) {
        $type_1 = '/^(([\d]{4})\-([\d]{2})\-([\d]{2})(T([\d]{2})\:([\d]{2})(\:([\d]{2}))?)?)$/';
        $type_2 = '/^(([\d]{4})\-([\d]{2})\-([\d]{2})(T([\d]{2})\:([\d]{2})))$/';
        $type_3 = '/^([\d]{4})\\-([\d]{2})\-([\d]{2})$/';

        if (preg_match($type_3, $value) == 1) {
            return \DateTime::createFromFormat('!Y-m-d', $value);
        } else if (preg_match($type_2, $value) == 1) {
            return \DateTime::createFromFormat('!Y-m-d\\TH:i', $value);
        } else if (preg_match($type_1, $value) == 1) {
            return \DateTime::createFromFormat('!Y-m-d\\TH:i:s', $value);
        }

        throw new \Exception("The time format is not known. Class MiddlewareFilter {$value}");
    }

    // $processor(MiddlewareFilter $e);
    public function setStringifier(callable $processor = NULL) {
        $this->stringifier = $processor;
    }

    protected function LDAPStringer(MiddlewareFilterBase &$context) {
        $ret = '';

        $value = $this->value;
        if ($value instanceof \DateTime) {
            // $value = $value->format('Y-m-d\\TH:i:s');
            $epoch = new \DateTime('1601-01-01');
            $interval = $epoch->diff($value);
            $value = ($interval->days * 24 * 60 * 60);
        } else {
            if (is_null($value)) {
                $value = '\\00';
            }
        }

        switch ($context->operator) {
            case self::STARTS_WITH: {
                    $ret = "{$context->field}={$value}*";
                    break;
                }
            case self::ENDS_WITH: {
                    $ret = "{$context->field}=*{$value}";
                    break;
                }
            case self::SUBSTRING_OF: {
                    $ret = "{$context->field}=*{$value}*";
                    break;
                }
            case self::EQUAL_TO: {
                    if (is_null($value)) {
                        $ret = "!{$context->field}=*";
                    } else {
                        $ret = "{$context->field}={$value}";
                    }
                    break;
                }
            case self::NOT_EQUAL_TO: {
                    if (is_null($value)) {
                        $ret = "{$context->field}=*";
                    } else {
                        $ret = "!{$context->field}={$value}";
                    }
                    break;
                }
            case self::GREATER_THAN: {
                    $ret = "{$context->field}>{$value}";
                    break;
                }
            case self::GREATER_THAN_EQUAL_TO: {
                    $ret = "{$context->field}>={$value}";
                    break;
                }
            case self::LESS_THAN: {
                    $ret = "{$context->field}<{$value}";
                    break;
                }
            case self::LESS_THAN_EQUAL_TO: {
                    $ret = "{$context->field}>={$value}";
                    break;
                }
            case self::IN: {
                    $im = implode(")({$this->field}=", $value);
                    if (count($value) > 1) {
                        $ret = "(|({$this->field}={$im}))";
                    } else {
                        $ret = "({$this->field}={$im})";
                    }
                    break;
                }
            default: {
                    $ret = "{$context->field} {$context->operator} {$value}";
                }
        }

        return $ret;
    }

    protected function DEFAULTStringer(MiddlewareFilterBase &$context) {
        $ret = '';

        $value = $context->value;
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d\\TH:i:s');
        }

        if (is_null($value)) {
            $value = '0';
        }

        switch ($this->operator) {
            case self::STARTS_WITH:
            case self::ENDS_WITH:
            case self::SUBSTRING_OF: {
                    $ret = "{$this->operator}({$this->field},{$value})";
                    break;
                }
            case self::IN: {
                    $ret = "{$this->field}{$this->operator}({$this->quoteValue()})";
                    break;
                }
            default: {
                    $ret = "{$this->field} {$this->operator} {$value}";
                }
        }

        return $ret;
    }

    protected function SOQLStringer(MiddlewareFilterBase &$scope) {
        $ret = '';

        $value = $this->value;
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d\\TH:i:s\\Z');
        } else if (is_null($value)) {
            $value = 'NULL';
        }

        switch ($this->operator) {
            case self::STARTS_WITH: {
                    $ret = "{$this->field} LIKE '{$value}%'";
                    break;
                }
            case self::ENDS_WITH: {
                    $ret = "{$this->field} LIKE '%{$value}'";
                    break;
                }
            case self::SUBSTRING_OF: {
                    $ret = "{$this->field} LIKE '%{$value}%'";
                    break;
                }
            case self::NOT_EQUAL_TO: {
                    $ret = "{$this->field} != {$this->quoteValue()}";
                    break;
                }
            case self::EQUAL_TO: {
                    $ret = "{$this->field} = {$this->quoteValue()}";
                    break;
                }
            case self::GREATER_THAN: {
                    $ret = "{$this->field} > {$this->quoteValue()}";
                    break;
                }
            case self::GREATER_THAN_EQUAL_TO: {
                    $ret = "{$this->field} >= {$value}";
                    break;
                }
            case self::LESS_THAN: {
                    $ret = "{$this->field} < {$value}";
                    break;
                }
            case self::LESS_THAN_EQUAL_TO: {
                    $ret = "{$this->field} <= {$value}";
                    break;
                }
            case self::IN: {
                    $ret = "{$this->field} IN({$this->quoteValueIn()})";
                    break;
                }
            default: {
                    throw new \Exception('Unknown query operand encountered.');
                }
        }

        return $ret;
    }

    protected function BMCStringer(MiddlewareFilterBase &$scope) {
        $ret = '';

        $field = $this->field;
        $value = $this->value;
        if ($value instanceof \DateTime) {
            $value = $value->format('"m/d/Y H:i:s"');
        } else if (is_null($value)) {
            $value = 'NULL';
        } else if(is_string($value)){
            $value = "\"{$value}\"";
        }else{
            $value = $this->quoteValue();
        }

        switch ($this->operator) {
            case self::STARTS_WITH: {
                    $ret = "'{$field}' LIKE '{$value}%'";
                    break;
                }
            case self::ENDS_WITH: {
                    $ret = "'{$field}' LIKE '%{$value}'";
                    break;
                }
            case self::SUBSTRING_OF: {
                    $ret = "'{$field}' LIKE '%{$value}%'";
                    break;
                }
            case self::NOT_EQUAL_TO: {
                    $ret = "'{$field}' != {$value}";
                    break;
                }
            case self::EQUAL_TO: {
                    $ret = "'{$field}' = {$value}";
                    break;
                }
            case self::GREATER_THAN: {
                    $ret = "'{$field}' > {$value}";
                    break;
                }
            case self::GREATER_THAN_EQUAL_TO: {
                    $ret = "'{$field}' >= {$value}";
                    break;
                }
            case self::LESS_THAN: {
                    $ret = "'{$field}' < {$value}";
                    break;
                }
            case self::LESS_THAN_EQUAL_TO: {
                    $ret = "'{$field}' <= {$value}";
                    break;
                }
            case self::IN: {
                    $ret = "";
                    foreach($this->value as $v){
                        $ret = "{$ret}'{$field}' = \"{$v}\" || ";
                    }
                    $ret = strlen($ret)>0?substr($ret, 0, strlen($ret) - 3):"";
                    break;
                }
            default: {
                    throw new \Exception('Unknown query operand encountered.');
                }
        }

        return $ret;
    }

    protected function SQLStringer(MiddlewareFilterBase &$scope) {
        $ret = '';

        $value = $this->value;
        if ($value instanceof \DateTime) {
            $value = $value->format('\'Y-m-d H:i:s\'');
        } else if (is_null($value)) {
            $value = 'NULL';
        } else {
            $value = $this->quoteValue();
        }
        switch ($this->operator) {
            case self::STARTS_WITH: {
                    $ret = "{$this->field} LIKE '{$this->value}%'";
                    break;
                }
            case self::ENDS_WITH: {
                    $ret = "{$this->field} LIKE '%{$this->value}'";
                    break;
                }
            case self::SUBSTRING_OF: {

                    $ret = "{$this->field} LIKE '%{$this->value}%'";
                    break;
                }
            case self::NOT_EQUAL_TO: {
                    $ret = "{$this->field} != {$value}";
                    break;
                }
            case self::EQUAL_TO: {
                    $ret = "{$this->field} = {$value}";
                    break;
                }
            case self::GREATER_THAN: {
                    $ret = "{$this->field} > {$value}";
                    break;
                }
            case self::GREATER_THAN_EQUAL_TO: {
                    $ret = "{$this->field} >= {$value}";
                    break;
                }
            case self::LESS_THAN: {
                    $ret = "{$this->field} < {$value}";
                    break;
                }
            case self::LESS_THAN_EQUAL_TO: {
                    $ret = "{$this->field} <= {$value}";
                    break;
                }
            case self::IN: {
                    $ret = "{$this->field} {$this->operator}({$this->quoteValue()})";
                    break;
                }
            default: {
                    throw new \Exception('Unknown query operand encountered.');
                }
        }

        return $ret;
    }

    protected function XPPStringer(MiddlewareFilterBase &$context) {
        $ret = '';

        $value = $this->value;
        if ($value instanceof \DateTime) {
            $value = "datetime'{$value->format('Y-m-d\\TH:i:s')}'";
        } else if (is_null($value)) {
            $value = '\'\'';
        } else {
            $value = $this->quoteValue();
        }

        switch ($this->operator) {
            case self::STARTS_WITH:
            case self::ENDS_WITH:
            case self::SUBSTRING_OF: {
                    $ret = "{$this->operator}({$this->field},{$value})";
                    break;
                }
            // case self::STARTS_WITH: {
            //         $ret = "{$this->field} LIKE '{$this->value}*'";
            //         break;
            //     }
            // case self::ENDS_WITH: {
            //         $ret = "{$this->field} LIKE '*{$this->value}'";
            //         break;
            //     }
            // case self::SUBSTRING_OF: {
            //         $ret = "{$this->field} LIKE '*{$this->value}*'";
            //         break;
            //     }
            case self::NOT_EQUAL_TO:
            case self::EQUAL_TO:
            case self::GREATER_THAN:
            case self::GREATER_THAN_EQUAL_TO:
            case self::LESS_THAN:
            case self::LESS_THAN_EQUAL_TO: {
                    $ret = "{$this->field} {$this->operator} {$value}";
                    break;
                }
            case self::IN: {
                    $ret = "{$this->field} {$this->operator}({$this->quoteValue()})";
                    break;
                }
            default: {
                    throw new \Exception('Unknown query operand encountered.');
                }
        }

        return $ret;
    }

}
