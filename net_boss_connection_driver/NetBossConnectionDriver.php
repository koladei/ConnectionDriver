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

    /**
     * @override
     * Overrides the default implementation.
     *
     * @param \DateTime $value
     * @return void
     */
    protected function parseDateValue($value) {
        $type_1 = '/^([\d]{4})\-([\d]{2})\-([\d]{2})[\s]{1,}([\d]{2})\:([\d]{2})\:([\d]{2})\.([\d]+)$/';
        $type_3 = '/^([\d]{4})\\-([\d]{2})\-([\d]{2})$/';
        //2016-04-13 17:30:54.9300000

        if (preg_match($type_3, $value) == 1) {
            //$value = substr($value, 0, strpos($value, '.'));
            return \DateTime::createFromFormat('Y-m-d', $value);
        } else if (preg_match($type_1, $value) == 1) {
            $value = substr($value, 0, strpos($value, '.'));
            return \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        }

        throw new \Exception("The date / datetime format is not known. {$value}");
    }

    public function executeFunctionInternal($entityBrowser, $functionName, array $objects = [], &$connectionToken = NULL, array $otherOptions = []) {
        
        // Get a connection token
        return '$this->getDevices()';
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
        $source = $entityBrowser->getDataSourceName();
        

        if($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken($source))){
            // Set defaults
            $top = $otherOptions['$top'];
            $skip = $otherOptions['$skip'];
            $pageNumber = $otherOptions['$pageNumber'];
            $pageSize = $otherOptions['$pageSize'];
            $orderBy = $otherOptions['$orderBy'];
            $all = isset($otherOptions['$all']) && ''.$otherOptions['$all'] = '1'?TRUE:FALSE;

            // Remove distinct fields from select
            $distinct = $otherOptions['$distinct'];
            $sel = [];
            foreach($select as $dis){
                if(!in_array($dis, $distinct)){
                    $sel[] = $dis;
                }
            }
            $select2 = implode(',', $select);

            // $distinct = implode(',', $otherOptions['$distinct']);
            $occurence = '';
            if (count($distinct) < 1) {
                $distinct = 'ID';
            } else {
                $distinct = implode(',', $otherOptions['$distinct']);
                $distinct = "{$distinct}";
                $occurence = ' WHERE OCCURENCE = 1 ';
            }

            // Determin the record to start from based on the $pageSize and $pageNumber;
            $start = ($pageSize * ($pageNumber - 1)) + 1 + $skip;
            $end = $start + $pageSize;

            // Generate the SQL query to send in the POST request   
            $where = (strlen($filter) > 0 ? "  WHERE {$filter} " : '');         
            $sel = implode(',', $sel);
            
            $query_url = "
                WITH DEDUPE AS (
                    SELECT {$select2}, ROW_NUMBER() OVER (PARTITION BY {$distinct} ORDER BY {$distinct}) AS OCCURENCE
                    FROM {$entityBrowser->getInternalName()}
                    {$where}
                )

                SELECT  {$select2}
                FROM    ( SELECT  ROW_NUMBER() OVER ( ORDER BY {$orderBy} ) AS RowNum, {$select2}
                        FROM DEDUPE {$occurence}
                        ) AS RowConstrainedResult
                WHERE   RowNum >= {$start}
                        AND RowNum < {$end}
                ORDER BY RowNum
            ";

            if($all){
                $query_url = "SELECT {$select2} FROM {$entityBrowser->getInternalName()} {$where} ORDER BY {$orderBy}";
            }

            $query_url = str_replace("\\'", "''", $query_url);
            try {
                $pdo = new \PDO($connectionToken->DSN, $connectionToken->Username, $connectionToken->Password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $pdo->query($query_url);
                $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                return array_values($rs);
            } catch (\Exception $e) {

                throw new \Exception('Connection failed: ' . $e->getMessage());
            }
        } else {
            throw new \Exception('The connection datasource settings could not be retrieved, contact the administrator.');
        }   
    }

    public function getStringer() {
        return MiddlewareFilter::SQL;
    }

    
    // private function getDevices(){

        
    //     $config = [
    //         'soap_ip' => 'nbportal.mainone.net'
    //         , 'soap_username' => 'ayodeji.babatunde'
    //         , 'soap_password' => ''
    //     ];

    //     /**
    //      * Global API configuration file.
    //      * Please add your credentials here if you haven't.
    //      */
    //     $config = include_once 'api-config.php';
    //     // We don't want to cache the WSDL (for testing purposes).
    //     ini_set("soap.wsdl_cache_enabled", 0);
            
    //     //! This is the WSDL url.
    //     $soapUrl = "http://{$config['soap_ip']}/soap3/api.wsdl";
            
    //     //! Connect to the SOAP server.
    //     $client = new SoapClient(
    //         $soapUrl,
    //         array(
    //             // Let's debug in case we hit a snag.
    //             'trace' => 1
    //         )
    //     );
    //     if( !$client ) {
    //         throw new \Exception( "!!! Could not connect to SOAP server at '{$soapUrl}'.\n");                    
    //     }
            
    //     echo "== Information ==\n";
    //     echo "IP: {$config['soap_ip']}\n";
    //     echo "URL: {$soapUrl}\n";
            
    //     echo "== Authentication ==\n";
    //     try {
    //         //! This authenticates a user for the duration of this script.
    //         $result = $client->authenticate( $config['soap_username'], $config['soap_password']);
    //         if( !$result ) {
    //             throw new \Exception( "!!! Could not authenticate with the server.\n");
    //         } else {
    //             //! This gets the user ID (UID) of the authenticated user.
    //             $result = $client->getAuthenticatedUid();
    //             echo "* Successfully authenticated with UID '{$result}'.\n";
    //         }
    //     } catch( Exception $e ) {
    //         throw new \Exception( $e );
    //     }
            
    //     echo "== Device list ==\n";
    //     try {
    //         $devices = $client->core_getDevices();
    //         if( !$devices ) {
    //             throw new \Exception("!!! Could not get any device information.\n");
    //         }
    //         foreach( $devices as $device ) {
    //             echo "Device:\n";
    //             echo "   ID: {$device->id}\n";
    //             echo "   Name: {$device->name}\n";
    //             echo "   Description: {$device->description}\n";
    //             echo "   IP: {$device->ip}\n";
    //             echo "   Element count: {$device->elementCount}\n";
            
    //             echo "   Plugins:\n";
    //             $plugins = $client->core_getEnabledPluginsByDeviceId( $device->id );
    //             if( !$plugins ) {
    //                 echo "      (none).\n";
    //                 continue;
    //             }
    //             foreach( $plugins as $pluginString ) {
    //                 echo "      Plugin: {$pluginString}\n";
            
    //                 $objects = $client->core_getObjectsByDeviceIdAndPlugin( $device->id, $pluginString );
    //                 if( !$objects ) {
    //                     echo "         (none)\n";
    //                     continue;
    //                 } else {
    //                     foreach( $objects as $object ) {
    //                         echo "         Object:\n";
    //                         echo "            Name: {$object->name}\n";
    //                         echo "            Description: {$object->description}\n";
            
    //                         echo "            Polls:\n";
    //                         $polls = $client->core_getPollsByDeviceIdAndObjectName( $device->id, $pluginString, $object->name );
    //                         if( !$polls ) {
    //                             echo "               (none)\n";
    //                         } else {
    //                             foreach( $polls as $poll ) {
    //                                 echo "               Indicator: {$poll->indicator}\n";
            
    //                                 // Make a new graph object.
    //                                 $graph = $client->factory_Graph();
            
    //                                 // It's a line graph.
    //                                 $graph->graphType = "graph_line";
            
    //                                 $graphDataSource = $client->factory_GraphDataSource();
    //                                 $graphDataSource->deviceId = $device->id;
    //                                 $graphDataSource->plugin = $pluginString;
    //                                 $graphDataSource->objectName = $object->name;
    //                                 $graphDataSource->indicator = $poll->indicator;
            
    //                                 $graph->dataSources[] = $graphDataSource;
            
    //                                 // Let's do from 4 hours ago until now.
    //                                 $timespan = $client->factory_Timespan();
    //                                 $timespan->startTime = time() - ( 60 * 60 * 4 );
    //                                 $timespan->endTime = time();
            
                                    
    //                                 $url = $client->report_makeUrlFromGraph( $graph, $timespan, false, $soapIp );
    //                                 $secureUrl = $client->report_makeSecureUrlFromGraph( $graph, $timespan, false, $soapIp );
    //                                 $relativeUrl = $client->report_makeRelativeUrlFromGraph( $graph, $timespan, false, $soapIp );
    //                                 echo "<pre>";
    //                                 echo "               URL: {$url}\n";
    //                                 echo "               Secure URL: {$secureUrl}\n";
    //                                 echo "               Relative URL: {$relativeUrl}\n";
    //                                 echo "\n\n";
    //                                 echo "</pre>";
    //                             }
    //                         }
    //                     }
    //                 }
    //             }
    //         }
    //     } catch( Exception $e ) {
    //         echo "Exception:\n";
    //         print_r( $e );
    //     }
    // }
        

    
    /**
     * Returns a connection token to aid communication with the datasource.
     * @return boolean
     */
    private function getConnectionToken($sourceName) {
        try {
            $sourceLoader = $this->sourceLoader;
            $settings = $sourceLoader($sourceName);

            return $settings;            
        } catch (Exception $x) {
            return FALSE;
        }
    }   
}

class NetBossEntity extends MiddlewareEntity {
    
}

class NetBossComplexEntity extends MiddlewareComplexEntity {
    
}

class NetBossEntityCollection extends MiddlewareEntityCollection {
    
}
