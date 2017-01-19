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
class SalesforceConnectionDriver extends MiddlewareConnectionDriver {

    private $connection_settings;

    public function __construct(callable $driverLoader, $connection_settings) {
        parent::__construct($driverLoader);

        $this->connection_settings = $connection_settings;
    }

    public function fetchFieldValues($record, $selected_field){
        return parent::fetchFieldValues($record, $selected_field);
    }

    public function renameRecordFields($record, $selected_fields){
        return parent::renameRecordFields($record, $selected_fields);
    }

    public function mergeRecordArray($data, $chunkResult, EntityFieldDefinition $localField, EntityFieldDefinition $remoteField = NULL){
        return parent::mergeRecordArray($data, $chunkResult, $localField, $remoteField);
    }

    function addExpansionToRecord($entity, &$record, EntityFieldDefinition $fieldInfo, $vals){
        return parent::addExpansionToRecord($entity, $record, $fieldInfo, $vals);
    }

    public function updateItemInternal($entityBrowser, &$connectionToken = NULL, $id, \stdClass $object, array $otherOptions = []){
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];
        
        // Get a connection token
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
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

            // Execute the POST request.
            $new_url = "{$connectionToken->instance_url}/services/data/v35.0/sobjects/{$entityBrowser->getInternalName()}/{$id}";
            $response = mware_blocking_http_request($new_url, ['options' => $options]);

            $content = $response->getContent();
            
            // Salesforce does return anything on successful update, something is wrong.
            if (strlen( $content) > 0 ) {
                // Process the request
                $res = json_decode($content);
                throw new \Exception("{$res[0]->message}. errorCode: {$res[0]->errorCode}");
            }

            // Get the resulting data afresh
            $selectFields = array_keys(get_object_vars($object));
            return $this->getItemById($entityBrowser, $id, $selectFields);
        } else {
            throw new \Exception('Unable to connect to Salesforce');
        }
    }

    public function createItemInternal($entityBrowser, &$connectionToken = NULL, \stdClass $object, array $otherOptions = []){
         $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];
        
        // Get a connection token
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
            $obj = json_encode($object);

            // Prepare the POST request
            $options = array(
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $connectionToken->access_token,
                    'Content-Type: application/json'
                ),
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_POSTFIELDS => $obj
            );

            // Execute the POST request.
            $new_url = $connectionToken->instance_url . '/services/data/v35.0/sobjects/' . $entityBrowser->getInternalName();
            $feed = mware_blocking_http_request($new_url, ['options' => $options]);

            // Process the request
            $res = json_decode($feed->getContent());
            if (is_array($res)) {
                throw new \Exception("{$res[0]->message}. errorCode: {$res[0]->errorCode}");
            } else if (is_null($res)) {
                throw new \Exception('Something went wrong. Communication with Salesforce failed.');
            } else {                
                // Get the resulting data afresh
                $selectFields = array_keys(get_object_vars($object));
                return $this->getItemById($entityBrowser, $res->id, $selectFields);
            }
        } else {
            throw new \Exception('Unable to connect to Salesforce');
        }
    }  

    /**
     * Implements the delete item operation.
     * @param type $entityBrowser
     * @param type $connectionToken
     * @param type $id
     * @param array $otherOptions
     */
    public function deleteItemInternal($entityBrowser, &$connectionToken = NULL, $id, array $otherOptions = []){
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];
         
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
    public function getItemsInternal($entityBrowser, &$connectionToken = NULL,  array $select, $filter, $expands = [], $otherOptions = []) {        
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];
               
        // Get the requstToken
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {

            // Prepare the limit
            $limit = ' LIMIT 200';
            if(isset($otherOptions['$top'])){
                $limit = " LIMIT {$otherOptions['$top']}";
            }

            // Prepare the POST request
            $options = array(
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $connectionToken->access_token
                ),
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
            );

            // Generate the SOQL query to send in the POST request
            $query_url = drupal_http_build_query(['q' => 'SELECT ' . implode(',', $select)
                . " FROM {$entityBrowser->getInternalName()}"
                . (strlen($filter)>0?"  WHERE {$filter} ":'')
                . $limit]);
                
            // Execute the POST request.
            $new_url = $connectionToken->instance_url . '/services/data/v35.0/query?' . $query_url;
            $feed = mware_blocking_http_request($new_url, ['options' => $options]);

            // Process the request
            $res = json_decode($feed->getContent());

            
            if(is_object($res) && property_exists($res, 'records')){
                return $res->records;
            } else {
                throw new \Exception("{$feed->getContent()}");
            }

            return $res;
        } else{
            throw new \Exception('Unable to connect to Salesforce');
        }
    }

    public function getStringer(){
        return MiddlewareFilter::SOQL;
    }

    private function getConnectionToken(){
        try {
            $sf_settings = $this->connection_settings;
            $uri = $sf_settings->URL;
            $query_array = [
                'grant_type' => $sf_settings->GrantType,
                'client_id' => $sf_settings->ClientID,
                'client_secret' => $sf_settings->ClientSecret,
                'username' => $sf_settings->Username,
                'password' => $sf_settings->Password
            ];

            $query_string = (drupal_http_build_query($query_array));

            $tokenOption = array(
                CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_POSTFIELDS => $query_string
            );

            $feed = mware_blocking_http_request($uri, ['options' => $tokenOption, 'block' => true]);
            $token_response = json_decode($feed->getContent());
            
            return $token_response;
        } catch (Exception $x) {
            return FALSE;
        }
    }
}
