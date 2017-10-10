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

        
        $config = [
            'soap_ip' => 'nbportal.mainone.net'
            , 'soap_username' => 'ayodeji.babatunde'
            , 'soap_password' => 'Welcome@123'
        ];

        /**
         * Global API configuration file.
         * Please add your credentials here if you haven't.
         */
        // $config = include_once 'api-config.php';
        // We don't want to cache the WSDL (for testing purposes).
        ini_set("soap.wsdl_cache_enabled", 0);
            
        //! This is the WSDL url.
        $soapUrl = "https://{$config['soap_ip']}/soap3/api.wsdl";
        $context = \stream_context_create([
            'ssl' => [
                // 'verify_peer' => FALSE,
                'verify_peer_name' => FALSE,
                'allow_self_signed' => TRUE
            ]
        ]);

        $wsdl = file_get_contents($soapUrl, [
            'stream_context' => $context
        ]);

        echo $wsdl;
            
        //! Connect to the SOAP server.
        $client = new \SoapClient(
            $soapUrl,
            [
                // Let's debug in case we hit a snag.
                'trace' => 1,
                'stream_context' => $context,
                // 'uri' => $soapUrl
            ]
        );
        if( !$client ) {
            throw new \Exception( "!!! Could not connect to SOAP server at '{$soapUrl}'.\n");                    
        }
            
        echo "== Information ==\n";
        echo "IP: {$config['soap_ip']}\n";
        echo "URL: {$soapUrl}\n";
            
        echo "== Authentication ==\n";
        try {
            echo 'Trying.... 1;';
            //! This authenticates a user for the duration of this script.
            $result = $client->authenticate( $config['soap_username'], $config['soap_password']);
            if( !$result ) {
                echo '!!! Could not authenticate with the server.\n';
                throw new \Exception( "!!! Could not authenticate with the server.\n");
            } else {
                //! This gets the user ID (UID) of the authenticated user.
                echo 'Trying....;';
                $result = $client->getAuthenticatedUid();
                echo "* Successfully authenticated with UID '{$result}'.\n";
            }
        } catch( Exception $e ) {
            echo 'Exception: '.$e->getMessage();
            throw $e;
        }
            
        echo "== Device list ==\n";
        try {
            $devices = $client->core_getDevices();
            if( !$devices ) {
                throw new \Exception("!!! Could not get any device information.\n");
            }
            foreach( $devices as $device ) {
                echo "Device:\n";
                echo "   ID: {$device->id}\n";
                echo "   Name: {$device->name}\n";
                echo "   Description: {$device->description}\n";
                echo "   IP: {$device->ip}\n";
                echo "   Element count: {$device->elementCount}\n";
            
                echo "   Plugins:\n";
                $plugins = $client->core_getEnabledPluginsByDeviceId( $device->id );
                if( !$plugins ) {
                    echo "      (none).\n";
                    continue;
                }
                foreach( $plugins as $pluginString ) {
                    echo "      Plugin: {$pluginString}\n";
            
                    $objects = $client->core_getObjectsByDeviceIdAndPlugin( $device->id, $pluginString );
                    if( !$objects ) {
                        echo "         (none)\n";
                        continue;
                    } else {
                        foreach( $objects as $object ) {
                            echo "         Object:\n";
                            echo "            Name: {$object->name}\n";
                            echo "            Description: {$object->description}\n";
            
                            echo "            Polls:\n";
                            $polls = $client->core_getPollsByDeviceIdAndObjectName( $device->id, $pluginString, $object->name );
                            if( !$polls ) {
                                echo "               (none)\n";
                            } else {
                                foreach( $polls as $poll ) {
                                    echo "               Indicator: {$poll->indicator}\n";
            
                                    // Make a new graph object.
                                    $graph = $client->factory_Graph();
            
                                    // It's a line graph.
                                    $graph->graphType = "graph_line";
            
                                    $graphDataSource = $client->factory_GraphDataSource();
                                    $graphDataSource->deviceId = $device->id;
                                    $graphDataSource->plugin = $pluginString;
                                    $graphDataSource->objectName = $object->name;
                                    $graphDataSource->indicator = $poll->indicator;
            
                                    $graph->dataSources[] = $graphDataSource;
            
                                    // Let's do from 4 hours ago until now.
                                    $timespan = $client->factory_Timespan();
                                    $timespan->startTime = time() - ( 60 * 60 * 4 );
                                    $timespan->endTime = time();
            
                                    
                                    $url = $client->report_makeUrlFromGraph( $graph, $timespan, false, $soapIp );
                                    $secureUrl = $client->report_makeSecureUrlFromGraph( $graph, $timespan, false, $soapIp );
                                    $relativeUrl = $client->report_makeRelativeUrlFromGraph( $graph, $timespan, false, $soapIp );
                                    echo "<pre>";
                                    echo "               URL: {$url}\n";
                                    echo "               Secure URL: {$secureUrl}\n";
                                    echo "               Relative URL: {$relativeUrl}\n";
                                    echo "\n\n";
                                    echo "</pre>";
                                }
                            }
                        }
                    }
                }
            }
        } catch( Exception $e ) {
            echo "Exception:\n";
            print_r( $e );
        }
    }

    public function getStringer() {
        return MiddlewareFilter::SQL;
    }  
}
