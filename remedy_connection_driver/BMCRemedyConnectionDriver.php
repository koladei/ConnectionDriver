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

    private $endpoint;

    public function __construct(callable $driverLoader, $endpoint = 'http://molsptest:82/drp/CPortalService.svc/QueryTable/[~]') {
        parent::__construct($driverLoader);

        $this->endpoint = $endpoint;
    }

    public function updateItemInternal($entityBrowser, &$connectionToken = NULL, $id, \stdClass $object, array $otherOptions = []) {
        
    }

    public function createItemInternal($entityBrowser, &$connectionToken = NULL, \stdClass $object, array $otherOptions = []) {
        
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
    public function getItemsInternal($entityBrowser, &$connection_token = NULL, array $select, $filter, $expands = [], $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];

        //get the result
        $methods = $entityBrowser->getSoapMethods();
        if (!is_null($methods) && property_exists($methods, 'query')) {

            $uri = "http://molbmcprod:8080/arsys/WSDL/public/molbmcprod/{$entityBrowser->getInternalName()}";

            $client = new \SoapClient("$uri");
            $authenticationInfo = new \stdClass();
            $authenticationInfo->userName = 'appadmin';
            $authenticationInfo->password = 'Remedy123';
            $ns = "urn:{$entityBrowser->getInternalName()}";

            //Create Soap Header with the authentication parameters      
            $header = new \SOAPHeader($ns, 'AuthenticationInfo', $authenticationInfo);
            $client->__setSoapHeaders($header);
            $getListInputMap = new \stdClass();

            var_dump("{$filter}");

            $getListInputMap->Qualification = "{$filter}";
            $getListInputMap->maxLimit = $otherOptions['$top'];
            $getListInputMap->startRecord = $otherOptions['$skip'];

            //execute the query
            try {
                //get the result
                $ld = $client->{$methods->query}($getListInputMap);
//                var_dump($ld);
                return intval($otherOptions['$top']) > 1 ? $ld->getListValues : [$ld->getListValues];
            } catch (\SoapFault $sf) {
                throw new \Exception("{$sf->getMessage()}");
            }
        }
        throw new \Exception("The data dictionary is missing the soap method 'query' for entity {$entityBrowser->getDisplayName()}");
    }

    public function getStringer() {
        return MiddlewareFilter::BMC;
    }

}

class BMCRemedyEntity extends MiddlewareEntity {
    
}

class BMCRemedyComplexEntity extends MiddlewareComplexEntity {
    
}

class BMCRemedyEntityCollection extends MiddlewareEntityCollection {
    
}
