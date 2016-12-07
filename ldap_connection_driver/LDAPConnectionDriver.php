<?php

namespace com\mainone\middleware;

use com\mainone\middleware\MiddlewareConnectionDriver;
use com\mainone\middleware\MiddlewareQueryFragment;

/**
 * Description of LDAPConnectionDriver
 *
 * @author Kolade.Ige
 */
class LDAPConnectionDriver extends MiddlewareConnectionDriver {

    private $query = NULL;

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

    public function __construct() {
        
    }

    public static function getLDAPQueryFragment($field, $value, $operator) {
        $fragment = new MiddlewareQueryFragment($field, $value, $operator);
        $fragment->setProcessor(function() {
            return LDAPConnectionDriver::stringifier(...func_get_args());
        });
        return $fragment;
    }

    public static function getItemsByQueryFragment(MiddlewareQueryFragment $query, $options = []) {
        
        // Try getting the phone number from active directory
        $con = NULL;
        $dn = NULL;
        $ldapbind = self::bindTOLDAPServer($con, $dn);
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

    private static function bindTOLDAPServer(&$_global_connection = NULL, &$dn = NULL) {
        //connect to active directory and get the details of the user.
        putenv('LDAPTLS_REQCERT=never');
        $server = "ldaps://moghdc01.mainonecable.com:636";
        $dn = "DC=mainonecable,DC=com";

        $_global_connection = ldap_connect($server);
        $_global_ldap_binding = null;

        $ldaprdn = 'koladexa';
        $ldappass = 'Nigeria234';

        ldap_set_option($_global_connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($_global_connection, LDAP_OPT_REFERRALS, 0);

        //If AD responds.
        if ($_global_connection) {
            $_global_ldap_binding = ldap_bind($_global_connection, $ldaprdn, $ldappass);
            return $_global_ldap_binding;
        }

        return false;
    }

}
