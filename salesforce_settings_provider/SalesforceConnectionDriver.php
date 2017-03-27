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
            $objs = ['inputs' => $objects];
            // $obj = json_encode($objs);
            var_dump($obj);
            
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

            if ($connectionToken->ConnectionParameters->UseProxyServer) {
                $options[CURLOPT_PROXY] = $connectionToken->ConnectionParameters->ProxyServer;
                $options[CURLOPT_PROXYPORT] = $connectionToken->ConnectionParameters->ProxyServerPort;
                //$tokenOption[CURLOPT_PROXYUSERPWD] = $sf_settings->ProxyServer;
            }

            // Execute the POST request.
            $new_url = "{$connectionToken->instance_url}/services/data/v35.0/actions/custom/flow/{$functionName}";
            $feed = mware_blocking_http_request($new_url, ['options' => $options]);
            

            // Process the request
            $res = json_decode($feed->getContent());
            if (is_array($res)) {
                throw new \Exception("{$res[0]->message}. errorCode: {$res[0]->errorCode}");
            } else if (is_null($res)) {
                throw new \Exception('Something went wrong. Communication with Salesforce failed.');
            } else {
                $d = new \stdClass();
                $d->d = $res->id;
                $d->success = TRUE;
                return $d;
            }
        } else {
            throw new \Exception('Unable to connect to Salesforce');
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
            //                $tokenOption[CURLOPT_PROXYUSERPWD] = $sf_settings->ProxyServer;
            }

            // Execute the POST request.
            $new_url = "{$connectionToken->instance_url}/services/data/v35.0/sobjects/{$entityBrowser->getInternalName()}/{$id}";
            $response = mware_blocking_http_request($new_url, ['options' => $options]);

            $content = $response->getContent();

            // Salesforce does return anything on successful update, something is wrong.
            if (strlen($content) > 0) {
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

            if ($connectionToken->ConnectionParameters->UseProxyServer) {
                $options[CURLOPT_PROXY] = $connectionToken->ConnectionParameters->ProxyServer;
                $options[CURLOPT_PROXYPORT] = $connectionToken->ConnectionParameters->ProxyServerPort;
                //$tokenOption[CURLOPT_PROXYUSERPWD] = $sf_settings->ProxyServer;
            }

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
                $d = new \stdClass();
                $d->d = $res->id;
                $d->success = TRUE;
                return $d;
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
    public function deleteItemInternal($entityBrowser, &$connectionToken = NULL, $id, array $otherOptions = []) {
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
    public function getItemsInternal($entityBrowser, &$connectionToken = NULL, array $select, $filter, $expands = [], $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];

        // Get the requstToken
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {

            // Prepare the limit
            $limit = ' LIMIT 200';
            if (isset($otherOptions['$top'])) {
                $limit = " LIMIT {$otherOptions['$top']}";
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
            );

            if ($connectionToken->ConnectionParameters->UseProxyServer) {
                $options[CURLOPT_PROXY] = $connectionToken->ConnectionParameters->ProxyServer;
                $options[CURLOPT_PROXYPORT] = $connectionToken->ConnectionParameters->ProxyServerPort;
                // $tokenOption[CURLOPT_PROXYUSERPWD] = $sf_settings->ProxyServer;
            }

            // Generate the SOQL query to send in the POST request
            $query_url =  'SELECT ' . implode(',', $select)
                . " FROM {$entityBrowser->getInternalName()}"
                . (strlen($filter) > 0 ? "  WHERE {$filter} " : '')
                . $limit;

            //    var_dump("{$entityBrowser->getInternalName()} :=:  WHERE {$filter} ");
            // Execute the POST request.
            $new_url = $connectionToken->instance_url . '/services/data/v35.0/query?' . drupal_http_build_query(['q' => $query_url]);
            $feed = mware_blocking_http_request($new_url, ['options' => $options]);

            // Process the request
            $content =$feed->getContent();

            $res = json_decode($content);

            if (is_object($res) && property_exists($res, 'records')) {
                return $res->records;
            } else {
                throw new \Exception("An empty response was received from Salesforce. Please retry later. {$query_url}\n{$content}");
            }
        } else {
            throw new \Exception('Unable to connect to Salesforce');
        }
    }

    /**
     * Returns the preffered query generator for the connection driver.
     * @return type
     */
    public function getStringer() {
        return MiddlewareFilter::SOQL;
    }

    /**
     * Returns a connection token to aid communication with the datasource.
     * @return boolean
     */
    private function getConnectionToken() {
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

            if ($sf_settings->UseProxyServer) {
                $tokenOption[CURLOPT_PROXY] = $sf_settings->ProxyServer;
                $tokenOption[CURLOPT_PROXYPORT] = $sf_settings->ProxyServerPort;
                //$tokenOption[CURLOPT_PROXYUSERPWD] = $sf_settings->ProxyServer;
            }


            $feed = mware_blocking_http_request($uri, ['options' => $tokenOption, 'block' => true]);
            $token_response = json_decode($feed->getContent());

            // var_dump($token_response);

            if(!is_null($token_response) && property_exists($token_response, 'access_token')){
                $token_response->ConnectionParameters = $sf_settings;
                return $token_response;
            } else {
                return FALSE;
            }
        } catch (Exception $x) {
            return FALSE;
        }
    }

}
