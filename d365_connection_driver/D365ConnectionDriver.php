<?php

namespace com\mainone\middleware;

use com\mainone\middleware\MiddlewareConnectionDriver;
use com\mainone\middleware\MiddlewareFilter;
use com\mainone\middleware\EntityDefinitionBrowser;

/**
 * Description of D365ConnectionDriver
 *
 * @author Kolade.Ige
 */
class D365ConnectionDriver extends MiddlewareConnectionDriver {

    private $connection_settings;

    public function __construct(callable $driverLoader, callable $sourceLoader, $identifier, $connection_settings) {
        parent::__construct($driverLoader, $sourceLoader, $identifier);

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
        $type_1 = '/^([\d]{4})\-([\d]{2})\-([\d]{2})T([\d]{2})\:([\d]{2})\:([\d]{2})(Z)$/';
        $type_3 = '/^([\d]{4})\\-([\d]{2})\-([\d]{2})$/';

        if (preg_match($type_3, $value) == 1) {
            return \DateTime::createFromFormat('Y-m-d', $value);
        } else if (preg_match($type_1, $value) == 1) {
            $value = substr($value, 0, strpos($value, 'Z'));
            return \DateTime::createFromFormat('Y-m-d\TH:i:s', $value);
        }

        throw new \Exception("The date / datetime format is not known. {$value}");
    }

    public function fetchFieldValues($record, $selected_field) {
        return parent::fetchFieldValues($record, $selected_field);
    }

    public function renameRecordFields($record, $selected_fields) {
        return parent::renameRecordFields($record, $selected_fields);
    }

    public function mergeRecordArray($data, $chunkResult, EntityFieldDefinition $localField, EntityFieldDefinition $remoteField = NULL) {        
        return parent::mergeRecordArray($data, $chunkResult, $localField, $remoteField);
    }

    public function executeFunctionInternal($functionName, array $objects = [], &$connectionToken = NULL, array $otherOptions = []) {
        
        // Get a connection token
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
            // $objs = ['inputs' => $objects];
            // $obj = json_encode($objs);
            
            // Prepare the POST request
            $options = array(
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $connectionToken->access_token,
                    'Content-Type: application/json',
                    'X-PrettyPrint: 1'
                ),
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                // CURLOPT_POSTFIELDS => $obj
            );

            if ($connectionToken->ConnectionParameters->UseProxyServer) {
                $options[CURLOPT_PROXY] = $connectionToken->ConnectionParameters->ProxyServer;
                $options[CURLOPT_PROXYPORT] = $connectionToken->ConnectionParameters->ProxyServerPort;
                //$tokenOption[CURLOPT_PROXYUSERPWD] = $d365_settings->ProxyServer;
            }

            switch($functionName){
                case 'VerifyUserSession':{
                    // Check if the username was provided
                    if(!isset($objects['user_id'])){
                        throw new \Exception('Kindly provide 3 the \'user_id\' parameter');
                    }    
                    $user_id = $objects['user_id'];
                                        
                    // Check if the password was provided
                    if(!isset($objects['org_id'])){
                        throw new \Exception('Kindly provide the \'org_id\' parameter');
                    }
                    $org_id = $objects['org_id'];

                    // Execute the POST request.
                    $options[CURLOPT_CUSTOMREQUEST] = 'GET';
                    $new_url = "{$connectionToken->instance_url}/id/{$org_id}/{$user_id}";
                    $feed = mware_blocking_http_request($new_url, ['options' => $options]);
                    
                    // Process the request
                    $res = json_decode($feed->getContent());

                    return $res;
                }
                case 'ExecuteFlow': {
                    // Execute the POST request.
                    $new_url = "{$connectionToken->instance_url}/id/data/v35.0/actions/custom/flow/{$functionName}";
                    $feed = mware_blocking_http_request($new_url, ['options' => $options]);
                    
                    // Process the request
                    $res = json_decode($feed->getContent());
                }
            }            

            if (is_array($res)) {
                throw new \Exception("{$res[0]->message}. errorCode: {$res[0]->errorCode}");
            } else if (is_null($res)) {
                throw new \Exception('Something went wrong. xCommunication with D365 failed.');
            } else {
                $d = new \stdClass();
                $d->d = $res->id;
                $d->success = TRUE;
                return $d;
            }
        } else {
            throw new \Exception('Unable to connect to Saled365orce');
        }
    }

    /**
     * Implements MiddlewareConnectionDriver.updateItemInternal.
     * @param type $entityBrowser
     * @param type $connectionToken
     * @param type $id
     * @param \stdClass $object
     * @param array $otherOptions
     * @return type
     * @throws \Exception
     */
    public function updateItemInternal($entityBrowser, &$connectionToken = NULL, $id, \stdClass $object, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];

        // Get a connection token
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
            $object = $entityBrowser->reverseRenameFields($object);    
            if(property_exists($object, 'Id')) {
                unset($object->Id);
            }       
            $obj = json_encode($object);

            // Prepare the POST request
            $options = [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $connectionToken->access_token,
                    'Content-Type: application/json',
                ],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_POSTFIELDS => $obj,
                CURLOPT_CUSTOMREQUEST => 'PATCH'
            ];

            if ($connectionToken->ConnectionParameters->UseProxyServer) {
                $options[CURLOPT_PROXY] = $connectionToken->ConnectionParameters->ProxyServer;
                $options[CURLOPT_PROXYPORT] = $connectionToken->ConnectionParameters->ProxyServerPort;
            //                $tokenOption[CURLOPT_PROXYUSERPWD] = $d365_settings->ProxyServer;
            }

            // Execute the POST request.
            $new_url = "{$connectionToken->instance_url}/services/data/v35.0/sobjects/{$entityBrowser->getInternalName()}/{$id}";
            $response = mware_blocking_http_request($new_url, ['options' => $options]);

            $content = $response->getContent();

            // Saled365orce does return anything on succesd365ul update, something is wrong.
            if (strlen($content) > 0) {
                // Process the request
                $res = json_decode($content);
                throw new \Exception("{$res[0]->message}. errorCode: {$res[0]->errorCode}");
            }

            // Get the resulting data afresh
            $selectFields = array_keys(get_object_vars($object));
            return $id;
        } else {
            throw new \Exception('Unable to connect to Saled365orce');
        }
    }

    /**
     * Implements MiddlewareConnectionDriver.createItemInternal
     * @param type $entityBrowser
     * @param type $connectionToken
     * @param \stdClass $object
     * @param array $otherOptions
     * @return type
     * @throws \Exception
     */
    public function createItemInternal($entityBrowser, &$connectionToken = NULL, \stdClass $object, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];

        // Get a connection token
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
            $object = $entityBrowser->reverseRenameFields($object);
            $obj = json_encode($object);
            
            // Prepare the POST request
            $options = [
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $connectionToken->access_token,
                    'Content-Type: application/json'
                ),
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_POSTFIELDS => $obj
            ];

            if ($connectionToken->ConnectionParameters->UseProxyServer) {
                $options[CURLOPT_PROXY] = $connectionToken->ConnectionParameters->ProxyServer;
                $options[CURLOPT_PROXYPORT] = $connectionToken->ConnectionParameters->ProxyServerPort;
                //$tokenOption[CURLOPT_PROXYUSERPWD] = $d365_settings->ProxyServer;
            }

            // Execute the POST request.
            $new_url = $connectionToken->instance_url . '/services/data/v35.0/sobjects/' . $entityBrowser->getInternalName();
            $feed = mware_blocking_http_request($new_url, ['options' => $options]);
            

            // Process the request
            $res = json_decode($feed->getContent());
            if (is_array($res)) {
                throw new \Exception("{$res[0]->message}. errorCode: {$res[0]->errorCode}");
            } else if (is_null($res)) {
                throw new \Exception('Something went wrong. YCommunication with D365 failed.');
            } else {
                $d = new \stdClass();
                $d->d = $res->id;
                $d->success = TRUE;
                return $d;
            }
        } else {
            throw new \Exception('Unable to connect to Saled365orce');
        }
    }

    /**
     * Implements the delete item operation.
     * @param type $entityBrowser
     * @param type $connectionToken
     * @param type $id
     * @param array $otherOptions
     */
    public function deleteItemInternal($entityBrowser, &$connectionToken = NULL, $id, array $otherOptions = []) {
        // $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];
        
        // Get a connection token
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
            $obj = new \stdClass();

            // Prepare the POST request
            $options = [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $connectionToken->access_token,
                    // 'Content-Type: application/json',
                ],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                // CURLOPT_POSTFIELDS => $obj,
                CURLOPT_CUSTOMREQUEST => 'DELETE'
            ];

            if ($connectionToken->ConnectionParameters->UseProxyServer) {
                $options[CURLOPT_PROXY] = $connectionToken->ConnectionParameters->ProxyServer;
                $options[CURLOPT_PROXYPORT] = $connectionToken->ConnectionParameters->ProxyServerPort;
            //                $tokenOption[CURLOPT_PROXYUSERPWD] = $d365_settings->ProxyServer;
            }

            // Execute the POST request.
            $new_url = "{$connectionToken->instance_url}/services/data/v35.0/sobjects/{$entityBrowser->getInternalName()}/{$id}";
            $response = mware_blocking_http_request($new_url, ['options' => $options]);

            $content = $response->getContent();

            // Saled365orce does return anything on succesd365ul update, something is wrong.
            if (strlen($content) > 0) {
                // Process the request
                $res = json_decode($content);
                throw new \Exception("{$res[0]->message}. errorCode: {$res[0]->errorCode}");
            }

            $resp = (object)['d' => 1];

            // Get the resulting data afresh
            // $selectFields = array_keys(get_object_vars($object));
            return $resp;
        } else {
            throw new \Exception('Unable to connect to Saled365orce');
        }
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

        // Deal with field prefix
        $filter = str_replace('_xENTITYNAME_', '', $filter);
        
        $retryCount = 0;

        // Get the requstToken
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {

            $top = $otherOptions['$top'];
            $skip = $otherOptions['$skip'];
            $pageNumber = $otherOptions['$pageNumber'];
            $pageSize = $otherOptions['$pageSize'];
            $orderBy = $otherOptions['$orderBy'];
            $all = isset($otherOptions['$all']) && ''.$otherOptions['$all'] = '1'?TRUE:FALSE;

            if($all){
                $pageSize = 100000000;
            }
            

            // Prepare the POST request
            $options = array(
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $connectionToken->access_token
                )
                , CURLOPT_PROTOCOLS => CURLPROTO_HTTPS
                , CURLOPT_SSL_VERIFYPEER => 0
                , CURLOPT_SSL_VERIFYHOST => 0
                , CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
                , CURLOPT_CONNECTTIMEOUT => 100000
                , CURLOPT_TIMEOUT => 100000
            );

            $result = [];
            $lastResult = [];
            $counter = 0;

            $invoice_params = [
                '$select' => implode(',', $select)
                , '$top' => $pageSize
                , '$skip' => ($pageSize * ($pageNumber - 1))
                , 'cross-company' => 'true'
            ];

            if(strlen($filter) > 0){
                $invoice_params['$filter'] = $filter;
            }

            // Execute the POST request.
            $query_string = http_build_query($invoice_params);
            $res = new \stdClass();              
            $res->nextRecordsUrl = "/data/{$entityBrowser->getInternalName()}?{$query_string}";

            do {   
                $feed = mware_blocking_http_request("{$connectionToken->resource}{$res->nextRecordsUrl}", ['options' => $options]);

                // Process the request
                $content =$feed->getContent();
                $res = json_decode($content);

                if (is_object($res) && property_exists($res, 'value')) {
                    $lastResult = $res->value;
                    $result = array_merge($result, $lastResult);  
                } else {
                    throw new \Exception("An empty response was received from D365 online. Please retry later. {$query_string}\n{$content}");
                }
            } while(false);// (\property_exists($res, 'nextRecordsUrl'));

            return $result;
        } else {
            throw new \Exception('Unable to connect to D365 online');
        }
    }

    /**
     * Returns the preffered query generator for the connection driver.
     * @return type
     */
    public function getStringer() {
        return MiddlewareFilter::ODATA;
    }

    /*
     ********************************************************************************************
     ********************************************************************************************
     ********************************************************************************************
     ********************************************************************************************
     ********************************************************************************************
     */
    
    /**
     * Returns a number that represents the maximum allowed OR statements to use when converting from IN to OR.
     *
     * This is necessary for systems that do not have an OOB implementation of the IN operator.
     *
     * @return void
     */
    public function getMaxInToOrConversionChunkSize()
    {
        return 20;
    }

    /**
     * Returns a connection token to aid communication with the datasource.
     * @return boolean
     */
    private function getConnectionToken($force = TRUE) {
        $t = self::retrieveValue('D365_access_token', NULL);
        $age = new \DateInterval('PT1H0M');
        $ten_minutes = new \DateInterval('PT0H10M');
        $now = new \DateTime();

        if(!$force && !is_null($t) && array_key_exists('last_update', $t)){
            try{
                $age = $now->diff((\DateTime::createFromFormat('Y-m-d\TH:i:s', $t['last_update'])));
                
                if(intval($age->format('i')) <= 15){
                    return $t['token'];
                }
            }catch(\Exception $e){}
        }

        try {
            $d365_settings = ($this->connection_settings);
            $uri = $d365_settings['authentication_url'];
            $query_array = $d365_settings;
            unset($query_array['authentication_url']);

            $query_string = (drupal_http_build_query($query_array));

            $tokenOption = [
                CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded')
                , CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
                , CURLOPT_PROTOCOLS => CURLPROTO_HTTPS
                , CURLOPT_SSL_VERIFYPEER => FALSE
                , CURLOPT_SSL_VERIFYHOST => 0
                , CURLOPT_FOLLOWLOCATION => TRUE
                , CURLOPT_HTTPPROXYTUNNEL => TRUE
                , CURLOPT_VERBOSE => TRUE
                , CURLOPT_POSTFIELDS => $query_string
            ];

            $feed = mware_blocking_http_request($uri, ['options' => $tokenOption, 'block' => true]);
            $token_response = json_decode($feed->getContent());

            if(!is_null($token_response) && property_exists($token_response, 'access_token')){
                $token_response->ConnectionParameters = $d365_settings;
                self::storeValue('D365_access_token', ['token' => $token_response,  'last_update' => $now->format('Y-m-d\TH:i:s')]);
                return $token_response;
            } else {
                return FALSE;
            }
            
        } catch (Exception $x) {
            return FALSE;
        }
    }
}
