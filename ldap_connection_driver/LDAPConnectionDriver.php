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
class LDAPConnectionDriver extends MiddlewareConnectionDriver {

    private $query = NULL;
    private $host = 'ldapserver';
    private $protocol = 'ldaps';
    private $port = 636;
    private $username = '';
    private $password = '';
    private $dn = '';

    public function __construct(callable $driverLoader, $host, $protocol, $port = 636, $username = '', $password = '', $dn = '') {
        parent::__construct($driverLoader);

        $this->host = $host;
        $this->protocol = $protocol;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->dn = $dn;

        return $this;
    }

    
    public function executeFunctionInternal($functionName, array $objects = [], &$connectionToken = NULL, array $otherOptions = []) {
        
        throw new \Exception('Not yet implemented');
        
        foreach($entityBrowsers as &$entityBrowser){
                $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];
        }

        // Get a connection token
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
            $objs = [];
            foreach($entityBrowsers as $key => &$entityBrowser){
                if(isset($objects[$key])){
                    $object = $entityBrowser->reverseRenameFields($objects[$key]);
                    $objs[] = $object;//json_encode($object);
                }
            }
            $obj = json_encode($objs);
            
            
        } else {
            throw new \Exception('Unable to connect to Salesforce');
        }
    }

    public function updateItemInternal($entityBrowser, &$connectionToken = NULL, $id, \stdClass $object, array $otherOptions = []) {
        
    }

    public function createItemInternal($entityBrowser, &$connectionToken = NULL, \stdClass $object, array $otherOptions = []) {
        
    }

    public function deleteItemInternal($entityBrowser, &$connectionToken = NULL, $id, array $otherOptions = []) {
        
    }

    public function getItemsInternal($entityBrowser, &$ldapbind = NULL, array $select, $filter, $expands = [], $otherOptions = []) {

        // Remove the field prefix
        $filter = str_replace('_xENTITYNAME_', '', $filter);

        // Try getting the phone number from active directory
        $con = NULL;
        $dn = $this->dn;

        // obtain a connection binding.
        $ldapbind = $this->bindTOLDAPServer($con);

        $limit = isset($otherOptions['$top']) ? $otherOptions['$top'] : 100;

        if ($ldapbind) {
            $user_search = \ldap_search($con, $dn, "{$filter}", $select, 0, $limit);
            $user_entries = \ldap_get_entries($con, $user_search);

            foreach ($user_entries as &$user_entry) {
                if (is_array($user_entry)) {
                    foreach ($user_entry as $f => &$sel) {
                        if (is_array($sel)) {
                            $fieldInfo = $entityBrowser->getFieldByInternalName($f);
                            unset($sel['count']);
                            foreach ($sel as &$sele) {

                                if ($fieldInfo->getDisplayName() == 'Id') {
                                    $sele = strtolower($sele);
                                } else
                                if ($fieldInfo->isPhoto()) {
                                    $en = base64_encode($sele);
                                    $sele = "data:image/png;base64,{$en}";
                                } else if ($fieldInfo->isBlob()) {
                                    $sele = base64_encode($sele);
                                }
                            }
                        }
                    }
                }
            }

            unset($user_entries['count']);
            $user_entries = \json_decode(json_encode($user_entries));

            return $user_entries;
        }

        return [];
    }

    public function getStringer() {
        return MiddlewareFilter::LDAP;
    }

    private function bindTOLDAPServer(&$connection = NULL) {
        //connect to active directory and get the details of the user.
        putenv('LDAPTLS_REQCERT=never');
        $server = "{$this->protocol}://{$this->host}:{$this->port}"; //"ldaps://moghdc01.mainonecable.com:636";

        $connection = \ldap_connect($server);
        $binding = null;

        $ldaprdn = $this->username;
        $ldappass = $this->password;

        \ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        \ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        //If AD responds.
        if ($connection) {
            $binding = \ldap_bind($connection, $ldaprdn, $ldappass);
            return $binding;
        }


        return false;
    }

    protected function getDefaultFilter() {

        return 'ObjectClass eq \'*\'';
    }

}
