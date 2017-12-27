<?php

namespace com\mainone\middleware;

include_once 'EntityFieldDefinition.php';
include_once 'MiddlewareConnectionDriver.php';
include_once 'EncoderDecoder.php';
include_once 'Exceptions.php';

use com\mainone\middleware\EntityFieldDefinition;
use com\mainone\middleware\MiddlewareConnectionDriver;
use com\mainone\middleware\EncoderDecoder;
use com\mainone\middleware\InvalidFieldSelectedException;

/**
 * Description of EntityDefinitionBrowser
 *
 * @author Kolade.Ige
 */
class EntityDefinitionBrowser
{

    private $parent;
    private $internalName;
    private $displayName;
    private $soapMethods = null;
    private $idField;
    private $fieldsByDisplayName = [];
    private $fieldsByInternalName = [];
    private $mandatoryFields = ['Id'];
    private $renameStrategy = null;
    private $fieldValueFetchStrategy = null;
    private $mergeExpansionChunksStrategy = null;
    private $expansionJoinStrategy = null;
    private $dataSource = 'default';
    private $cacheData = false;
    private $context = 'default';
    private $cachingDriverName = null;
    private $delegateStorage = FALSE; // TRUE means that the driver underwhich this connection driver is stored is not the final store.
    private $delegateDriverName = NULL;
    private $manageTimestamps = FALSE;
    private $redirectReadTo = FALSE;
    private $redirectCreateTo = FALSE;
    private $redirectDeleteTo = FALSE;
    private $redirectUpdateTo = FALSE;
    private $originalDisplayName = NULL;
    private $originalInternalName = NULL;
    private $originalDriverName = NULL;

    public function __construct($internalName, array &$definition, MiddlewareConnectionDriver &$parent)
    {
        $this->parent = $parent;
        $this->displayName = $internalName;
        $this->internalName = $definition['internal_name'];
        
        if (isset($definition['original_internal_name'])) {
            $this->originalInternalName = $definition['original_internal_name'];
        }
        
        if (isset($definition['original_display_name'])) {
            $this->originalDisplayName = $definition['original_display_name'];
        }
        
        if (isset($definition['original_driver_name'])) {
            $this->originalDriverName = $definition['original_driver_name'];
        }
        
        if (isset($definition['soap_methods'])) {
            $this->soapMethods = (object) $definition['soap_methods'];
        }

        if (isset($definition['datasource'])) {
            $this->dataSource = $definition['datasource'];
        }
        
        if(isset($definition['manage_timestamps'])){
            $this->manageTimestamps = $definition['manage_timestamps'];
        }
        
        // Collect information about method redirection
        if(isset($definition['redirects'])){
            if(isset($definition['redirects']['read'])){
                $this->redirectReadTo = (object)$definition['redirects']['read'];

                // if the driver is missing, default to this entity's driver
                if(!(\property_exists($this->redirectReadTo, 'driver')) || is_null($this->redirectReadTo->driver)){
                    $this->redirectReadTo->driver = $this->getParent()->getIdentifier();
                }
            }
            
            if(isset($definition['redirects']['create'])){
                $this->redirectCreateTo = (object)$definition['redirects']['create'];
                
                // if the driver is missing, default to this entity's driver
                if(!(\property_exists($this->redirectCreateTo, 'driver')) || is_null($this->redirectCreateTo->driver)){
                    $this->redirectCreateTo->driver = $this->getParent()->getIdentifier();
                }
            }
            
            if(isset($definition['redirects']['update'])){
                $this->redirectUpdateTo = (object)$definition['redirects']['update'];                

                // if the driver is missing, default to this entity's driver
                if(!(\property_exists($this->redirectUpdateTo, 'driver')) || is_null($this->redirectUpdateTo->driver)){
                    $this->redirectUpdateTo->driver = $this->getParent()->getIdentifier();
                }
            }
            
            if(isset($definition['redirects']['delete'])){
                $this->redirectDeleteTo = (object)$definition['redirects']['delete'];
                

                // if the driver is missing, default to this entity's driver
                if(!(\property_exists($this->redirectDeleteTo, 'driver')) || is_null($this->redirectDeleteTo->driver)){
                    $this->redirectDeleteTo->driver = $this->getParent()->getIdentifier();
                }
            }
        }

        if (isset($definition['context'])) {
            $this->context = $definition['context'];
        }
                
        if (isset($definition['cache_to'])) {
            $this->cacheData = true;
            $this->cachingDriverName = $definition['cache_to'];
        }
        
        if (isset($definition['delegate_to'])) {
            $this->delegateStorage = true;
            $this->delegateDriverName = $definition['delegate_to'];
        }

        $this->setFields($definition['fields']);
        return $this;
    }
    
    /**
     * Checks if this entity definition mirrors another entity's read operation
     *
     * @return void
     */
    public function shouldRedirectRead(){
        if($this->redirectReadTo != FALSE){
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Returns information about the entity browser and driver to read from
     *
     * @return void
     */
    public function getReadProviderInfo(){
        return $this->redirectReadTo;
    }
    
    
    /**
     * Checks if this entity definition mirrors another entity's create operation
     *
     * @return void
     */
    public function shouldRedirectCreate(){
        if($this->redirectCreateTo != FALSE){
            return TRUE;
        }
        return FALSE;
    }
    
    /**
     * Returns information about the entity browser and driver to create to
     *
     * @return void
     */
    public function getCreateProviderInfo(){
        return $this->redirectCreateTo;
    }
        
    /**
     * Checks if this entity definition mirrors another entity's update operation
     *
     * @return void
     */
    public function shouldRedirectUpdate(){
        if($this->redirectUpdateTo != FALSE){
            return TRUE;
        }
        return FALSE;
    }
    

    /**
     * Returns information about the entity browser and driver to write updates to
     *
     * @return void
     */
    public function getUpdateProviderInfo(){
        return $this->redirectUpdateTo;
    }
    
    /**
     * Checks if this entity definition mirrors another entity's delete operation
     *
     * @return void
     */
    public function shouldRedirectDelete(){
        if($this->redirectDeleteTo != FALSE){
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Returns information about the entity browser and driver to read from
     *
     * @return void
     */
    public function getDeleteProviderInfo(){
        return $this->redirectDeleteTo;
    }

    /**
     * Checks whether timestamps like created and modified dates should be managed by the driver hosting this entity.
     *
     * @return void
     */
    public function shouldManageTimestamps(){
        return $this->manageTimestamps;
    }

    /**
     * Returns the name of the driver that the information of this entity is cached to.
     *
     * @return void
     */
    public function getCachingDriverName()
    {
        return $this->cachingDriverName;
    }

    /**
     * Returns the MiddlewareConnectionDriver that instantiated this entity.
     *
     * @return void
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * For cached objects, returns the driver of the source object otherwise returns the drive of this object.
     *
     * @return void
     */
    public function getOriginalParent()
    {
        return is_null($this->originalDriverName) ? $this->parent : $this->parent->loadDriver($this->originalDriverName);
    }
    
    /**
     * For cached objects, returns the Object browser that is being cached.
     *
     * @return void
     */
    public function getCachedObject(){
        return $this->getOriginalParent()->getEntityBrowser($this->getOriginalDisplayName());
    }
    
    /**
     * For non-cached objects, returns the Object browser that is responsible for caching
     *
     * @return void
     */
    public function getCachingObject(){
        if($this->getParent()->getIdentifier() != $this->getCachingDriverName()){
            $driver = $this->getParent()->loadDriver($this->getCachingDriverName());
            $cachingObjectName = strtolower("{$this->getParent()->getIdentifier()}__{$this->getInternalName()}");
            return $driver->getEntityBrowser($cachingObjectName);
        } else {
            return FALSE;
        }
    }
    
    public function getDisplayName()
    {
        return $this->displayName;
    }
    
    /**
     * If this a cache of another source, returns the name of the original source
     */
    public function getOriginalDisplayName()
    {
        return is_null($this->originalDisplayName)?$this->displayName:$this->originalDisplayName;
    }

    public function setDisplayName($name)
    {
        $this->displayName = $name;
        return $this;
    }

    public function getInternalName()
    {
        return $this->internalName;
    }
    
    public function getOriginalInternalName()
    {
        return is_null($this->originalInternalName)?$this->internalName:$this->originalInternalName;
    }

    public function setInternalName($name)
    {
        $this->internalName = $name;
        return $this;
    }

    public function getMandatoryFieldNames()
    {
        return $this->mandatoryFields;
    }
    
    public function getSoapMethods()
    {
        return $this->soapMethods;
    }

    public function getDataSourceName()
    {
        return $this->dataSource;
    }
    
    public function shouldCacheData()
    {
        return $this->cacheData;
    }
    
    public function delegatesStorage()
    {
        return $this->delegateStorage;
    }
    
    public function getCacheDriverName()
    {
        return $this->cachingDriverName;
    }
    
    public function getDelegateDriverName()
    {
        return $this->delegateDriverName;
    }

    public function getContext()
    {
        return $this->context;
    }

    private function setFields(array $fields)
    {

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

    public function setField(EntityFieldDefinition $fieldDef)
    {
        $internalName = $fieldDef->getInternalName(false);
        $this->fieldsByInternalName[$internalName] = $fieldDef;
        $this->fieldsByDisplayName[$fieldDef->getDisplayName()] = &$this->fieldsByInternalName[$internalName];
        if (isset($field['mandatory']) && ($field['mandatory'] == 1 || $field['mandatory'] == true)) {
            if ($fieldDef->isExpandable()) {
                if (!in_array($fieldDef->getRelatedLocalFieldName(), $this->mandatoryFields)) {
                    $this->mandatoryFields[] = $fieldDef->getRelatedLocalFieldName();
                }
            } else {
                if (!in_array($fieldDef->getDisplayName(), $this->mandatoryFields)) {
                    $this->mandatoryFields[] = $fieldDef->getDisplayName();
                }
            }
        }
    }

    /**
     * Returns array of the internal names of the fields supplied as parameter.
     *
     * @param array $fieldNames
     * @return array
     */
    public function getFieldInternalNames(array $fieldNames)
    {
        $fieldNames2 = [];
        foreach ($fieldNames as $fieldName) {
            if (isset($this->fieldsByDisplayName[$fieldName])) {
                $fieldInfo = $this->fieldsByDisplayName[$fieldName];
                if ($fieldInfo->isExpandable()) {
                    $fieldNames2[] = $fieldInfo->getRelatedLocalField()->getInternalName();
                } else {
                    $fieldNames2[] = $fieldInfo->getInternalName();
                }
            } 
            else {
                throw new InvalidFieldSelectedException("Field '{$fieldName}' does not exist in entity '{$this->getCachedObject()->getDisplayName()}'");
            }
        }

        return array_values($fieldNames2);
    }

    /**
     * Checks whether this entity has a field with the specified name and type.
     */
    public function hasField($name, $nameType = 'display'){
        try {
            switch($nameType){
                case 'display':{
                    return $this->getFieldByDisplayName($name);
                }
                default:{
                    return $this->getFieldByInternalName($name);
                }
            }
        } catch (\Exception $exp) {
            return FALSE;
        }
    }

    /**
     * Returns an array of EntityFieldDefinition references based the supplied array of internal names.
     *
     * @param array $fieldNames
     * @return array
     */
    public function getFieldsByInternalNames(array $fieldNames = [])
    {
        $fieldNames = count($fieldNames) < 1? array_keys($this->fieldsByInternalName) : $fieldNames;
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
    public function getFieldsByDisplayNames(array $fieldNames = null)
    {
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
    public function getFieldInternalToDisplayNames(array $fieldNames = null)
    {
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
    public function getFieldDisplayToInternalNames(array $fieldNames = null)
    {
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
    public function getValidFieldsByDisplayName(array $fieldNames = null)
    {
        $is_null = is_null($fieldNames);
        $fieldNames = $is_null ? array_keys($this->fieldsByDisplayName) : $fieldNames;
        $r = [];

        if ($is_null) {
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
    public function getValidFieldsByInternalName(array $fieldNames = null)
    {
        $is_null = is_null($fieldNames);
        $fieldNames = $is_null ? array_keys($this->fieldsByInternalName) : $fieldNames;
        $r = [];

        if ($is_null) {
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
    public function getIdField()
    {
        return $this->idField;
    }

    /**
     * Returns a reference to the field with the specified internal name.
     *
     * @param string $name
     * @return EntityFieldDefinition
     */
    public function getFieldByInternalName($name)
    {
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
    public function getFieldByDisplayName($name = '')
    {
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
    public function setRenameStrategy($strategy)
    {
        $this->renameStrategy = $strategy;
    }

    /**
     * Returns the fields of the entity that have the specified types.
     *
     * @param array $typeNames
     * @param [type] $fieldNames
     * @return void
     */
    public function getFieldsOfTypeByDisplayName(array $typeNames, array $fieldNames = null)
    {
        $fields = $this->getValidFieldsByDisplayName($fieldNames);
        $matched = [];

        foreach ($fields as $field) {
            if (in_array($field->getDataType(), $typeNames)) {
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
    public function getFieldsOfTypeByInternalName(array $typeNames, array $fieldNames = null)
    {
        $fields = $this->getValidFieldsByInternalName($fieldNames);
        $matched = [];

        foreach ($fields as $field) {
            if (in_array($field->getDataType(), $typeNames)) {
                $matched[] = $field;
            }
        }

        // unset($fields);
        return $matched;
    }

    public function setFieldValueFetchStrategy($strategy)
    {
        $this->fieldValueFetchStrategy = $strategy;
    }

    public function setMergeExpansionChunksStrategy(callable $strategy)
    {
        $this->mergeExpansionChunksStrategy = $strategy;
    }

    public function setExpansionJoinStrategy(callable $strategy)
    {
        $this->expansionJoinStrategy = $strategy;
    }

    public function setReverseRenameStrategy($strategy)
    {
        $this->reverseRenameStrategy = $strategy;
    }

    public function reverseRenameFields($record, $type = 'normal')
    {
        if (is_callable($this->reverseRenameStrategy)) {
            $rename = $this->reverseRenameStrategy;
            $scope = $this;
            return $rename(...array_merge([$scope], func_get_args()));
        } else {
            return $record;
        }
    }

    public function renameFields($record, $selected_fields)
    {
        if (is_callable($this->renameStrategy)) {
            $rename = $this->renameStrategy;
            return $rename(...func_get_args());
        } else {
            $r = new \stdClass();

            foreach ($selected_fields as $key => $displayName) {
                if (property_exists($record, $key)) {
                    $r->{$displayName} = $record->{$key};
                } elseif (is_array($record) && isset($record[$key])) {
                    $r->{$displayName} = $record[$key];
                }
            }

            return $r;
        }
    }

    public function fetchFieldValues($record, $selected_field)
    {
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

    public function mergeExpansionChunks($data, $chunkResult, EntityFieldDefinition $localFieldInfo, EntityFieldDefinition $fieldInfo)
    {
        $mergeExpansion = $this->mergeExpansionChunksStrategy;
        if (!is_null($mergeExpansion)) {
            return $mergeExpansion(...func_get_args());
        } else {
            // Watch this: Not clear why this is necessary but it helped caching
            $data = is_null($data) ? [] : $data;
            $data = array_merge($data, $chunkResult);
            return $data;
        }
    }

    public function joinExpansionToParent($recordIndex, $record, $fieldInfo, $vals)
    {
        // if($fieldInfo->getDisplayName() == 'CI'){
        //     echo 'here';
        // }
        if (is_callable($this->expansionJoinStrategy)) {
            $join = $this->expansionJoinStrategy;
            return $join(...func_get_args());
        } else {
            return $record;
        }
    }
}
