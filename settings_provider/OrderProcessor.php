<?php

namespace com\mainone\middleware;

include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/EntityDefinitionBrowser.php');
include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/Order.php');

use com\mainone\middleware\EntityDefinitionBrowser;
use com\mainone\middleware\Order;

/**
 * Description of OrderProcessor
 *
 * @author Kolade.Ige
 */
class OrderProcessor {

    private $orderSegments = [];

    public static function convert(EntityDefinitionBrowser $entityDefinition, $expression, callable $translator){
        $orderProcessor = new OrderProcessor($entityDefinition, $expression);
        $translation = '';
        foreach($orderProcessor->getOrderSegments() as $segment){
            $translation .= "{$translator($segment)},";
        }

        return trim($translation, ',');
    }

    private function __construct(EntityDefinitionBrowser $entityDefinition, $expression) {

        // In operator
        $matchs = [];
        
        preg_match_all('/([\w][\w\d]*)\s*((asc|desc)\s*[\,]?)?/i', $expression, $matchs, PREG_PATTERN_ORDER);
        foreach ($matchs as $mat) {
            $key = $mat[1];
            if(strlen($key) > 0){
                $this->orderSegments[$key] = new Order($entityDefinition, $mat[1], $mat[3]);
            }
        }
    }

    private function getOrderSegments(){
        return $this->orderSegments;
    }
}