<?php

namespace com\mainone\middleware;

include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/MiddlewareQueryFragment.php');
include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/MiddlewareOdataFilterParser.php');

use com\mainone\middleware\MiddlewareQueryFragment;
use com\mainone\middleware\MiddlewareOdataFilterParser;

/**
 * Description of LDAPConnectionDriver
 *
 * @author Kolade.Ige
 */
class MiddlewareConnectionDriver {

    private $select = [];
    private $expand = [];
    private $query = [];

    public function selectFields(array $fields = []) {
        $this->select = $fields;
        return $this;
    }

    public function expandFields($field, array $fields = []) {
        $this->expand[$field] = $fields;
        return $this;
    }

    public function getItemById() {
        
    }

    public function getItemsByQuery() {
        
    }  
        
    public function parseExpression(){
        $parser = MiddlewareOdataFilterParser::reduce('(Fabac eq 123) or (Faba eq 123 and (ax lt 1))');
        return $parser;
    }
}
