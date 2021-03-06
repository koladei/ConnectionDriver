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
class DrupalEntityDriver extends MiddlewareConnectionDriver {

    private $endpoint;

    public function __construct(callable $driverLoader, callable $sourceLoader, $identifier = __CLASS__) {
        parent::__construct($driverLoader, $sourceLoader, $identifier);

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
                if ($entity == 'DAT') {
                    foreach ($vals as $child_entity => $child_items) {
                        parent::addExpansionToRecord($child_entity, $record, $fieldInfo, $child_items);
                    }
                } else if (property_exists($vals, $entity)) {
                    $val_items = $vals->{$entity};
                    parent::addExpansionToRecord($entity, $record, $fieldInfo, $val_items);

                } else if (property_exists($vals, 'DAT')) {
                    $val_items = $vals->{'DAT'};
                    parent::addExpansionToRecord($entity, $record, $fieldInfo, $val_items);
                } else {
                    $record->{$fieldInfo->getDisplayName()} = $fieldInfo->isMany() ? ['results' => []] : NULL;
                }
            } else {
                parent::addExpansionToRecord($entity, $record, $fieldInfo, $vals);
            }
        }


        return $records;
    }

    public function getItemById($entityBrowser, $id, $select, $expands = '', $otherOptions = []) {

        $return = parent::getItemById(... func_get_args());
        if (!is_null($return) && count($return > 0)) {
            return $return[0];
        }
        return NULL;
    }

    public function updateItemInternal($entityBrowser, &$connectionToken = NULL, $id, \stdClass $obj, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];

        $url = "http://molsptest:82/drp/CPortalService.svc/UpdateTable";
//        $url = "{$this->endpoint}http://molsptest:82/drp/CPortalService.svc/UpdateTable"; //{$entityBrowser->getInternalName()}?{$query_string}";
        $recordInfo = [];
        $recordInfo['Table'] = $entityBrowser->getInternalName();
        $recordInfo['Entity'] = $obj->DataArea;
        $recordInfo['RecId'] = $id;

        $object = $entityBrowser->reverseRenameFields($obj);
        $object->RecordInfo = $recordInfo;
        $objOut = json_encode($object);

        $tokenOption = [
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/plain'
            ]
            , CURLOPT_PROTOCOLS => CURLPROTO_HTTP
            , CURLOPT_SSL_VERIFYPEER => 0
            , CURLOPT_SSL_VERIFYHOST => 0
            , CURLOPT_POSTFIELDS => $objOut
            , CURLOPT_TIMEOUT => 15
            , CURLOPT_CONNECTTIMEOUT => 15
        ];

        $content = mware_blocking_http_request($url, ['options' => $tokenOption, 'block' => true]);
        $res = $content->getContent();

        if (!is_null($res)) {
            $res = (json_decode($content->getContent())); //TODO: add code that will hand this error appropriately.

            return true;
        } else {
            throw new \Exception("Something went wrong while updating record {$id} of entity {$entityBrowser->getDisplayName()} of " . __CLASS__ . ". Please try again");
        }
    }

    public function createItemInternal($entityBrowser, &$connectionToken = NULL, \stdClass $obj, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];

        $url = "http://molsptest:82/drp/CPortalService.svc/CreateRecord";
//        $url = "{$this->endpoint}http://molsptest:82/drp/CPortalService.svc/UpdateTable"; //{$entityBrowser->getInternalName()}?{$query_string}";
        $recordInfo = [];
        $recordInfo['Table'] = $entityBrowser->getInternalName();
        $recordInfo['Entity'] = $obj->DataArea;

        $object = $entityBrowser->reverseRenameFields($obj);
        $object->RecordInfo = $recordInfo;
        $objOut = json_encode($object);

        $tokenOption = [
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/plain'
            ]
            , CURLOPT_PROTOCOLS => CURLPROTO_HTTP
            , CURLOPT_SSL_VERIFYPEER => 0
            , CURLOPT_SSL_VERIFYHOST => 0
            , CURLOPT_POSTFIELDS => $objOut
            , CURLOPT_TIMEOUT => 15
            , CURLOPT_CONNECTTIMEOUT => 15
        ];


        $content = mware_blocking_http_request($url, ['options' => $tokenOption, 'block' => true]);
        $res = $content->getContent();

        if (!is_null($res)) {
            $res = json_decode($content->getContent());
            return $res;
        } else {
            throw new \Exception("Something went wrong while trying to create an item in entity {$entityBrowser->getDisplayName()} of " . __CLASS__ . ". Please try again");
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
    public function getItemsInternal($entityBrowser, &$connection_token = NULL, array $select, $filter, $expands = [], $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];

        $invoice_params = [
            '$filter' => $filter
            , '$select' => implode(',', $select)
            , '$top' => $otherOptions['$top']
        ];


        $query_string = drupal_http_build_query($invoice_params);
        $url = "{$this->endpoint}/{$entityBrowser->getInternalName()}?{$query_string}";

//        var_dump("{$entityBrowser->getInternalName()}::{$filter}");
        $tokenOption = array(
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
            , CURLOPT_PROTOCOLS => CURLPROTO_HTTP
            , CURLOPT_SSL_VERIFYPEER => 0
            , CURLOPT_SSL_VERIFYHOST => 0
            , CURLOPT_TIMEOUT => 15
            , CURLOPT_CONNECTTIMEOUT => 15
        );

        $feed = mware_blocking_http_request($url, ['options' => $tokenOption, 'block' => true]);
        $res = (json_decode($feed->getContent()));
//        var_dump("{$entityBrowser->getInternalName()} :: {$filter} :: ", $res);

        if (is_object($res) && property_exists($res, 'd')) {
            $z = new DynamicsAXEntityCollection();

            foreach ($res->d as $a => $b) {
                $z[$a] = $b;
            }

            return $z;
        } else {
            throw new \Exception("Failed to get items of entity {$entityBrowser->getDisplayName()} of " . __CLASS__ . " due to error: {$feed->getContent()}");
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
