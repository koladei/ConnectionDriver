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

    public function __construct(EntityDefinitionBrowser $entityDefinition = NULL, $field, $value, $operator = EQUAL_TO, $quote = '', $formater = '', $behaviour = self::DEFAULT_STRINGER, callable $stringer = NULL) {
        parent::__construct($behaviour, $stringer);

        if($operator == self::IN){
            if(!is_array($value)){
                throw new \Exception("IN filter expects an array value, {gettype($value)} given.");
            }
        }

        // Get the internal name of the field
        $fieldInfo = !is_null($entityDefinition) ? $entityDefinition->getFieldByDisplayName($field) : NULL;
        $this->fieldInfo = $fieldInfo;
        $this->field = !is_null($fieldInfo) ? $fieldInfo->getInternalName() : $field;
        
        if($formater == 'datetime'){
            $this->value = $this->getDateTime($value);
        } else if(strtolower($value) == '$now$'){
            $this->value = new \DateTime();
            $this->quote = '\'';
        } else if(strtolower($value) == '$today$'){
            $this->value = new \DateTime();
            $this->value->setTime(0, 0);
            $this->quote = '\'';
            var_dump($this->value);
        } else if(strtolower($value) == '$null$'){
            $this->value = NULL;
        } else if(strtolower($value) == '$blank$'){
            $this->value = '';
            $this->quote = '\'';
        } 
        $this->operator = strtolower($operator);
        
        if(is_null($fieldInfo)){
            $this->quote = (strlen($quote) > 0) ?'\'':'';
        }else{
            if(!is_null($this->value)){
                if($fieldInfo->getDataType() == 'int' && strlen($quote) > 0){
                    throw new \Exception("Field {$fieldInfo->getDisplayName()} is an integer field. Quotes are not allowed for integer fields.");
                } else if($fieldInfo->getDataType() != 'int' && strlen($quote) < 1) {
                    $args = print_r(func_get_arg(4), true);
                    throw new \Exception("Field {$fieldInfo->getDisplayName()} requires that it's values be quoted. {$value}");
                } else if($fieldInfo->getDataType() != 'int' && (strlen($quote) > 1 || ($quote != '"' && $quote != '\''))) {
                    $args = print_r(func_get_arg(4), true);
                    throw new \Exception("Field {$fieldInfo->getDisplayName()} only supports qoutes of type ''' or '\"'. #{$args}#");
                } else {
                    $this->quote = (strlen($quote) > 0) ?'\'':'';
                }
            }
        }
    }

    private function quoteValue(){
        // Implement checking if field is meant to be a string or otherwise
        if(is_array($this->value)){ 
            $im = implode("{$this->quote},{$this->quote}", $this->value);
            return "{$this->quote}{$im}{$this->quote}";
        } else{ 
            return "{$this->quote}{$this->value}{$this->quote}";
        }
    }

    private function getDateTime($value){        
        $type_1 = '/^(([\d]{4})\-([\d]{2})\-([\d]{2})(T([\d]{2})\:([\d]{2})(\:([\d]{2}))?)?)$/';
        $type_2  = '/^(([\d]{4})\-([\d]{2})\-([\d]{2})(T([\d]{2})\:([\d]{2})))$/';
        $type_3 = '/^([\d]{4})\\-([\d]{2})\-([\d]{2})$/';

        if(preg_match($type_3, $value) == 1){
            return \DateTime::createFromFormat('!Y-m-d', $value);
        } else if(preg_match($type_2, $value) == 1){
            return \DateTime::createFromFormat('!Y-m-d\\TH:i', $value);
        } else if(preg_match($type_1, $value) == 1){
            return \DateTime::createFromFormat('!Y-m-d\\TH:i:s', $value);
        }

        throw new \Exception("The time format is not known. {$value}");
    }

    // $processor(MiddlewareFilter $e);
    public function setStringifier(callable $processor = NULL){
        $this->stringifier = $processor;
    }

    protected function LDAPStringer(MiddlewareFilterBase &$context){
            $ret = '';

            $value = $context->value;
            if($value instanceof \DateTime){
                // $value = $value->format('Y-m-d\\TH:i:s');
                $epoch = new \DateTime('1601-01-01');
                $interval = $epoch->diff($value);
                $value = ($interval->days * 24 * 60 * 60);
            }

            if(is_null($value)){
                $value = '0';
            }

            switch($context->operator){
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
                    $ret = "{$context->field}={$value}";
                    break;
                }
                case self::NOT_EQUAL_TO: {
                    $ret = "!{$context->field}={$value}";
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
                case self::IN:{
                    $im = implode(")({$this->field}=", $value);
                    if(count($value)>1){
                        $ret = "(|({$this->field}={$im}))";
                    } else {
                        $ret = "({$this->field}={$im})";
                    }
                    break;
                }
                default:{
                    $ret = "{$context->field} {$context->operator} {$value}";
                }
            }

            return $ret;
    }

    protected function DEFAULTStringer(MiddlewareFilterBase &$context){
        $ret = '';

        $value = $context->value;
        if($value instanceof \DateTime){
            $value = $value->format('Y-m-d\\TH:i:s');
        }

        if(is_null($value)){
            $value = '0';
        }
        
        switch($this->operator){
            case self::STARTS_WITH:
            case self::ENDS_WITH:
            case self::SUBSTRING_OF:{
                $ret = "{$this->operator}({$this->field},{$value})";
                break;
            }
            case self::IN:{
                $ret = "{$this->field}{$this->operator}({$this->quoteValue()})";
                break;
            }
            default:{
                $ret = "{$this->field} {$this->operator} {$value}";
            }
        }

        return $ret;
    }

    protected function SOQLStringer(MiddlewareFilterBase &$scope){
        $ret = '';

        $value = $this->value;
        if($value instanceof \DateTime){
            $value = $value->format('\'Y-m-d\\TH:i:s\\Z\'');
        }
        else if(is_null($value)){
            $value = 'NULL';
        } else {
            $value = $this->quoteValue();
        }
        
        switch($this->operator){
            case self::STARTS_WITH:{
                $ret = "{$this->field} LIKE '{$value}%'";
                break;
            }
            case self::ENDS_WITH:{
                $ret = "{$this->field} LIKE '%{$value}'";
                break;
            }
            case self::SUBSTRING_OF:{
                $ret = "{$this->field} LIKE '%{$value}%'";
                break;
            }
            case self::NOT_EQUAL_TO:{
                $ret = "{$this->field} != {$value}";
                break;
            }
            case self::EQUAL_TO:{
                $ret = "{$this->field} = {$value}";
                break;
            }
            case self::GREATER_THAN:{
                $ret = "{$this->field} > {$value}";
                break;
            }
            case self::GREATER_THAN_EQUAL_TO:{
                $ret = "{$this->field} >= {$value}";
                break;
            }
            case self::LESS_THAN:{
                $ret = "{$this->field} < {$value}";
                break;
            }
            case self::LESS_THAN_EQUAL_TO:{
                $ret = "{$this->field} <= {$value}";
                break;
            }
            case self::IN:{
                $ret = "{$this->field} IN({$this->quoteValue()})";
                break;
            }
            default:{
                throw new \Exception('Unknown query operand encountered.');
            }
        }

        return $ret;
    }

    protected function SQLStringer(MiddlewareFilterBase &$scope){
        // TODO: Implement 
        return $this->XPPStringer($scope);
    }

    protected function XPPStringer(MiddlewareFilterBase &$context){
         $ret = '';

        $value = $this->value;
        if($value instanceof \DateTime){
            $value = "datetime'{$value->format('Y-m-d\\TH:i:s')}'";
        } else if(is_null($value)){
            $value = '\'\'';
        } else {
            $value = $this->quoteValue();
        }
        
        switch($this->operator){
            case self::STARTS_WITH:
            case self::ENDS_WITH:
            case self::SUBSTRING_OF:{
                 $ret = "{$this->operator}({$this->field},'{$value}')";
                break;
            }
            case self::NOT_EQUAL_TO:
            case self::EQUAL_TO:
            case self::GREATER_THAN:
            case self::GREATER_THAN_EQUAL_TO:
            case self::LESS_THAN:
            case self::LESS_THAN_EQUAL_TO:{
                $ret = "{$this->field} {$this->operator} {$value}";
                break;
            }
            case self::IN:{
                
                 $ret = "{$this->field} {$this->operator}({$this->quoteValue()})";
                // $im = implode("{$this->quote} || {$this->field}={$this->quote}", $value);
                // $ret = "{$this->field}={$this->quote}{$im}{$this->quote}";
                break;
            }
            default:{
                throw new \Exception('Unknown query operand encountered.');
            }
        }

        return $ret;
    }
}
