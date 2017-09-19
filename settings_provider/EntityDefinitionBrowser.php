<?php

namespace com\mainone\middleware;

include_once 'EntityFieldDefinition.php';
include_once 'MiddlewareConnectionDriver.php';
include_once 'EncoderDecoder.php';

use com\mainone\middleware\EntityFieldDefinition;
use com\mainone\middleware\MiddlewareConnectionDriver;
use com\mainone\middleware\EncoderDecoder;

/**
 * Description of EntityDefinitionBrowser
 *
 * @author Kolade.Ige
 */
class EntityDefinitionBrowser {

    private $parent;
    private $internalName;
    private $displayName;
    private $soapMethods = NULL;
    private $idField;
    private $fieldsByDisplayName = [];
    private $fieldsByInternalName = [];
    private $mandatoryFields = ['Id'];
    private $renameStrategy = NULL;
    private $fieldValueFetchStrategy = NULL;
    private $mergeExpansionChunksStrategy = NULL;
    private $expansionJoinStrategy = NULL;
    private $dataSource = 'default';
    private $cacheData = false;
    private $context = 'default';
    private $cachingDriverName = NULL;

    public function __construct($internalName, array &$definition, MiddlewareConnectionDriver &$parent) {
        $this->parent = $parent;
        $this->displayName = $internalName;
        $this->internalName = $definition['internal_name'];
        
        if (isset($definition['soap_methods'])) {
            $this->soapMethods = (object) $definition['soap_methods'];
        }

        if (isset($definition['datasource'])){
            $this->dataSource = $definition['datasource'];
        }

        if (isset($definition['context'])){
            $this->context = $definition['context'];
        }
        
        // if (isset($definition['cache'])){
        //     $this->cacheData = $definition['cache'];
        // }
        
        if (isset($definition['cache_to'])){
            $this->cacheData = true;
            $this->cachingDriverName = $definition['cache_to'];
        }

        $this->setFields($definition['fields']);
        return $this;
    }

    public function getCachingDriverName(){
        return $this->cachingDriverName;
    }

    public function getParent() {
        return $this->parent;
    }

    public function getDisplayName() {
        return $this->displayName;
    }

    public function setDisplayName($name) {
        $this->displayName = $name;
        return $this;
    }

    public function getInternalName() {
        return $this->internalName;
    }

    public function setInternalName($name) {
        $this->internalName = $name;
        return $this;
    }

    public function getMandatoryFieldNames() {
        return $this->mandatoryFields;
    }
    
    public function getSoapMethods(){
        return $this->soapMethods;
    }

    public function getDataSourceName(){
        return $this->dataSource;
    }
    
    public function shouldCacheData(){
        return $this->cacheData;
    }
    
    public function getCacheDriverName(){
        return $this->cacheDriverName;
    }

    public function getContext(){
        return $this->context;
    }

    private function setFields(array $fields) {

        foreach ($fields as $internalName => $field) {
            $fieldDef = new EntityFieldDefinition($internalName, $field, $this);
            $this->setField($fieldDef);
        }

        if (!isset($this->fieldsByDisplayName['Id'])) {
            if (count($this->fieldsByDisplayName) > 0) {
                reset($this->fieldsByDisplayName);
                $first_key = key($this->fieldsByDisplayName);
                $this->idField = &$this->fieldsByDisplayName[$first_key];
            } else {
                throw new \Exception("The Entity '{$this->displayName}' has no fields");
            }
        } else {
            $this->idField = &$this->fieldsByDisplayName['Id'];
        }

        return $this;
    }

    public function setField(EntityFieldDefinition $fieldDef) {
        $internalName = $fieldDef->getInternalName(FALSE);
        $this->fieldsByInternalName[$internalName] = $fieldDef;
        $this->fieldsByDisplayName[$fieldDef->getDisplayName()] = &$this->fieldsByInternalName[$internalName];
        if (isset($field['mandatory']) && ($field['mandatory'] == 1 || $field['mandatory'] == TRUE)) {
            if ($fieldDef->isExpandable()) {
                if (!in_array($fieldDef->getRelatedLocalFieldName(), $this->mandatoryFields))
                    $this->mandatoryFields[] = $fieldDef->getRelatedLocalFieldName();
            } else {
                if (!in_array($fieldDef->getDisplayName(), $this->mandatoryFields))
                    $this->mandatoryFields[] = $fieldDef->getDisplayName();
            }
        }
    }

    /**
     * Returns array of the internal names of the fields supplied as parameter.
     *
     * @param array $fieldNames
     * @return array
     */
    public function getFieldInternalNames(array $fieldNames) {
        foreach ($fieldNames as &$fieldName) {
            if (isset($this->fieldsByDisplayName[$fieldName])) {
                $fieldInfo = $this->fieldsByDisplayName[$fieldName];
                if ($fieldInfo->isExpandable()) {
                    $fieldName = $fieldInfo->getRelatedLocalField()->getInternalName();
                } else {
                    $fieldName = $fieldInfo->getInternalName();
                }
            } else {
                unset($fieldName);
            }
        }

        return array_values($fieldNames);
    }

    /**
     * Returns an array of EntityFieldDefinition references based the supplied array of internal names.
     *
     * @param array $fieldNames
     * @return array
     */
    public function getFieldsByInternalNames(array $fieldNames = NULL) {
        $fieldNames = is_null($fieldNames) ? array_keys($this->fieldsByInternalName) : $fieldNames;
        $r = [];

        foreach ($fieldNames as $fieldName) {
            if (isset($this->fieldsByInternalName[$fieldName])) {
                $iName = $this->fieldsByInternalName[$fieldName];
                $r[$fieldName] = $iName;
            } else {
                throw new \Exception("Field with internal name '{$fieldName}' does not exist in Entity '{$this->displayName}'.");
            }
        }

        return $r;
    }

    /**
     * Returns an array of EntityFieldDefinition references based the supplied array of display names.
     *
     * @param array $fieldNames
     * @return array
     */
    public function getFieldsByDisplayNames(array $fieldNames = NULL) {
        $fieldNames = is_null($fieldNames) ? array_keys($this->fieldsByDisplayName) : $fieldNames;
        $r = [];

        foreach ($fieldNames as $fieldName) {
            if (isset($this->fieldsByDisplayName[$fieldName])) {
                $iName = $this->fieldsByDisplayName[$fieldName];
                $r[$fieldName] = $iName;
            } else {
                throw new \Exception("Field with display name '{$fieldName}' does not exist in Entity '{$this->displayName}'.");
            }
        }

        return $r;
    }

    /**
     * Returns a key-value array of fields names.
     * The internal name is the key while the display name is the value.
     * Returns all fields if $fieldNames is null or not provided at all.
     *
     * @param array $fieldNames
     * @return array
     */
    public function getFieldInternalToDisplayNames(array $fieldNames = NULL) {
        $fieldNames = is_null($fieldNames) ? array_keys($this->fieldsByInternalName) : $fieldNames;
        $r = [];

        foreach ($fieldNames as $fieldName) {
            if (isset($this->fieldsByInternalName[$fieldName])) {
                $iName = $this->fieldsByInternalName[$fieldName]->getDisplayName();
                $r[$fieldName] = $iName;
            } else {
                throw new \Exception("Field with internal name '{$fieldName}' does not exist in Entity '{$this->displayName}'.");
            }
        }

        return $r;
    }

    /**
     * Returns a key-value array of fields names.
     * The display name is the key while the internal name is the value.
     * Returns all fields if $fieldNames is null or not provided at all.
     *
     * @param array $fieldNames
     * @return array
     */
    public function getFieldDisplayToInternalNames(array $fieldNames = NULL) {
        $fieldNames = is_null($fieldNames) ? array_keys($this->fieldsByDisplayName) : $fieldNames;
        $r = [];

        foreach ($fieldNames as $fieldName) {
            if (isset($this->fieldsByDisplayName[$fieldName])) {
                $iName = $this->fieldsByDisplayName[$fieldName]->getInternalName();
                $r[$fieldName] = $iName;
            } else {
                throw new \Exception("Field with display name '{$fieldName}' does not exist in Entity '{$this->displayName}'.");
            }
        }

        return $r;
    }

    /**
     * Returns an array of EntityFieldDefinition references based on the array of display names provided.
     * Ignores invalid fields.
     * 
     * @param array $fieldNames
     * @return array
     */
    public function getValidFieldsByDisplayName(array $fieldNames = NULL) {
        $is_null = is_null($fieldNames);
        $fieldNames = $is_null ? array_keys($this->fieldsByDisplayName) : $fieldNames;
        $r = [];

        if($is_null){
            $r = array_values($this->fieldsByDisplayName);
        } else {
            foreach ($fieldNames as $fieldName) {
                if (isset($this->fieldsByDisplayName[$fieldName])) {
                    $r[] = $this->fieldsByDisplayName[$fieldName];
                }
            }
        }

        return $r;
    }

    /**
     * Returns an array of EntityFieldDefinition references based on the array of display names provided.
     * Ignores invalid fields.
     * 
     * @param array $fieldNames
     * @return array
     */
    public function getValidFieldsByInternalName(array $fieldNames = NULL) {
        $is_null = is_null($fieldNames);
        $fieldNames = $is_null ? array_keys($this->fieldsByInternalName) : $fieldNames;
        $r = [];

        if($is_null){
            $r = array_values($this->fieldsByInternalName);
        } else {
            foreach ($fieldNames as $fieldName) {
                if (isset($this->fieldsByInternalName[$fieldName])) {
                    $r[] = $this->fieldsByInternalName[$fieldName];
                }
            }
        }

        return $r;
    }

    /**
     * Returns a reference to the Id field.
     *
     * @return EntityFieldDefinition
     */
    public function getIdField() {
        return $this->idField;
    }

    /**
     * Returns a reference to the field with the specified internal name.
     *
     * @param string $name
     * @return EntityFieldDefinition
     */
    public function getFieldByInternalName($name) {
        if (isset($this->fieldsByInternalName[$name])) {
            return $this->fieldsByInternalName[$name];
        } else {
            throw new \Exception("Field with internal name '{$name}' does not exist in Entity '{$this->displayName}'.");
        }
    }

    /**
     * Returns a reference to the field with the specified display name.
     *
     * @param [type] $name
     * @return void
     */
    public function getFieldByDisplayName($name) {
        if (isset($this->fieldsByDisplayName[$name])) {
            return $this->fieldsByDisplayName[$name];
        } else {
            throw new \Exception("Field with display name '{$name}' does not exist in Entity '{$this->displayName}'.");
        }
    }

    /**
     * Sets $strategy as the function used to rename fields of this EntityDefinitionBrowser.
     *
     * @param [type] $strategy
     * @return void
     */
    public function setRenameStrategy($strategy) {
        $this->renameStrategy = $strategy;
    }

    /**
     * Returns the fields of the entity that have the specified types.
     *
     * @param array $typeNames
     * @param [type] $fieldNames
     * @return void
     */
    public function getFieldsOfTypeByDisplayName(array $typeNames, array $fieldNames = NULL){
        $fields = $this->getValidFieldsByDisplayName($fieldNames);
        $matched = [];

        foreach($fields as $field){
            if(in_array($field->getDataType(), $typeNames)){
                $matched[] = $field;
            }
        }

        // unset($fields);
        return $matched;
    }

    /**
     * Returns the fields of the entity that have the specified types.
     *
     * @param array $typeNames
     * @param [type] $fieldNames
     * @return void
     */
    public function getFieldsOfTypeByInternalName(array $typeNames, array $fieldNames = NULL){
        $fields = $this->getValidFieldsByInternalName($fieldNames);
        $matched = [];

        foreach($fields as $field){
            if(in_array($field->getDataType(), $typeNames)){
                $matched[] = $field;
            }
        }

        // unset($fields);
        return $matched;
    }

    public function setFieldValueFetchStrategy($strategy) {
        $this->fieldValueFetchStrategy = $strategy;
    }

    public function setMergeExpansionChunksStrategy($strategy) {
        $this->mergeExpansionChunksStrategy = $strategy;
    }

    public function setExpansionJoinStrategy($strategy) {
        $this->expansionJoinStrategy = $strategy;
    }

    public function setReverseRenameStrategy($strategy) {
        $this->reverseRenameStrategy = $strategy;
    }

    public function reverseRenameFields($record) {
        if (is_callable($this->reverseRenameStrategy)) {
            $rename = $this->reverseRenameStrategy;
            $scope = $this;
            return $rename(...array_merge([$scope], func_get_args()));
        } else {
            return $record;
        }
    }

    public function renameFields($record, $selected_fields) {
        if (is_callable($this->renameStrategy)) {
            $rename = $this->renameStrategy;
            return $rename(...func_get_args());
        } else {
            $r = new \stdClass();

            foreach ($selected_fields as $key => $displayName) {
                if (property_exists($record, $key)) {
                    $r->{$displayName} = $record->{$key};
                } else if (is_array($record) && isset($record[$key])){
                    $r->{$displayName} = $record[$key];
                }
            }

            return $r;
        }
    }

    public function fetchFieldValues($record, $selected_field) {
        if (is_callable($this->fieldValueFetchStrategy)) {
            $fetch = $this->fieldValueFetchStrategy;
            return $fetch(...func_get_args());
        } else {
            $r = [];

            if (is_object($record) && property_exists($record, $selected_field) && !is_null($record->{$selected_field})) {
                $v = "{$record->{$selected_field}}";
                $r[] = EncoderDecoder::escapeinner($v);
            }

            return $r;
        }
    }

    public function mergeExpansionChunks($data, $chunkResult, EntityFieldDefinition $localFieldInfo, EntityFieldDefinition $fieldInfo) {
        if (is_callable($this->mergeExpansionChunksStrategy)) {
            $mergeExpansion = $this->mergeExpansionChunksStrategy;
            return $mergeExpansion(...func_get_args());
        } else {
            // $data = is_null($data) ? [] : $data;
            // $data += $chunkResult;
            return $data;
        }
    }

    public function joinExpansionToParent($record, $fieldInfo, $vals) {
        if (is_callable($this->expansionJoinStrategy)) {
            $join = $this->expansionJoinStrategy;
            return $join(...func_get_args());
        } else {
            return $record;
        }
    }

}
