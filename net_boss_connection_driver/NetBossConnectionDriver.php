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
        $url = 'http://nbportalproxy.mainone.net/echo.php';
        $data = ['method' => 'authenticate', 'parameters' => serialize(["api", "mumu&711"])];
        
        // use key 'http' even if you send the request to https://...
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result === FALSE) { /* Handle error */ }
        
        var_dump($result);
    }

    public function getStringer() {
        return MiddlewareFilter::SQL;
    }  
}
