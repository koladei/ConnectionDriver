<?php

namespace com\mainone\middleware;

include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/EntityDefinitionBrowser.php');

use com\mainone\middleware\EntityDefinitionBrowser;

class Order {
    private $field;
    private $order;
    private $entityBrowser;

    public function __construct(EntityDefinitionBrowser $browser, $field, $order = 'asc') {
        $this->entityBrowser = $browser;
        $this->field = $field;
        $this->order = (is_null($order)|| trim($order) == '')? 'asc': strtolower($order);
    }
    
    public function getField(){
        return $this->entityBrowser->getFieldByDisplayName($this->field);
    }

    public function getOrder(){
        return $this->order;
    }
}