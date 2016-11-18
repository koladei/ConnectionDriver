<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace com\mainone\middleware;

using \com\mainone\middleware\MiddlewareConnectionDriverInterface;

/**
 * Description of SalesforceConnectionDriver
 *
 * @author Kolade.Ige
 */
class SalesforceConnectionDriver implements MiddlewareConnectionDriverInterface {

    private $entityName;
    private $entityInternalName;
    private $connectionToken = NULL;
    private $selectedFields = [];
    private $keyField = NULL;
    
    public function __construct($entityName) {
        $this->entityName = $entityName;
        return $this;
    }

    public function getSelectedFields(array &$select) {
        $this->selectedFields = $select;
        return $this;
    }

    public function setSelectedFields(array $select) {
        $this->selectedFields = $select;
        return $this;
    }

    public function setConnectionToken(stdClass $token_response) {
        $this->connectionToken = $token_response;
        return $this;
    }

    public function getConnectionToken(stdClass &$token_response) {
        $token_response = $this->connectionToken;
        return $this;
    }

    public function setKeyField($key_field) {
        $this->keyField = $key_field;
        return $this;        
    }

    public function getItemsByIds(array &$items, array $Ids = [], array $notable_fields = []) {
        return $this;
    }

    public function getItemsByFieldValues(array &$items, $field_name, array $values = [], array $notable_fields = []) {
        return $this;        
    }

    public function getItemsByFilter(array &$items, array $filter = [], array $notable_fields = []) {
        return $this;        
    }

}
