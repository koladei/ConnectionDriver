<?php

namespace com\mainone\middleware;

//$compromise 'c:\inetpub\wwwroot\itsupport\sites\all\modules\custom\settings_provider\settings_provider\MiddlewareConnectionDriver.php';

use com\mainone\middleware\MiddlewareConnectionDriver;
use com\mainone\middleware\MiddlewareQueryFragment;

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

    private static function stringifier($current) {
        $operator_map = [
            'eq' => '=',
            'gt' => '>',
            'lt' => '<',
            'ge' => '>=',
            'le' => '<=',
        ];

        $s = "({$current->getField()}{$current->getOperator($operator_map)}{$current->getValue()})";
        $ands = '';
        $ors = '';
        foreach ($current->getAnds() as $ind => $and) {
            $ands .= $and->toString();
        }

        if (strlen($ands) > 0) {
            $ands = "&{$ands}{$s}";
        }

        foreach ($current->getOrs() as $ind => $or) {
            $ors .= $or->toString();
        }

        if (strlen($ors) > 0) {
            $ors = "&{$ors}{$s}";
        }

        $addBraces = strlen($ors) > 0 || strlen($ands) > 0 ? true : false;

        if ($addBraces) {
            return "({$ands}{$ors})";
        } else {
            return $s;
        }
    }

    public function __construct($host, $protocol, $port = 636, $username = '', $password = '', $dn = '') {
        $this->host = $host;
        $this->protocol = $protocol;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->dn = $dn;

        return $this;
    }

    public static function getLDAPQueryFragment($field, $value, $operator) {
        $fragment = new MiddlewareQueryFragment($field, $value, $operator);
        $fragment->setProcessor(function() {
            return LDAPConnectionDriver::stringifier(...func_get_args());
        });
        return $fragment;
    }

    public function getItemsByQueryFragment(MiddlewareQueryFragment $query, $options = [], $ldapbind = NULL) {

        // Try getting the phone number from active directory
        $con = NULL;
        $dn = $this->dn;

        // obtain a connection binding.
        if (is_null($ldapbind)) {
            $ldapbind = $this->bindTOLDAPServer($con);
        }

        $select = count($options) > 0 ? $options : ['mobile'];
        if ($ldapbind) {
            // bind and find user by uid
            $user_search = ldap_search($con, $dn, $query->toString(), $select);
            $user_entries = ldap_get_entries($con, $user_search);

            foreach ($user_entries as &$user_entry) {
                $s = [];
                foreach ($select as $sel) {
                    $s[$sel] = $user_entry[$sel];
                    unset($s[$sel]['count']);
                }
                $user_entry = $s;
            }

            unset($user_entries['count']);
            $user_entries = json_decode(json_encode($user_entries));

            return $user_entries;
        }

        return FALSE;
    }

    private function bindTOLDAPServer(&$connection = NULL) {
        //connect to active directory and get the details of the user.
        putenv('LDAPTLS_REQCERT=never');
        $server = "{$this->protocol}://{$this->host}:{$this->port}"; //"ldaps://moghdc01.mainonecable.com:636";
        
        $connection = ldap_connect($server);
        $binding = null;

        $ldaprdn = $this->username;
        $ldappass = $this->password;

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        //If AD responds.
        if ($connection) {
            $binding = ldap_bind($connection, $ldaprdn, $ldappass);
            return $binding;
        }

        return false;
    }

}
