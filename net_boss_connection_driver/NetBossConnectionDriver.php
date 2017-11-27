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
        $protocol = "https";
        $deviceId = 51;
        $objectName = "Bundle-Ether60.1896";
        $indicatorName = "enh_utilization";
        $startTime = 1510531200;
        $endTime = 1510665841;
                
        $soapIp = "nbportal.mainone.net";
        $timezoneOffset = 0;
        $client = $this->do_login ($soapIp,"api","P@99word123");       
            
        
        $data = $this->getDataFromSystem ( $client, $deviceId, $objectName, $indicatorName, $startTime, $endTime, $soapIp, $timezoneOffset,"SNMP",$protocol );
        return $data;
        // // var_dump (getAverageOfDataGraph($data));
        // // return ( $data );
        // $snmpDevices =  $this->getSNMPObjects();
        // return $snmpDevices;
        // return $this->getMapping($client, $snmpDevices);
    }           
    
    function do_login($soapIp, $username, $password) {
        
        ini_set ( "soap.wsdl_cache_enabled", 0 );

        $soapUrl = "https://middleware-dev.mainone.net/net-boss/wsdl/1";
        
        // ! Connect to the SOAP server.
        $client = new \SoapClient ( $soapUrl, array (
                // Let's debug in case we hit a snag.
                // 'trace' => 1 
        ));
        if (! $client) {
            throw new \Exception("ERROR: Could not connect to SOAP server at '{$soapUrl}'.\n");
        }
        
        try {
            // ! This authenticates a user for the duration of this script.
            $result = $client->authenticate($username, $password);
            if (! $result) {
                throw new \Exception("ERROR: Could not authenticate with the server.\n");
            } else {
                // ! This gets the user ID (UID) of the authenticated user.
                $result = $client->getAuthenticatedUid ();
            }
        } catch ( Exception $e ) {
            throw new \Exception( "ERROR: Exception:\n{$e->getMessage()}");
        }
        return $client;
    }
    
    
    function getDataFromSystem($client, $deviceId, $objectName, $indicatorName, $startTime, $endTime, $soapIp, $timezoneOffset,$pluginCode,$protocol) {
        // Make a new graph object.
        $graph = $client->factory_Graph ();
        
        // set graph options
        $graph->graphType = "graph_csv";
        $graph->bitsOrBytes = "bytes";
        
        // Set Graph times
        $graph->startTime = $startTime;
        $graph->endTime = $endTime;
        
        // perform aggregation
        // We want the data returned to us to be aggregated hourly,
        // then all we have to do is to group it into days and pick that which was highest for the day
        $graph->granularityEnabled = 0;
        $graph->granularityAggregation = "average";
        $graph->granularitySeconds = 3600;
        
        // Lets state who's data we want
        $graphDataSource = $client->factory_GraphDataSource ();
        $graphDataSource->deviceId = $deviceId;
        $graphDataSource->plugin = $pluginCode;
        $graphDataSource->objectName = $objectName;
        $graphDataSource->indicator = $indicatorName;
        
        // Load to the graph the filter we just created
        $graph->dataSources [] = $graphDataSource;
        
        // lets define the data timescale
        $timespan = $client->factory_Timespan ();
        $timespan->startTime = $startTime;
        $timespan->endTime = $endTime;
        $timespan->timezoneOffset = $timezoneOffset;
        
        $url = null;
        
        if ($protocol == "https"){
            $url = $client->report_makeSecureUrlFromGraph ( $graph, $timespan, false, $soapIp );
        }else{
            $url = $client->report_makeUrlFromGraph ( $graph, $timespan, false, $soapIp );
        }
        
        $context = [ 'ssl' => [ 'verify_peer' => false,"verify_peer_name"=>false, 'allow_self_signed'=> true ] ];
        $context = stream_context_create($context);
        $result = file_get_contents ( $url,false,$context );
        
        $explode = explode(PHP_EOL, $result);
        $data = [];
        if(count($explode) > 1){
            $now = new \DateTime();
            foreach($explode as $id => $ex){
                if($id > 0){
                    $row = \explode(',', $ex);
                    $d = new \stdClass();
                    $d->time = ($now->setTimestamp(intval($row[0])))->format('Y-m-d h:i a');
                    $d->utilization = $row[1];
                    $data[] = $d;
                }
            }
        }
        return $data;
    }
    
    public function getSNMPObjects(){      
        
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
            $result = $client->authenticate( "api", "P@99word123" );
            if (! $result) {
                return ("Could not authenticate with the server\n");
            } else {
                $result = $client->getAuthenticatedUid();
                $devices = $client->core_getObjectsByDeviceIdAndPlugin(51, "SNMP");
                return $devices;
            }
        } catch ( \Exception $e ) {
            print_r ( $e );
        }
    }
    
    public function getDevices(){      
        
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
            $result = $client->authenticate( "api", "P@99word123" );
            if (! $result) {
                return ("Could not authenticate with the server\n");
            } else {
                $result = $client->getAuthenticatedUid();
                $devices = $client->core_getDevices();
                return $devices;
            }
        } catch ( \Exception $e ) {
            print_r ( $e );
        }
    }

    // logInfo("Collecting IP addresses from the system");  
    // // We loop through these SNMP objects looking for interfaces then we will look for the IP address of each interface
    function getMapping($client, $allSnmpObjects){
        $ipAddressesOnDevice = [];
        $ipAddressToObject = [];
        foreach ( $allSnmpObjects as $snmpObject ) {
            // if ($snmpObject->isInterface != 1) {
            //     continue;
            // }
            // Since we are interested in MPLS-VPN configurations, anything that is not l2vlan should be skipped
            if ($snmpObject->subtype != 135) {
                continue;
            }
        
            // get the IP address of this interface
            $ipAddress = null;
            $localKeyVal = $client->factory_KeyValue ( [
                    "deviceId"
            ], [
                    $snmpObject->deviceId
            ] );
            try {
                $metaMappings = $client->metadata_getMapping ( null, $attributeId, "object", $snmpObject->id, $localKeyVal );
                foreach ( $metaMappings as $metaMapping ) {
                    // print_r($metaMapping);
                    $value = $metaMapping->value;
                    $ipAddress = $value->string;
                    if (! is_null ( $ipAddress )) {
                        break;
                    }
                }
            } catch ( Exception $e ) {
            }
            array_push ( $ipAddressesOnDevice, $ipAddress );
            $ipAddressToObject [$ipAddress] = $snmpObject;
        }

        return $ipAddressesOnDevice;
    }

    public function getStringer() {
        return MiddlewareFilter::SQL;
    }  
}
