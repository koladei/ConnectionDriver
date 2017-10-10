<?php

module_load_include('module', 'settings_provider');
$module_path = drupal_get_path('module', 'smsgateway_connection_driver');
include_once str_replace('/', DIRECTORY_SEPARATOR, $module_path . '/forms/admin.inc');
include_once str_replace('/', DIRECTORY_SEPARATOR, $module_path . '/SMSGatewayConnectionDriver.php');

use com\mainone\middleware\SMSGatewayConnectionDriver;

/**
 * Implement hook_menu().
 */
function smsgateway_connection_driver_menu()
{
    $items = [];

    // Link to cache update cron
    $items['smsgateway/update-sms-status'] = [
        'page callback' => 'settings_provider__update_sms_status'
        , 'file' => 'crons/cron.update-sms-status.inc'
        , 'page arguments' => ['internal']
        , 'access arguments' => ['access content']
        , 'type' => MENU_CALLBACK
    ];

    return $items;
}

/**
 * Implements hook_cron
 *
 * @return void
 */
function smsgateway_connection_driver_cron(){

    // Get the current date and time
    $now = new \DateTime();

    // Get the last and next run time of the workflow
    $smsgatewayupdateInfo = variable_get('SMSGATEWAY_UPDATEINFO', [
        'lastrun' => '1998-01-01T00:00:00'
        , 'frequency' => 30
        , 'inprogresssince' => '1996-01-01T00:00:00'
        , 'concurrencyinterval' => 30
    ]);
    $lastRun = \DateTime::createFromFormat('Y-m-d\TH:i:s', $smsgatewayupdateInfo['lastrun']);
    $frequency = new \DateInterval("PT{$smsgatewayupdateInfo['frequency']}M");
    $nextRun = \DateTime::createFromFormat('Y-m-d\TH:i:s', $smsgatewayupdateInfo['lastrun']);
    $nextRun->add($frequency);
        
    // Invoke the workflow if it is due to run.
    if($now > $nextRun) {        
        global $base_url;
    
        $tokenOption = [
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
            , CURLOPT_PROTOCOLS => CURLPROTO_HTTPS
            , CURLOPT_SSL_VERIFYPEER => FALSE
            , CURLOPT_SSL_VERIFYHOST => 0
            , CURLOPT_FOLLOWLOCATION => TRUE
            , CURLOPT_HTTPPROXYTUNNEL => TRUE
            , CURLOPT_VERBOSE => TRUE
        ];

        mware_http_request("{$base_url}/smsgateway/update-sms-status", ['options' => $tokenOption, 'callback' => function($event) use ($now, $smsgatewayupdateInfo){
            $smsgatewayupdateInfo['lastrun'] = $now->format('Y-m-d\TH:i:s');
            variable_set('SMSGATEWAY_UPDATEINFO', $smsgatewayupdateInfo);
        }]);
    }
    
    // TODO: IMPLEMENT A CONCURRENCY CHECK CONTROL FEATURE SO THAT MORETHAN 1 INSTANCE OF THIS WORKFLOW IS NOT STARTED.
}

function smsgateway_connection_driver_connection_driver_smsgateway_alter(&$container)
{
    $driver = new SMSGatewayConnectionDriver(function ($x) {
        $return = mware_connection_driver__get_driver($x);
        return $return;
    }, function ($source_name) {
        return smsgateway_connection_driver__get_settings($source_name);
    }, 'smsgateway');

    $defs = smsgateway_connection_driver__get_entity_definitions_local();
    $driver->setEntities($defs);
    $container['smsgateway'] = $driver;
}

function smsgateway_connection_driver__get_settings($key)
{
    $settings = new \stdClass();

    $settings->DSN = variable_get("SQL_SETTINGS_DSN_{$key}");
    $settings->Username = variable_get("SQL_SETTINGS_USERNAME_{$key}");
    $settings->Password = variable_get("SQL_SETTINGS_PASSWORD_{$key}");
    $settings->DatabaseType = variable_get("SQL_SETTINGS_DBTYPE_{$key}");

    return $settings;
}

/**
 * Implements hook_permission
 * @return array
 */
function smsgateway_connection_driver_permission()
{
    $permission = [];

    return $permission;
}

/**
 * Confirms if a user should be allowed to access something.
 * @param type $args
 * @return boolean
 */
function smsgateway_connection_driver__user_access($args)
{
    return true;
}

/**
 *
 * Returns entity definitions that have been marked as cacheable to the specified entity.
 *
 * @param String $targetDriver the connection driver to use to retrieve the cache.
 * @return void
 */
function smsgateway_connection_driver__get_delegated_entity_definitions($targetDriver)
{
    return settings_provider__format_delegated_definition('smsgateway', $targetDriver, function () {
        $args = func_get_args();
        $return = smsgateway_connection_driver__get_entity_definitions_local(...$args);
        return $return;
    });
}

function smsgateway_connection_driver__get_entity_definitions_local()
{
    return smsgateway_connection_driver__get_entity_definitions(...func_get_args())['smsgateway'];
}

/**
 * Returns all the entity definitions implemented in Dynamics AX.
 * @return type
 */
function smsgateway_connection_driver__get_entity_definitions()
{
    $return = [
        'smsgateway'=> [
            'smslog' => [
                'internal_name' => 'SMSDeliveryLog'
                , 'delegate_to' => 'sql'
                , 'manage_timestamps' => TRUE
                , 'fields' => [
                    'id' => [
                        'preferred_name' => 'Id'
                        , 'type' => 'string'
                        , 'mandatory' => 1,
                    ]
                    , 'batchid' => [
                        'preferred_name' => 'BatchId'
                        , 'type' => 'string'
                    ]
                    , 'created' => [
                        'preferred_name' => 'Created'
                        , 'type' => 'datetime'
                    ]
                    , 'modified' => [
                        'preferred_name' => 'Modified'
                        , 'type' => 'datetime'
                    ]
                    , 'delivered' => [
                        'preferred_name' => 'Delivered'
                        , 'type' => 'datetime'
                    ]
                    , 'lastchecked' => [
                        'preferred_name' => 'LastChecked'
                        , 'type' => 'datetime'
                    ]
                    , 'message' => [
                        'preferred_name' => 'Body'
                        , 'type' => 'string'
                    ]
                    , 'recipient' => [
                        'preferred_name' => 'Recipient'
                        , 'type' => 'string'
                    ]
                    , 'sentas' => [
                        'preferred_name' => 'From'
                        , 'type' => 'string'
                    ]
                    , 'sentby' => [
                        'preferred_name' => 'SentBy'
                        , 'type' => 'string'
                        , 'relationship' => [
                            'local_field' => 'SentBy'
                            , 'preferred_local_key_name' => 'SentById'
                            , 'remote_field' => 'Id'
                            , 'remote_type' => 'parent'
                            , 'remote_entity' => 'objects'
                            , 'remote_driver' => 'ldap'
                        ]
                    ]
                    , 'status' => [
                        'preferred_name' => 'Status'
                        , 'type' => 'string'
                    ]
                    , 'sentthrough' => [
                        'preferred_name' => 'SentThrough'
                        , 'type' => 'string'
                    ]
                    , 'smscount' => [
                        'preferred_name' => 'SMSCount'
                        , 'type' => 'int'
                    ]
                    , 'remoteid' => [
                        'preferred_name' => 'RemoteId'
                        , 'type' => 'string'
                    ]
                ]
            ]
        ]
    ];

    return $return;
}

