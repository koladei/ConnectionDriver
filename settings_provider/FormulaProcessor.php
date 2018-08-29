<?php

namespace com\mainone\middleware;

class FormulaProcessor {
    private $field;
    private $processedExpression;
    private $fields = [];
    private $formulaFields = [];

    public function __construct(EntityFieldDefinition &$field){
        $this->field = $field;

        $matchs = [];
        preg_match_all('/(\{)([\w][\w\d]*)(\})/', $field->getFormula(), $matchs, PREG_SET_ORDER);

        foreach ($matchs as $mat) {
            $name = $mat[2];      

            $this->fields[$name] = $field->getParent()->getFieldByDisplayName($name)->getInternalName();
        }

        $field->setFormulaProcessor($this);
    }

    public function getFields(array $fields = [], $displayNames = false){
        foreach($this->fields as $display => $internal) {
            if($displayNames){
                if(!in_array($display, $fields)){
                    $fields[] = $display;
                }
            } else {
                if(!in_array($internal, $fields)){
                    $fields[] = $internal;
                }
            }
        }

        return $fields;
    }

    public function evaluate(\stdClass $record, $displayName = true){
        $formulaFieldName = $this->field->getDisplayName();
        $formula = "return ({$this->field->getFormula()});";
        foreach($this->fields as $display => $internal){
            $newValue = property_exists($record, $display)? $record->{$display} : '';
            $formula = str_replace("{{$display}}", $newValue, $formula);
        }

        $record->{"{$this->field->getDisplayName()}"} = eval($formula);
        return $record;
    }

    public static function initialize(EntityFieldDefinition $field){        
        return new FormulaProcessor($field);
    }
}