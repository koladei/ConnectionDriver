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
    private $remoteEntityName;
    private $expandable = false;
    private $isAnArray = 0;

    public function __construct($name, array $fieldDefinition, EntityDefinitionBrowser &$parent) {
        $this->parent = $parent;
        $this->internalName = $name;
        $this->actualInternalName = $name;
        $this->preferredQueryName = isset($fieldDefinition['preferred_query_name'])?$fieldDefinition['preferred_query_name']:$name;
        $this->displayName = $fieldDefinition['preferred_name'];
        $this->type = $fieldDefinition['type'];
        $this->isAnArray = isset($fieldDefinition['is_array'])?$fieldDefinition['is_array']:0;
        if($this->type != 'detail' && isset($fieldDefinition['relationship'])) {
            if($this->localField = $fieldDefinition['relationship']['local_field'] == $fieldDefinition['preferred_name']){
                $idName = isset($fieldDefinition['relationship']['preferred_local_key_name'])?$fieldDefinition['relationship']['preferred_local_key_name']:"{$fieldDefinition['relationship']['local_field']}Key";
                $fieldDefinition['relationship']['local_field'] = $idName;//"{$fieldDefinition['relationship']['local_field']}Id";
                $this->internalName = "{$name}_lookup";
                $this->actualInternalName = $name;
                $this->type = 'detail';
                $x = $fieldDefinition;
                $x['preferred_name'] = $fieldDefinition['relationship']['local_field'];
                unset($x['relationship']);
                $fieldDef = new EntityFieldDefinition($name, $x, $parent);
                $parent->setField($fieldDef);
            }

            $this->localField = $fieldDefinition['relationship']['local_field'];
            $this->remoteField = $fieldDefinition['relationship']['remote_field'];
            $this->remoteEntityRelationship = $fieldDefinition['relationship']['remote_type'];
            $this->remoteEntityName = isset($fieldDefinition['relationship']['remote_entity'])?$fieldDefinition['relationship']['remote_entity']: $fieldDefinition['lookup_entity'];
            $this->remoteEntityFilter = isset($fieldDefinition['relationship']['filter'])?$fieldDefinition['relationship']['filter']: NULL;
            $this->remoteDriver = isset($fieldDefinition['relationship']['remote_driver'])? $this->parent->getParent()->loadDriver($fieldDefinition['relationship']['remote_driver']): $this->parent->getParent();
            $this->expandable = true;
        }
        else if($this->type == 'detail') {
            $this->localField = $fieldDefinition['relationship']['local_field'];
            $this->remoteField = $fieldDefinition['relationship']['remote_field'];
            $this->remoteEntityRelationship = $fieldDefinition['relationship']['remote_type'];
            $this->remoteEntityName = isset($fieldDefinition['relationship']['remote_entity'])?$fieldDefinition['relationship']['remote_entity']: $fieldDefinition['lookup_entity'];
            $this->remoteEntityFilter = isset($fieldDefinition['relationship']['filter'])?$fieldDefinition['relationship']['filter']: NULL;
            $this->remoteDriver = isset($fieldDefinition['relationship']['remote_driver'])? $this->parent->getParent()->loadDriver($fieldDefinition['relationship']['remote_driver']): $this->parent->getParent();
            $this->expandable = true;
        }

        return $this;
    }

    public function getParent(){
        return $this->parent;
    }

    public function getInternalName($actual = TRUE){
        return $actual?$this->actualInternalName: $this->internalName;
    }

    public function getQueryName(){
        return $this->preferredQueryName;
    }

    public function getDisplayName(){
        return $this->displayName;
    }

    public function getDataType(){
        return $this->type;
    }

    public function getRelatedLocalFieldName(){
        return $this->localField;
    }

    public function getRelatedLocalField() {
        return $this->parent->getFieldByDisplayName($this->localField);
    }

    public function getRemoteDriver() {
        return $this->remoteDriver;
    }

    public function getRelatedForeignFieldName(){
        return $this->remoteField;
    }

    public function getForeignEntityRelationship(){
        return $this->remoteEntityRelationship;
    }

    public function getRemoteEntityName(){
        return $this->remoteEntityName;
    }

    public function getRemoteEntityFilter(){
        return $this->remoteEntityFilter;
    }

    public function isMany(){
        switch($this->getForeignEntityRelationship()){
            case 'child':{
                return true;
            }
            case 'parent':
            default:{
                return false;
            }
        }
    }

    public function isArray(){
        if($this->isAnArray == 1){
            return true;
        }
        return false;
    }

    public function isBlob(){
        if($this->type == 'photo' || $this->type == 'blob' ){
            return true;
        }
        return false;
    }

    public function isPhoto(){
        if($this->type == 'photo'){
            return true;
        }
        return false;
    }

    public function isExpandable(){
        return $this->expandable;
    }
}
