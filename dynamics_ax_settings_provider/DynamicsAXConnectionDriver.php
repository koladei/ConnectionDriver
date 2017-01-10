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
class DynamicsAXConnectionDriver extends MiddlewareConnectionDriver {

    private $endpoint;

    public function __construct(callable $driverLoader, $endpoint='http://molsptest:82/drp/CPortalService.svc/QueryTable/[~]') {
        parent::__construct($driverLoader);

        $this->endpoint = $endpoint;
    }

    public function fetchFieldValues($record, $selected_field){
        $r = [];

        foreach($record as &$member){
            $r[] = "{$member->{$selected_field}}";
        }

        return $r;
    }

    public function renameRecordFields($record, $selected_fields){

        foreach($record as &$member){
            $r = new DynamicsAXEntity();
            foreach ($selected_fields as $key => $field) {
                if(property_exists($member, $key)){
                    $r->{$field->getDisplayName()} = $member->{$key};
                }
            }
            $member = $r;
        }

        return $record;
    }

    public function mergeRecordArray($data, $chunkResult, EntityFieldDefinition $localField, EntityFieldDefinition $remoteField = NULL){
        $r = $data instanceof DynamicsAXComplexEntity ? $data : (new DynamicsAXComplexEntity());

        if(!is_null($chunkResult)){
            foreach($chunkResult as $entity => $val){
                if(property_exists($r, $entity)){
                    $x = &$r->{$entity};
                    if(is_null($remoteField)){                            
                        $x = array_merge($x, $val);
                    } else {
                        $remoteFieldName = $remoteField->getDisplayName();
                        $keyed_val = new DynamicsAXEntityCollection();
                        array_walk($val, function($member) use($remoteField, $localField, $remoteFieldName, &$keyed_val){
                            $y = $member->{$remoteFieldName};
                            if($localField->isMany()){
                                $keyed_val["{$y}"] = $member;
                            }else{
                                $keyed_val["{$y}"] = array_merge($keyed_val["{$y}"], $member);
                            }
                        });
                        $x = array_merge($x, $keyed_val);
                    }
                } else {
                    if(is_null($remoteField)){      
                        $r->{$entity} = $val;
                    } else {
                        $remoteFieldName = $remoteField->getDisplayName();
                        $keyed_val = new DynamicsAXEntityCollection();
                        array_walk($val, function($member) use($remoteField, $localField, $remoteFieldName, &$keyed_val){
                            // var_dump(['XXYYY' => array_keys(get_object_vars($member))]);
                            $y = $member->{$remoteFieldName};
                            $keyed_val["{$y}"] = $localField->isMany()?[$member]:$member;
                        });
                        $r->{$entity} = $keyed_val;
                    }
                }
            }
        }

        return $r;
    }

    function addExpansionToRecord($entity, &$record, EntityFieldDefinition $fieldInfo, $vals){
        foreach($record as &$member){
            if($vals instanceof DynamicsAXComplexEntity) {
                if(property_exists($vals, $entity)){
                    $keyVal = $member->{$fieldInfo->getRelatedLocalFieldName()};

                    $results = isset($vals->{$entity}["{$keyVal}"])?$vals->{$entity}["{$keyVal}"]:($fieldInfo->isMany()? []: NULL);
                    $member->{$fieldInfo->getDisplayName()} = $fieldInfo->isMany()? ['results' => $results]:$results;
                    unset($member->{$fieldInfo->getRelatedLocalFieldName()});
                } else {
                    $results = isset($vals->{$entity}["{$keyVal}"])?$vals->{$entity}["{$keyVal}"]:($fieldInfo->isMany()? []: NULL);
                    $member->{$fieldInfo->getDisplayName()} = $fieldInfo->isMany()? ['results' => $results]:$results;
                    // unset($member->{$fieldInfo->getRelatedLocalFieldName()}); 
                }
            } else {                
                parent::addExpansionToRecord($entity, $member, $fieldInfo, $vals);
            }
        }

        return $record;
    }

    public function updateItemInternal($entityBrowser, &$connectionToken = NULL, $id,  \stdClass $object, array $otherOptions = []){

    }
    public function createItemInternal($entityBrowser, &$connectionToken = NULL,  \stdClass $object, array $otherOptions = []){

    }  
    public function deleteItemInternal($entityBrowser, &$connectionToken = NULL, $id, array $otherOptions = []){
        
    }   
    
    public function getItemsInternal($entityBrowser, &$connection_token = NULL, array $select, $filter, $expands=[], $otherOptions=[]){        
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];

            // $filter = strlen($filter)>0?"{$filter} and dataareaid eq 'mong'" : "dataareaid eq 'mong'" ;
        $invoice_params = [
            '$filter' => $filter
            , '$select' => implode(',', $select)
            , '$top' => $otherOptions['$top']
        ];
        // var_dump("{$entityBrowser->getInternalName()} {$filter} {$otherOptions['$top']} ".implode(',', $select));

        $query_string = drupal_http_build_query($invoice_params);
        $url = "{$this->endpoint}/{$entityBrowser->getInternalName()}?{$query_string}";

        $tokenOption = array(
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
            , CURLOPT_PROTOCOLS => CURLPROTO_HTTP
            , CURLOPT_SSL_VERIFYPEER => 0
            , CURLOPT_SSL_VERIFYHOST => 0
        );

        $feed = mware_blocking_http_request($url, ['options' => $tokenOption, 'block' => true]);
        $res = (json_decode($feed->getContent()));

        if(is_object($res) && property_exists($res, 'd')){
            $x = $res->d;
            $z = new DynamicsAXEntityCollection();
            
            foreach($res->d as $a => $b){
                $z[$a] = $b;
                foreach($z[$a] as &$d){
                    $d = new DynamicsAXEntity($d);
                }
            }
            
            return $z;
        } else {
            throw new \Exception("{$feed->getContent()}");
        }
    }

    public function getStringer(){
        return MiddlewareFilter::XPP;
    }
}

class DynamicsAXEntity extends MiddlewareEntity{

}

class DynamicsAXComplexEntity extends MiddlewareComplexEntity{

}

class DynamicsAXEntityCollection extends MiddlewareEntityCollection {

}
