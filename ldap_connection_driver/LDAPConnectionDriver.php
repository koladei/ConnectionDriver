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
class LDAPConnectionDriver extends MiddlewareConnectionDriver
{

    private $query = null;
    private $host = 'ldapserver';
    private $protocol = 'ldaps';
    private $port = 636;
    private $username = '';
    private $password = '';
    private $dn = '';

    public function __construct(callable $driverLoader, callable $sourceLoader, $identifier = __CLASS__, $host, $protocol, $port = 636, $username = '', $password = '', $dn = '')
    {
        parent::__construct($driverLoader, $sourceLoader, $identifier);

        $this->host = $host;
        $this->protocol = $protocol;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->dn = $dn;

        return $this;
    }

    public function updateItemInternal($entityBrowser, &$connectionToken = null, $id, \stdClass $object, array $otherOptions = [])
    {
    }

    public function createItemInternal($entityBrowser, &$connectionToken = null, \stdClass $object, array $otherOptions = [])
    {
    }

    public function deleteItemInternal($entityBrowser, &$connectionToken = null, $id, array $otherOptions = [])
    {
    }

    public function getItemsInternal($entityBrowser, &$ldapbind = null, array $select, $filter, $expands = [], $otherOptions = [])
    {

        // Remove the field prefix
        $filter = str_replace('_xENTITYNAME_', '', $filter);

        // Try getting the phone number from active directory
        $con = null;
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
                                } elseif ($fieldInfo->isPhoto()) {
                                    $en = base64_encode($sele);
                                    $sele = "data:image/png;base64,{$en}";
                                } elseif ($fieldInfo->isBlob()) {
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

    public function getStringer()
    {
        return MiddlewareFilter::LDAP;
    }

        
    public function executeTargetedFunctionInternal($entityBrowser, $id, $functionName, array $data = [], &$connectionToken = null, array $otherOptions = [])
    {
        switch ($functionName) {
            case 'verifypassword': {        
                // Try getting the phone number from active directory
                $con = null;
                $dn = $this->dn;
                $dc = explode(',', str_replace('DC=', '', trim($dn)));

                if(!isset($data['username'])){
                    throw new \Exception('Username can not be blank');
                }

                if(!isset($data['password']) || strlen($data['password']) < 1){
                    throw new \Exception('Password can not be blank');
                }
        
                // obtain a connection binding.
                $ldapbind = $this->bindTOLDAPServer($con, "{$dc[0]}\\{$data['username']}", $data['password']);
                
                if($ldapbind){
                    return $this->getItemById('objects', $id, 'DN,EMail,DisplayName');
                }
                throw new \Exception('Username and/or password is not valid');
            }
            default:{
                throw new \Exception("The function '{$functionName}' is not recognized.");
            }
        }
    }

    private function bindTOLDAPServer(&$connection = null, $username = NULL, $password = NULL)
    {
        //connect to active directory and get the details of the user.
        putenv('LDAPTLS_REQCERT=never');
        $server = "{$this->protocol}://{$this->host}:{$this->port}"; //"ldaps://moghdc01.mainonecable.com:636";

        $connection = \ldap_connect($server);
        $binding = null;


        $ldaprdn = is_null($username) ? $this->username : $username;
        $ldappass = is_null($username) ? $this->password : $password;

        \ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        \ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        //If AD responds.
        if ($connection) {
            $binding = \ldap_bind($connection, $ldaprdn, $ldappass);
            return $binding;
        }


        return false;
    }

    protected function getDefaultFilter()
    {

        return 'ObjectClass eq \'*\'';
    }
}
