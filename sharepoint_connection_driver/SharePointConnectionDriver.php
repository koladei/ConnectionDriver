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
class SharePointConnectionDriver extends MiddlewareConnectionDriver {

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
        $type_1 = '/^([\d]{4})\-([\d]{2})\-([\d]{2})T([\d]{2})\:([\d]{2})\:([\d]{2})\.([\d\+]+)$/';
        $type_3 = '/^([\d]{4})\\-([\d]{2})\-([\d]{2})$/';

        if (preg_match($type_3, $value) == 1) {
            return \DateTime::createFromFormat('Y-m-d', $value);
        } else if (preg_match($type_1, $value) == 1) {
            $value = substr($value, 0, strpos($value, '.'));
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
            $objs = ['inputs' => $objects];
            // $obj = json_encode($objs);
            
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
                throw new \Exception('Something went wrong. Communication with SharePoint failed.');
            } else {
                $d = new \stdClass();
                $d->d = $res->id;
                $d->success = TRUE;
                return $d;
            }
        } else {
            throw new \Exception('Unable to connect to SharePoint');
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
            //                $tokenOption[CURLOPT_PROXYUSERPWD] = $sf_settings->ProxyServer;
            }

            // Execute the POST request.
            $new_url = "{$connectionToken->instance_url}/services/data/v35.0/sobjects/{$entityBrowser->getInternalName()}/{$id}";
            $response = mware_blocking_http_request($new_url, ['options' => $options]);

            $content = $response->getContent();

            // SharePoint does return anything on successful update, something is wrong.
            if (strlen($content) > 0) {
                // Process the request
                $res = json_decode($content);
                throw new \Exception("{$res[0]->message}. errorCode: {$res[0]->errorCode}");
            }

            // Get the resulting data afresh
            $selectFields = array_keys(get_object_vars($object));
            return $this->getItemById($entityBrowser, $id, $selectFields);
        } else {
            throw new \Exception('Unable to connect to SharePoint');
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
                throw new \Exception('Something went wrong. Communication with SharePoint failed.');
            } else {
                $d = new \stdClass();
                $d->d = $res->id;
                $d->success = TRUE;
                return $d;
            }
        } else {
            throw new \Exception('Unable to connect to SharePoint');
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
        throw new \Exception('Not yet implemented.');
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

            // $filter = trim($filter, '[]');
            // $filter = str_replace('[[', '(', str_replace(']]', ')', $filter));

            // Generate the SOQL query to send in the POST request
            $query_url =  'SELECT ' . implode(',', $select)
                . " FROM {$entityBrowser->getInternalName()}"
                . (strlen($filter) > 0 ? "  WHERE {$filter} " : '')
                . $limit;

            // Execute the POST request.
            $new_url = $connectionToken->instance_url . '/services/data/v35.0/query?' . drupal_http_build_query(['q' => $query_url]);
            
            $feed = mware_blocking_http_request($new_url, ['options' => $options]);

            // Process the request
            $content =$feed->getContent();

            $res = json_decode($content);

            if (is_object($res) && property_exists($res, 'records')) {
                return $res->records;
            } else {
                throw new \Exception("An empty response was received from SharePoint. Please retry later. {$query_url}\n{$content}");
            }
        } else {
            throw new \Exception('Unable to connect to SharePoint');
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
            return $sf_settings;
        } catch (Exception $x) {
            return FALSE;
        }
    }
}
