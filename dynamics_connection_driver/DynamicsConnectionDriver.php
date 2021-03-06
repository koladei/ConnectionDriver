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
class DynamicsConnectionDriver extends MiddlewareConnectionDriver {

    private $endpoint;

    public function __construct(callable $driverLoader, callable $sourceLoader, $identifier = __CLASS__, $endpoint = '') {
        parent::__construct($driverLoader, $sourceLoader, $identifier);

        $this->endpoint = $endpoint;
    }

    /**
     * @overrides MiddlewareConnectionDriver.getMaxInToOrConversionChunkSize
     *
     * @return void
     */
    public function getMaxInToOrConversionChunkSize(){
        return 90;
    }
    
    /**
     * @overrides MiddlewareConnectionDriver.getItemById
     *
     * @param [type] $entityBrowser
     * @param [type] $id
     * @param [type] $select
     * @param string $expands
     * @param array $otherOptions
     * @return void
     */
    public function getItemById($entityBrowser, $id, $select, $expands = '', $otherOptions = []) {

        $return = parent::getItemById(... func_get_args());
        if (!is_null($return)) {
            return $return;
        }
        return NULL;
    }

    /**
     * @implements MiddlewareConnectionDriver.updateItemInternal
     *
     * @param [type] $entityBrowser
     * @param [type] $connectionToken
     * @param [type] $id
     * @param \stdClass $obj
     * @param array $otherOptions
     * @return void
     */
    public function updateItemInternal($entityBrowser, &$connectionToken = NULL, $id, \stdClass $obj, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];

        $url = "{$this->endpoint}/UpdateTable";

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

    /**
     * @implements Implements MiddlewareConnectionDriver.createItemInternal.
     *
     * @param [type] $entityBrowser
     * @param [type] $connectionToken
     * @param \stdClass $obj
     * @param array $otherOptions
     * @return void
     */
    public function createItemInternal($entityBrowser, &$connectionToken = NULL, \stdClass $obj, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];

        $url = "{$this->endpoint}/CreateRecord";

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

    /**
     * @implements MiddlewareConnectionDriver.deleteItemInternal
     *
     * @param [type] $entityBrowser
     * @param [type] $connectionToken
     * @param [type] $id
     * @param array $otherOptions
     * @return void
     */
    public function deleteItemInternal($entityBrowser, &$connectionToken = NULL, $id, array $otherOptions = []) {
        throw new \Exception('Not yet implemented');
    }

    /**
     * Implements the get items operation.
     * @param String|EntityDefinitionBrowser $entityBrowser the specific entity whose record is to be retrieved.
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

        $filter = str_replace('_xENTITYNAME_', 'axTbl_0.', $filter);
        $invoice_params = [
            '$filter' => $filter
            , '$select' => implode(',', $select)
            , '$top' => $otherOptions['$pageSize']
            , '$skip' => ($otherOptions['$pageSize'] * $otherOptions['$pageNumber']) + $otherOptions['$skip']
            , '$collate' => 0
        ];

        $query_string = http_build_query($invoice_params);
       
        $url = "{$this->endpoint}/QueryTable/[~]/{$entityBrowser->getInternalName()}?{$query_string}";

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

        if (is_object($res) && property_exists($res, 'd')) {
            return $res->d;
        } else {
            throw new \Exception("Failed to get items of entity {$entityBrowser->getDisplayName()} of " . __CLASS__ . " due to error: {$feed->getContent()}");
        }
    }

    /**
     * @implements MiddlewareConnectionDriver.getStringer()
     *
     * @return void
     */
    public function getStringer() {
        return MiddlewareFilter::XPP;
    }

}

class DynamicsEntity extends MiddlewareEntity {
    
}

class DynamicsComplexEntity extends MiddlewareComplexEntity {
    public function getByKey($key, $isMany = FALSE){

        if($isMany) {
            $vals = get_object_vars($this);
            $x = [];
            foreach($vals as $val){
                if(isset($val[$key])){
                    $x[] = $vals[$key];
                }
            }
            return $x;
        } else {
            $vals = get_object_vars($this);
            $vals = array_values($vals);
            $vals = array_merge(...$vals);
            return isset($vals[$key]) ? $vals[$key]: NULL;
        }

        return $isMany?[]:NULL;
    }
}

class DynamicsEntityCollection extends MiddlewareEntityCollection {
    
}
