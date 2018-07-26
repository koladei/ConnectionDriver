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
            $formula = str_replace("{{$display}}", $record->{$display}, $formula);
        }

        $record->{"{$this->field->getDisplayName()}"} = eval($formula);


        // var_dump($formula);


        // $formulaContent =  explode('|', $this->field->getFormula());

        // foreach($formulaContent as $comVal){
        //     if(strpos($comVal, '{') == 0){
        //         $comVal = \substr($comVal, 1, (strlen($comVal) - 2));
        //         $comVal = $record->{$comVal};
        //     }

        //     $prev = is_null($record->{$formulaFieldName})?'':$record->{$formulaFieldName};
        //     $record->{$formulaFieldName} = "{$prev}{$comVal}";
        // }

        // $fields = get_object_vars($record);
        return $record;
    }
}