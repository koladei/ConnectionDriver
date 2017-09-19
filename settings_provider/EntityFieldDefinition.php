<?php

namespace com\mainone\middleware;

/**
 * Description of EntityFieldDefinition
 *
 * @author Kolade.Ige
 */
class EntityFieldDefinition {

    private $internalName;
    private $preferredQueryName;
    private $actualInternalName;
    private $displayName;
    private $type;
    private $localField;
    private $remoteField;
    private $remoteEntityRelationship;
    private $remoteDriver;
    private $remoteDriverName;
    private $remoteEntityName;
    private $expandable = false;
    private $isAnArray = 0;

    public function __construct($name, array $fieldDefinition, EntityDefinitionBrowser &$parent) {
        $this->parent = $parent;
        $this->internalName = $name;
        $this->actualInternalName = $name;
        $this->preferredQueryName = isset($fieldDefinition['preferred_query_name']) ? $fieldDefinition['preferred_query_name'] : $name;
        $this->displayName = $fieldDefinition['preferred_name'];
        $this->type = $fieldDefinition['type'];
        $this->dataType = $fieldDefinition['type'];
        $this->isAnArray = isset($fieldDefinition['is_array']) ? $fieldDefinition['is_array'] : 0;
        if ($this->type != 'detail' && isset($fieldDefinition['relationship'])) {
            if ($this->localField = $fieldDefinition['relationship']['local_field'] == $fieldDefinition['preferred_name']) {
                $idName = isset($fieldDefinition['relationship']['preferred_local_key_name']) ? $fieldDefinition['relationship']['preferred_local_key_name'] : "{$fieldDefinition['relationship']['local_field']}Key";
                $fieldDefinition['relationship']['local_field'] = $idName; //"{$fieldDefinition['relationship']['local_field']}Id";
                $this->internalName = "{$name}_lookup";
                $this->actualInternalName = $name;
                $this->type = 'detail';
                $x = $fieldDefinition;
                $x['preferred_name'] = $fieldDefinition['relationship']['local_field'];
                $x['preferred_query_name'] = isset($fieldDefinition['preferred_query_name']) ? $fieldDefinition['preferred_query_name'] : $name;
                unset($x['relationship']);

                $fieldDef = new EntityFieldDefinition($name, $x, $parent);
                $parent->setField($fieldDef);
            }

            $this->localField = $fieldDefinition['relationship']['local_field'];
            $this->remoteField = $fieldDefinition['relationship']['remote_field'];
            $this->remoteEntityRelationship = $fieldDefinition['relationship']['remote_type'];
            $this->remoteEntityName = isset($fieldDefinition['relationship']['remote_entity']) ? $fieldDefinition['relationship']['remote_entity'] : $fieldDefinition['lookup_entity'];
            $this->remoteEntityFilter = isset($fieldDefinition['relationship']['filter']) ? $fieldDefinition['relationship']['filter'] : NULL;

            // Before trying to load the remote driver, check if it has been loaded.
            $this->remoteDriverName = $fieldDefinition['relationship']['remote_driver'] = isset($fieldDefinition['relationship']['remote_driver'])?$fieldDefinition['relationship']['remote_driver']:$this->parent->getParent()->getIdentifier();
            if(!$this->parent->getParent()->isDriverLoaded($this->remoteDriverName)){
                $this->remoteDriver = $this->parent->getParent()->loadDriver($this->remoteDriverName);// : $this->parent->getParent();
            } else {
                $this->remoteDriver = $this->parent->getParent()->getDriver($this->remoteDriverName);//$this->parent->getParent()->loadDriver($this->remoteDriverName);
            }
            $this->expandable = true;
        } else if ($this->type == 'detail') {
            $this->localField = $fieldDefinition['relationship']['local_field'];
            $this->remoteField = $fieldDefinition['relationship']['remote_field'];
            $this->remoteEntityRelationship = $fieldDefinition['relationship']['remote_type'];
            $this->remoteEntityName = isset($fieldDefinition['relationship']['remote_entity']) ? $fieldDefinition['relationship']['remote_entity'] : $fieldDefinition['lookup_entity'];
            $this->remoteEntityFilter = isset($fieldDefinition['relationship']['filter']) ? $fieldDefinition['relationship']['filter'] : NULL;
            // $this->remoteDriver = isset($fieldDefinition['relationship']['remote_driver']) ? $this->parent->getParent()->loadDriver($fieldDefinition['relationship']['remote_driver']) : $this->parent->getParent();
            
            $this->remoteDriverName = $fieldDefinition['relationship']['remote_driver'] = isset($fieldDefinition['relationship']['remote_driver'])?$fieldDefinition['relationship']['remote_driver']:$this->parent->getParent()->getIdentifier();
            if(!$this->parent->getParent()->isDriverLoaded($this->remoteDriverName)){
                $this->remoteDriver = $this->parent->getParent()->loadDriver($this->remoteDriverName);// : $this->parent->getParent();
            } else {
                $this->remoteDriver = $this->parent->getParent()->getDriver($this->remoteDriverName);//$this->parent->getParent()->loadDriver($this->remoteDriverName);
            }
            $this->expandable = true;
        }

        return $this;
    }

    public function getParent() {
        return $this->parent;
    }

    public function getInternalName($actual = TRUE) {
        return $actual ? $this->actualInternalName : $this->internalName;
    }

    public function getQueryName() {
        return $this->preferredQueryName;
    }

    public function getDisplayName() {
        return $this->displayName;
    }

    /**
     * Returns an array of the internal names of the specified field array.
     *
     * @param array $fields A collection of EntityFieldDefinition references.
     * @return array
     */
    public static function getInternalNames(array $fields){
        $names = [];

        foreach($fields as $field){
            $names[] = $field->getInternalName();
        }

        return $names;
    }

    /**
     * Returns an array of the display names of the specified field array.
     *
     * @param array $fields A collection of EntityFieldDefinition references.
     * @return array
     */
    public static function getDisplayNames(array $fields){
        $names = [];

        foreach($fields as $field){
            $names[] = $field->getDisplayName();
        }

        return $names;
    }

    /**
     * Returns the data type of this field.
     *
     * @return void
     */
    public function getDataType() {
        return $this->dataType;
    }

    /**
     * Returns the name of a local field
     *
     * @return void
     */
    public function getRelatedLocalFieldName() {
        return $this->localField;
    }

    public function getRelatedLocalField() {
        return $this->parent->getFieldByDisplayName($this->localField);
    }

    public function getRemoteDriver() {
        return $this->remoteDriver;
    }

    public function getRelatedForeignFieldName() {
        return $this->remoteField;
    }

    public function getForeignEntityRelationship() {
        return $this->remoteEntityRelationship;
    }

    public function getRemoteEntityName() {
        return $this->remoteEntityName;
    }

    public function getRemoteEntityFilter() {
        return $this->remoteEntityFilter;
    }

    public function isMany() {
        switch ($this->getForeignEntityRelationship()) {
            case 'child':
            case '1:n': {
                    return true;
                }
            case 'parent':
            case '1:1':
            case 'sibling':
            default: {
                    return false;
                }
        }
    }

    public function isArray() {
        if ($this->isAnArray == 1) {
            return true;
        }
        return false;
    }

    public function isBlob() {
        if ($this->dataType == 'photo' || $this->dataType == 'blob') {
            return true;
        }
        return false;
    }

    public function isPhoto() {
        if ($this->dataType == 'photo') {
            return true;
        }
        return false;
    }

    /**
     * Returns true if the return data type of this field is integer.
     *
     * @return boolean
     */
    public function isInteger() {
        if ($this->dataType == 'int') {
            return true;
        }
        return false;
    }

    /**
     * Returns true if the return data type of this field is String.
     *
     * @return boolean
     */
    public function isString(){
        if ($this->dataType == 'string') {
            return true;
        }
        return false;
    }

    /**
     * Returns true if the return data type of this field is DateTime.
     *
     * @return boolean
     */
    public function isDateTime(){
        if ($this->dataType == 'datetime') {
            return true;
        }
        return false;
    }

    /**
     * Returns true if the return data type of this field is Date.
     *
     * @return boolean
     */
    public function isDate(){
        if ($this->dataType == 'date') {
            return true;
        }
        return false;
    }

    public function isExpandable() {
        return $this->expandable;
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

        throw new \Exception("The time format is not known. class EntityFieldDefinition {$value}");
    }

    public function getStringValue(){
        if($this->isDate()){
            return $this->getValue($val)->format('Y-m-d');
        } else if($this->isDateTime()){
            return $this->getValue($val)->format('Y-m-d\\TH:m:s');
        }
    }

    public function getValue($val){
        if($this->isDate()){
            return $this->getDateTime($val);
        } else if($this->isDateTime()){
            return $this->getDateTime($val);
        }
    }

}
