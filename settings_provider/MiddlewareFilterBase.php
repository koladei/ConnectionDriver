<?php

namespace com\mainone\middleware;

include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/IMiddlewareFilterGroup.php');
use com\mainone\middleware\IMiddlewareFilterGroup;

abstract class MiddlewareFilterBase {

    protected $stringer = NULL;

    protected $parent = NULL;

    const DEFAULT_STRINGER = 0;
    const SOQL = 1;
    const XPP = 2;
    const SQL = 3;
    const LDAP = 4;
    const CUSTOM = 5;
    const BMC = 6;

    public abstract function setStringifier(callable $processor = NULL);
    protected abstract function LDAPStringer(MiddlewareFilterBase &$scope);
    protected abstract function SOQLStringer(MiddlewareFilterBase &$scope);
    protected abstract function SQLStringer(MiddlewareFilterBase &$scope);
    protected abstract function DEFAULTStringer(MiddlewareFilterBase &$scope);
    protected abstract function XPPStringer(MiddlewareFilterBase &$scope);
    protected abstract function BMCStringer(MiddlewareFilterBase &$scope);

    public function __construct($behaviour = self::DEFAULT_STRINGER, callable $stringer = NULL) {

        switch($behaviour){
            case self::LDAP:{
                $this->stringer = 'LDAPStringer';
                break;
            }
            case self::SOQL:{
                $this->stringer = 'SOQLStringer';
                break;
            }
            case self::SQL:{
                $this->stringer = 'SQLStringer';
                break;
            }
            case self::XPP:{
                $this->stringer = 'XPPStringer';
                break;
            }
            case self::BMC:{
                $this->stringer = 'BMCStringer';
                break;
            }
            case self::DEFAULT_STRINGER:{
                $this->stringer = 'DEFAULTStringer';
                break;
            }
            case self::CUSTOM: {
                if(!is_null($stringer)) {            
                    $this->stringer = $stringer;
                } else {
                    throw new \Exception('stringer cannot be null if stringer type is CUSTOM');
                }
                break;
            }
            default:{                
                throw new \Exception('Invalid stringer type specified.');
            }
        }
    }

    /**
     * Converts this query fragment into a string
     * @return type string
     */
    public function __toString() {        
        $scope = $this;

        $stringer = $this->stringer;
        if(is_string($stringer)){
            return $this->$stringer($scope);
        } else if(is_callable($stringer)) {
            return $stringer($scope);
        } else {
            return 'Something is wrong with the stringer method';
        }
    }

    public function addToGroup(IMiddlewareFilterGroup &$parentGroup, $type = IMiddlewareFilterGroup::FRAGMENT_AND){
        // Remove from previous parent
        if(!is_null($this->parent)){
            $this->parent->removePart($this);
        }

        // Add to new parent
        $parentGroup->addPart($this, $type);
        $this->parent = &$parentGroup;
        return $this;
    }

    public function removeFromGroup(){
        // Remove from previous parent
        if(!is_null($this->parent)){
            $this->parent->removePart($this);
        }
        return $this;
    }
}
