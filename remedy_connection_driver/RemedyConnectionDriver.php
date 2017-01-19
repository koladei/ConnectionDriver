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
class RemedyConnectionDriver extends MiddlewareConnectionDriver {

    private $endpoint;

    public function __construct(callable $driverLoader, $endpoint = 'http://molsptest:82/drp/CPortalService.svc/QueryTable/[~]') {
        parent::__construct($driverLoader);

        $this->endpoint = $endpoint;
    }

    public function fetchFieldValues($record, $selected_field) {
        $r = [];

        foreach ($record as &$member) {
            $r[] = "{$member->{$selected_field}}";
        }

        return $r;
    }

    public function renameRecordFields($record, $selected_fields) {

        foreach ($record as &$member) {
            $r = new DynamicsAXEntity();
            foreach ($selected_fields as $key => $field) {
                if (property_exists($member, $key)) {
                    $r->{$field->getDisplayName()} = $member->{$key};
                }
            }
            $member = $r;
        }

        return $record;
    }

    public function mergeRecordArray($data, $chunkResult, EntityFieldDefinition $localField, EntityFieldDefinition $remoteField = NULL) {
        $r = $data instanceof DynamicsAXComplexEntity ? $data : (new DynamicsAXComplexEntity());


        if (!is_null($chunkResult)) {
            foreach ($chunkResult as $entity => $val) {
                $y = NULL;
                $r->{$entity} = parent::mergeRecordArray($y, $val, $localField, $remoteField);
            }
        }

        return $r;
    }

    function addExpansionToRecord($entity, &$records, EntityFieldDefinition $fieldInfo, $vals) {
        foreach ($records as &$record) {
            if ($vals instanceof DynamicsAXComplexEntity) {
                if (property_exists($vals, $entity)) {
                    $val_items = $vals->{$entity};
                    parent::addExpansionToRecord($entity, $record, $fieldInfo, $val_items);
                }
            } else {
                parent::addExpansionToRecord($entity, $record, $fieldInfo, $vals);
            }
        }

        return $records;
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

        $uri = variable_get('cportal_core__remedy_uri');

        $client = new SoapClient("$uri");
        $authenticationInfo = new stdClass();
        $authenticationInfo->userName = 'Demo';
        $authenticationInfo->password = 'Remedy';
        $ns = 'urn:HPD_IncidentInterface_WS';

        //Create Soap Header with the authentication parameters      
        $header = new SOAPHeader($ns, 'AuthenticationInfo', $authenticationInfo);
        $client->__setSoapHeaders($header);
        $getListInputMap = new stdClass();

        //prepare the query
        $last_poll = (new \DateTime())->format("m/d/Y H:i:s");

        $getListInputMap->Qualification = '';//"('Last Modified Date'>=\"{$last_poll}\") AND (NOT 'Assigned Group' LIKE \"%IT Service Desk%\")";
        $getListInputMap->maxLimit = '200';
        $getListInputMap->startRecord = '1';

        //execute the query
        try {
            //get the result
            $ld = $client->HelpDesk_QueryList_Service($getListInputMap);
            var_dump($ld->getListValues);
        } catch (SoapFault $sf) {
            throw new \Exception("{$getListInputMap->Qualification}:" . $sf->getMessage());
        }
    }

    public function getStringer() {
        return MiddlewareFilter::XPP;
    }

}

class DynamicsAXEntity extends MiddlewareEntity {
    
}

class DynamicsAXComplexEntity extends MiddlewareComplexEntity {
    
}

class DynamicsAXEntityCollection extends MiddlewareEntityCollection {
    
}
