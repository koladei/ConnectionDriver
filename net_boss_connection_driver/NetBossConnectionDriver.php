<?php

namespace com\mainone\middleware;

use com\mainone\middleware\MiddlewareConnectionDriver;
use com\mainone\middleware\MiddlewareFilter;
use com\mainone\middleware\EntityDefinitionBrowser;
use \PDO;

/**
 * Description of NetBossConnectionDriver.php
 *
 * @author Kolade.Ige
 */
class NetBossConnectionDriver extends MiddlewareConnectionDriver {

    protected $maxRetries = -1;

    /**
     * Instantiates and returns an instance of a NetBossConnectionDriver.php.
     *
     * @param callable $driverLoader A callable reference that can be used to retrieve data that can be found in other connnection driver instances.
     * @param callable $sourceLoader A callable reference that can be used to load data from various named connections within the current driver.
     */
    public function __construct(callable $driverLoader, callable $sourceLoader, $identifier = __CLASS__) {
        parent::__construct($driverLoader, $sourceLoader, $identifier);
    }

    public function executeFunctionInternal($functionName, array $objects = [], &$connectionToken = NULL, array $otherOptions = []) {
        
        // Get a connection token
        return $this->getDevices();
    }
    
    private function getDevices(){      
        
        // ini_set ( "soap.wsdl_cache_enabled", 1 );
        ini_set('soap.wsdl_cache_enabled',0);
        ini_set('soap.wsdl_cache_ttl',0);
        
        $soapUrl = "https://middleware-dev.mainone.net/net-boss/wsdl/1";
        
        $context = stream_context_create ([ 
                'ssl' => [ 
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true 
                ] 
        ]);
        
        $client = new \SoapClient ( $soapUrl, array (
                //'trace' => 1,
                'sream_context' => $context 
        ));
        
        if (!$client) {
            echo ("Could not connect to SOAP server\n");
        }
        
        try {
            $result = $client->authenticate( "appdev", "nbportal" );
            if (! $result) {
                return ("Could not authenticate with the server\n");
            } else {
                $result = $client->getAuthenticatedUid();
                $devices = $client->report_getReportAttachmentsByReportId(43);
                return $devices;
            }
        } catch ( \Exception $e ) {
            print_r ( $e );
        }
    }
    
    // private function getDevices(){      
    //     $url = 'https://itsupport.mainone.net/netboss/echo.php';
    //     $data = ['method' => 'authenticate', 'parameters' => serialize(["appdev", "nbportal"])];
        
    //     // use key 'http' even if you send the request to https://...
    //     $options = array(
    //         'http' => array(
    //             'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
    //             'method'  => 'POST',
    //             'content' => http_build_query($data)
    //         )
    //     );

    //     $context  = stream_context_create($options);
    //     $result = file_get_contents($url, false, $context);
    //     if ($result === FALSE) { /* Handle error */ }
        
    //     return $result;
    // }

    public function getStringer() {
        return MiddlewareFilter::SQL;
    }  
}
