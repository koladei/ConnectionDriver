<?php

namespace com\mainone\middleware;

use com\mainone\middleware\MiddlewareConnectionDriver;
use com\mainone\middleware\MiddlewareFilter;
use com\mainone\middleware\EntityDefinitionBrowser;

/**
 * Description of LDAPConnectionDriver
 *
 * @author Kolade.Ige
 */
class BMCRemedyConnectionDriver extends MiddlewareConnectionDriver {

    public function __construct(callable $driverLoader, $connection_settings) {
        parent::__construct($driverLoader);

        $this->connection_settings = $connection_settings;
    }

    /**
     * @override
     * Overrides the default implementation.
     *
     * @param \DateTime $value
     * @return void
     */
    protected function parseDateValue($value) {
        $type_1 = '/^([\d]{4})\-([\d]{2})\-([\d]{2})T([\d]{2})\:([\d]{2})\:([\d]{2})\+([\d\:]+)$/';
        $type_3 = '/^([\d]{4})\\-([\d]{2})\-([\d]{2})$/';

        if (preg_match($type_3, $value) == 1) {
            //$value = substr($value, 0, strpos($value, '.'));
            return \DateTime::createFromFormat('Y-m-d', $value);
        } else if (preg_match($type_1, $value) == 1) {
            // $value = substr($value, 0, strpos($value, '.'));
            return \DateTime::createFromFormat('Y-m-d\TH:i:sT', $value);
        }

        throw new \Exception("The date / datetime format is not known. {$value}");
    }

    
    public function executeFunctionInternal($functionName, array $objects = [], &$connectionToken = NULL, array $otherOptions = []) {
        
        throw new \Exception('Not yet implemented');
    }

    public function updateItemInternal($entityBrowser, &$connectionToken = NULL, $id, \stdClass $object, array $otherOptions = []) {
        
    }

    public function createItemInternal($entityBrowser, &$connectionToken = NULL, \stdClass $object, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];

        // Get a connection token
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
            $object = $entityBrowser->reverseRenameFields($object);
            
            //get the result
            $methods = $entityBrowser->getSoapMethods();
            
            if (!is_null($methods) && property_exists($methods, 'create')) {
                $uri = "{$connectionToken->URL}/{$entityBrowser->getInternalName()}";

                $client = new \SoapClient("$uri"); //TODO: uptimize performance by caching the WSDL.
                $authenticationInfo = new \stdClass();
                $authenticationInfo->userName = $connectionToken->Username;
                $authenticationInfo->password = $connectionToken->Password;
                $ns = "urn:{$entityBrowser->getInternalName()}";

                //Create Soap Header with the authentication parameters      
                $header = new \SOAPHeader($ns, 'AuthenticationInfo', $authenticationInfo);
                $client->__setSoapHeaders($header);
                $getListInputMap = new \stdClass();

                //execute the query
                try {
                    //get the result
                    $d = $client->{$methods->create}($object);
                    $ld = new \stdClass();
                    $ld->d = $d->Request_ID;
                    $ld->success = TRUE;
                    return $ld;            
                } catch (\SoapFault $sf) {
                    throw new \Exception("{$sf->getMessage()}");
                } 
            } else {
                throw new \Exception("The data dictionary is missing the soap method 'create' for entity {$entityBrowser->getDisplayName()}");
            }            
        } else {
            throw new \Exception('Unable to connect to Remedy');
        }            
    }

    public function deleteItemInternal($entityBrowser, &$connectionToken = NULL, $id, array $otherOptions = []) {
        
    }

    /**
     * Implements the get items operation.
     * @param type $entityBrowser the specific entity whose record is to be retrieved.
     * @param type $connectionToken an existing token object that can be used in this operation.
     * @param array $select An array of fields to be returned by this query
     * @param array $filter The filter expression to be passed to the remote server.
     * @param array $expands The fields to be expanded to get.
     * @param array $otherOptions An array of other additional options.
     * @return array An array of the retrieved values.
     * @throws \Exception when something goes wrong.
     */
    public function getItemsInternal($entityBrowser, &$connectionToken = NULL, array $select, $filter, $expands = [], $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];

        // Get a connection token
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {

            //get the result
            $methods = $entityBrowser->getSoapMethods();
            if (!is_null($methods) && property_exists($methods, 'query')) {
                $uri = "{$connectionToken->URL}/{$entityBrowser->getInternalName()}";                

                $client = new \SoapClient("$uri"); //TODO: uptimize performance by caching the WSDL.
                $authenticationInfo = new \stdClass();
                $authenticationInfo->userName = $connectionToken->Username;
                $authenticationInfo->password = $connectionToken->Password;
                $ns = "urn:{$entityBrowser->getInternalName()}";

                //Create Soap Header with the authentication parameters      
                $header = new \SOAPHeader($ns, 'AuthenticationInfo', $authenticationInfo);
                $client->__setSoapHeaders($header);
                $getListInputMap = new \stdClass();

                $getListInputMap->Qualification = "{$filter}";
                $getListInputMap->maxLimit = $otherOptions['$top'];
                $getListInputMap->startRecord = $otherOptions['$skip'];

                //execute the query
                try {
                    //get the result
                    $ld = $client->{$methods->query}($getListInputMap);
                    
                    return intval($otherOptions['$top']) > 1 ? $ld->getListValues : [$ld->getListValues];                
                } catch (\SoapFault $sf) {
                    if(strpos(strtolower($sf->faultstring), 'error (302):') == 0){
                        return [];
                    }

                    throw new \Exception("{$sf->getMessage()}");
                }
            } else {
                throw new \Exception("The data dictionary is missing the soap method 'query' for entity {$entityBrowser->getDisplayName()}");
            }
        }  else {
            throw new \Exception('Unable to connect to Remedy');
        }        
    }

    public function getStringer() {
        return MiddlewareFilter::BMC;
    }

    /**
     * Returns a connection token to aid communication with the datasource.
     * @return boolean
     */
    private function getConnectionToken() {
        try {
            return $this->connection_settings;
            
        } catch (Exception $x) {
            return FALSE;
        }
    }
}

class BMCRemedyEntity extends MiddlewareEntity {
    
}

class BMCRemedyComplexEntity extends MiddlewareComplexEntity {
    
}

class BMCRemedyEntityCollection extends MiddlewareEntityCollection {
    
}
