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
    
    public function executeFunctionInternal($entityBrowser, $functionName, array $objects = [], &$connectionToken = NULL, array $otherOptions = []) {
        
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];
        $retryCount = 0;

        $response = new \stdClass();
        $procedurePath = variable_get('file_temporary_path');   
        
        //Connect to a Sharepoint site
        $host = 'mainyard.mainone.net';
        $username = 'mainonecable\spsetup_13';
        $password = 'P@55word321';
        $cookiefile = "{$procedurePath}/{$host}.txt";
        $headers = [
            'Accept: application/json; odata=verbose',
            'Content-Type: application/json'
        ];

        
        // $authorization = base64_encode("{$username}:{$password}");        
    

        $options = [
            CURLOPT_HTTPHEADER => [
                'Accept: application/json; odata=verbose',
                'Content-Type: application/json',
                // 'Authorization: Basic {$authorization}'
            ],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_USERAGENT => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
            CURLOPT_HTTPAUTH => CURLAUTH_NTLM,            
            CURLOPT_USERPWD => "$username:$password",
            CURLOPT_COOKIESESSION => TRUE,
            // CURLOPT_COOKIEJAR => "{$procedurePath}",
            // CURLOPT_COOKIE => TRUE,
            CURLOPT_COOKIEFILE => $cookiefile,
            CURLOPT_COOKIEJAR => $cookiefile,
        ];

        $a = function($curl, $header_line) use(&$options){
            $ax = explode(':', $header_line, 2);

            echo '> ' . $header_line;
            switch(strtolower($ax[0])){
                case 'sprequestguid':
                case 'request-id':{
                    foreach($options[CURLOPT_HTTPHEADER] as &$header){
                        // echo  ' '. substr(trim(strtolower($header)), 0, strlen($ax[0])).' == '.$ax[0].'\n';
                        if(substr(trim(strtolower($header)), 0, strlen($ax[0])) == $ax[0]){
                            $header = $header_line;
                        } else {
                            $options[CURLOPT_HTTPHEADER][] = $header_line;
                        }
                    }
                    
                    break;
                }
                case 'www-authenticate':{
                    if(substr(trim($header_line), 0, 24) == 'www-authenticate: bearer'){
                        $header = $header_line;
                    }
                }
            }
            // }
            return strlen($header_line);
        };
        
        $options[CURLOPT_HEADERFUNCTION] = $a;

        $contextOpt = $options;
        $contextOpt[CURLOPT_POSTFIELDS] = '';
        $contextOpt[CURLOPT_POST] = TRUE;
        $contextUrl = "https://{$host}/_api/contextinfo";
        $url = str_replace(' ', '%20', "{$contextUrl}");

        $context = mware_blocking_http_request($url, ['options' => $contextOpt]);
        
        $digest = json_decode($context->getContent())->d;

        // return "{$procedurePath}/{$host}.txt";
        // return $cookiefile;


        switch($functionName){
            case 'createfolder':{
                throw new \Exception("Function call '{$functionName} is not supported.");
                break;
            }
            case 'checkfolderexists':{
                throw new \Exception("Function call '{$functionName} is not supported.");
                break;
            }            
            case 'uploadfile':{
                $fileaddstring = "add(overwrite='true', url='TESTPHPFIEL')";
                $url = str_replace(' ', '%20', "https://{$host}/docs/_api/web/lists/getbytitle('{$entityBrowser->getInternalName()}')/{$fileaddstring}");    
                $fileInformation = new \stdClass();                
                $options[CURLOPT_POST] = TRUE; 
                $options[CURLOPT_POSTFIELDS] = '';//$fileInformation;
                $options[CURLOPT_REFERER] = "{$contextUrl}";
                // unset($options[CURLOPT_HTTPAUTH]);
                // unset($options[CURLOPT_USERPWD]);

                // $options[CURLOPT_HTTPHEADER] = $headers;
                var_dump('---------------------',$options[CURLOPT_HTTPHEADER]);

                $options[CURLOPT_HTTPHEADER][] = "X-RequestDigest: {$digest->GetContextWebInformation->FormDigestValue}";
                $options[CURLOPT_HTTPHEADER][] = "Authorization: Bearer {$authorization}";
                $options[CURLOPT_HTTPHEADER][] = "Content-Length: 0";
                
                $feed = mware_blocking_http_request($url, ['options' => $options, 'curl_handle' => $context->getHandle()]);
                // $feed = mware_blocking_http_request($url, ['options' => $options, 'curl_handle' => $context->getHandle()]);
                $response = json_decode($feed->getContent());
                return $response;
            }            
            case 'checkfileexists':{
                throw new \Exception("Function call '{$functionName} is not supported.");
                break;
            }
            default: {
                throw new \Exception("Function call '{$functionName}' is not supported.");
            }
        }

        return $response;
    }
    
    public function executeTargetedFunctionInternal($entityBrowser, $id, $functionName, array $data = [], &$connectionToken = NULL, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];
        
        switch($functionName){
            case 'fileupload': {        
                //Connect to a Sharepoint site
                $host = 'mainyard.mainone.net';
                $url = str_replace(' ', '%20', "https://{$host}/docs/_api/web/lists/getbytitle('Access Requests')/fields?");
                $username = 'mainonecable\spsetup_13';
                $password = 'P@55word321';
                $options = array(
                    CURLOPT_HTTPHEADER => array(
                        'Accept: application/json; odata=verbose',
                        'Content-Type: application/json'
                    ),
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_SSL_VERIFYPEER => 0,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                    CURLOPT_RETURNTRANSFER => 1,
                    // CURLOPT_POSTFIELDS => $obj,
                    CURLOPT_HTTPAUTH => CURLAUTH_NTLM,            
                    CURLOPT_USERPWD => "$username:$password"
                );

                $feed = mware_blocking_http_request($url, ['options' => $options]);

                var_dump( $feed->getContent());
                return [];
            } 
            default:{
                throw new \Exception("The function '{$functionName}' is not recognized.");
            }
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

        // Generate the SOQL query to send in the POST request
        $query_url =  'SELECT ' . implode(',', $select)
            . " FROM {$entityBrowser->getInternalName()}"
            . (strlen($filter) > 0 ? "  WHERE {$filter} " : '')
            . $limit;

        //Connect to a Sharepoint site
        $host = 'mainyard.mainone.net';
        $url = str_replace(' ', '%20', "https://{$host}/docs/_api/web/lists/getbytitle('Access Requests')/fields?");
        $username = 'mainonecable\spsetup_13';
        $password = 'P@55word321';
        $options = array(
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json; odata=verbose',
                'Content-Type: application/json'
            ),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPAUTH => CURLAUTH_NTLM,            
            CURLOPT_USERPWD => "$username:$password"
        );

        $feed = mware_blocking_http_request($url, ['options' => $options]);

        var_dump( $feed->getContent());
        return [];
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

    private function getSecurityToken(){
        $username = 'mainonecable\kolade.ige';
        $password = 'FGsltw3:20';
        $envelope =<<<"ENVELOPE"
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope" xmlns:a="http://www.w3.org/2005/08/addressing" xmlns:u="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
    <s:Header>
      <a:Action s:mustUnderstand="1">http://schemas.xmlsoap.org/ws/2005/02/trust/RST/Issue</a:Action>
      <a:ReplyTo>
        <a:Address>http://www.w3.org/2005/08/addressing/anonymous</a:Address>
      </a:ReplyTo>
      <a:To s:mustUnderstand="1">https://login.microsoftonline.com/extSTS.srf</a:To>
      <o:Security s:mustUnderstand="1"
         xmlns:o="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
        <o:UsernameToken>
          <o:Username>{$username}</o:Username>
          <o:Password>{$password}</o:Password>
        </o:UsernameToken>
      </o:Security>
    </s:Header>
    <s:Body>
      <t:RequestSecurityToken xmlns:t="http://schemas.xmlsoap.org/ws/2005/02/trust">
        <wsp:AppliesTo xmlns:wsp="http://schemas.xmlsoap.org/ws/2004/09/policy">
          <a:EndpointReference>
            <a:Address>[endpoint]</a:Address>
          </a:EndpointReference>
        </wsp:AppliesTo>
        <t:KeyType>http://schemas.xmlsoap.org/ws/2005/05/identity/NoProofKey</t:KeyType>
        <t:RequestType>http://schemas.xmlsoap.org/ws/2005/02/trust/Issue</t:RequestType>
        <t:TokenType>urn:oasis:names:tc:SAML:1.0:assertion</t:TokenType>
      </t:RequestSecurityToken>
    </s:Body>
  </s:Envelope>  
ENVELOPE;
    }
}
