<?php

namespace com\mainone\middleware;

include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/MiddlewareFilter.php');
include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/MiddlewareODataFilterProcessor.php');
include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/EntityDefinitionBrowser.php');

use com\mainone\middleware\MiddlewareFilter;
use com\mainone\middleware\MiddlewareODataFilterProcessor;
use com\mainone\middleware\EntityDefinitionBrowser;

/**
 * Description of MiddlewareConnectionDriver
 *
 * @author Kolade.Ige
 */
abstract class MiddlewareConnectionDriver {

    protected $entitiesByInternalName = []; //contains a list of entities, keyed by internal name
    protected $entitiesByDisplayName = []; //contains a list of entities, keyed by display name
    protected $driverLoader = NULL; //function to be called when there is need to load a driver that has never been loaded.
    protected $drivers = []; //a list of drivers that have been loaded during this session.
    protected $connectionToken = NULL;
    protected $maxRetries = 50;    
    protected $sourceLoader = NULL;

    public abstract function getItemsInternal($entityBrowser, &$connection_token = NULL, array $select, $filter, $expands = [], $otherOptions = []);

    public abstract function updateItemInternal($entityBrowser, &$connectionToken = NULL, $id, \stdClass $object, array $otherOptions = []);

    public abstract function createItemInternal($entityBrowser, &$connectionToken = NULL, \stdClass $object, array $otherOptions = []);

    public abstract function deleteItemInternal($entityBrowser, &$connectionToken = NULL, $id, array $otherOptions = []);

    public abstract function executeFunctionInternal($functionName, array $objects = [], &$connectionToken = NULL, array $otherOptions = []);
    
    public abstract function getStringer();

    public function __construct(callable $driverLoader, callable $sourceLoader = NULL) {
        $this->driverLoader = $driverLoader;
        $this->sourceLoader = $sourceLoader;
    }
    
    /**
     * Loads the entity definition in the DataDictionary into memory.
     *
     * @param array $entities
     * @return MiddlewareConnectonDriver
     */
    public function setEntities(array $entities) {
        
        foreach ($entities as $entity_name => $entity) {
            $entityDef = new EntityDefinitionBrowser($entity_name, $entity, $this);
            $this->entitiesByInternalName[$entity['internal_name']] = $entityDef;
            $this->entitiesByDisplayName[$entityDef->getDisplayName()] = &$this->entitiesByInternalName[$entity['internal_name']]; //$entityDef;//&$this->entitiesByInternalName[$entity['internal_name']];
        }

        return $this;
    }

    /**
     * Returns the <class>EntityDefinitionBrowser</class> identified by $entity.
     *
     * @param String $entity
     * @return EntityDefinitionBrowser
     */
    public function getEntityBrowser($entity){        
        $entityBrowser = $this->entitiesByDisplayName[$entity];
        $this->setStrategies($entityBrowser);
        return $entityBrowser;
    }

    public function loadDriver($driverName) {
        if (!isset($drivers[$driverName])) {
            $loader = $this->driverLoader;
            $driver = $loader($driverName);
            $drivers[$driverName] = $driver;
            return $drivers[$driverName];
        }
        return $drivers[$driverName];
    }

    /**
     * Parse Date / DateTime values returned by this connection driver
     * Sub-classes should override this method.
     *
     * @param DateTime $value
     * @return void
     */
    protected function parseDateValue($value) {
        $type_1 = '/^(([\d]{4})\-([\d]{2})\-([\d]{2})(T([\d]{2})\:([\d]{2})(\:([\d]{2}))?)?)$/';
        $type_2 = '/^(([\d]{4})\-([\d]{2})\-([\d]{2})(T([\d]{2})\:([\d]{2})))$/';
        $type_3 = '/^([\d]{4})\\-([\d]{2})\-([\d]{2})$/';

        if (preg_match($type_3, $value) == 1) {
            return \DateTime::createFromFormat('!Y-m-d', $value);
        } else if (preg_match($type_2, $value) == 1) {
            return \DateTime::createFromFormat('!Y-m-d\\TH:i', $value);
        } else if (preg_match($type_1, $value) == 1) {
            return \DateTime::createFromFormat('!Y-m-d\\TH:i:s', $value);
        }

        throw new \Exception("The time format is not known. Class MiddlewareConnectionDriver {$value}");
    }

    /**
     * Returns a number that represents the maximum allowed OR statements to use when converting from IN to OR.
     * 
     * This is necessary for systems that do not have an OOB implementation of the IN operator.
     *
     * @return void
     */
    public function getMaxInToOrConversionChunkSize(){
        return 100;
    }

    //TODO: Make this function less nostic of the Drupal function.

    /**
     * Stores a value for later retrieval.
     *
     * @param String $key The key to identify the value to store.
     * @param mixed $value
     * @return void
     */
    public function storeValue($key, $value){
        variable_set("MW__{$key}", $value);
        return $this;
    }

    /**
     * Retrieves a value from that has been previously stored
     *
     * @param String $key The key to identity the value to be retrieved.
     * @param mixed $default
     * @return mixed
     */
    public function retrieveValue($key, $default = NULL){
        return variable_get("MW__{$key}", $default);
    }

    /**
     * Invokes a function call on the underlying system, passing the specified objects as parameters.
     *
     * @param String $functionName
     * @param array $objects
     * @param array $otherOptions
     * @return \stdClass representing the result of the function call.
     */
    public function executeFunction($functionName, array $objects = [], array $otherOptions = []) {

        $retryCount = isset($otherOptions['retryCount'])?$otherOptions['retryCount']:-1;
        $otherOptions['retryCount'] = $retryCount + 1;

        try{
            $result = $this->executeFunctionInternal($functionName, $objects, $this->connectionToken, $otherOptions);
            return $result;
        } catch(\Exception $exc){
            if($retryCount < $this->maxRetries){
                return $this->executeFunction($functionName, $objects, $otherOptions);
            } else {
                throw $exc;
            }
        }
    }

    /**
     * Returns the resource identified by <code>$id</code>
     *
     * @param EntityDefinitionBrowser $entityBrowser A reference to the entity datasource.
     * @param String $id The unique identifier of the resource to be retrieved. 
     * @param mixed $select Comma-separated string or array of the resource fields to return.
     * @param string $expands Comma-separated string or array of the sub-resource fields to return.
     * @param array $otherOptions Key-Value array of other query parameters.
     * @return \stdClass
     */
    public function getItemById($entityBrowser, $id, $select, $expands = '', $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : (isset($this->entitiesByDisplayName[$entityBrowser])?$this->entitiesByDisplayName[$entityBrowser]:NULL);
        if(is_null($entityBrowser)){
            throw new \Exception('Invalid entity could not be found.');
        }
        
        $result = $this->getItemsByIds($entityBrowser, [$id], $select, $expands, $otherOptions);

        reset($result);
        $first_key = key($result);

        return count($result) > 0 ? $result[$first_key] : NULL;
    }

    /**
     * Returns the resources identified by the array ids in $ids.
     *
     * @param EntityDefinitionBrowser $entityBrowser A reference to the entity datasource.
     * @param String $id The unique identifier of the resource to be retrieved. 
     * @param mixed $select Comma-separated string or array of the resource fields to return.
     * @param string $expands Comma-separated string or array of the sub-resource fields to return.
     * @param array $otherOptions Key-Value array of other query parameters.
     * @return \stdClass
     */
    public function getItemsByIds($entityBrowser, $ids, $select, $expands = '', $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : (isset($this->entitiesByDisplayName[$entityBrowser])?$this->entitiesByDisplayName[$entityBrowser]:NULL);
        if(is_null($entityBrowser)){
            throw new \Exception('Invalid entity could not be found.');
        }

        $result = $this->getItemsByFieldValues($entityBrowser, $entityBrowser->getIdField(), $ids, $select, $expands, $otherOptions);
        return $result;
    }

    /**
     * Undocumented function
     *
     * @param EntityDefinitionBrowser | String $entityBrowser  A reference to the entity datasource.
     * @param EntityFieldDefinition $entityField A reference to the resource field definition to be used as match criteria.
     * @param array $values An array of values to be used as search criteria.
     * @param array | String $select Comma-separated string or array of the resource fields to return.
     * @param string $expands Comma-separated string or array of the sub-resource fields to return.
     * @param array $otherOptions Key-Value array of other query parameters.
     * @return \stdClass
     */
    public function getItemsByFieldValues($entityBrowser, EntityFieldDefinition $entityField, array $values, $select, $expands = '', &$otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : (isset($this->entitiesByDisplayName[$entityBrowser])?$this->entitiesByDisplayName[$entityBrowser]:NULL);
        if(is_null($entityBrowser)){
            throw new \Exception('Invalid entity could not be found.');
        }

        // implode the values based on the type of the field
        $implosion = '';
        $backslash = '\'';
        $type = $entityField->getDataType();
        switch ($type) {
            case 'int': {
                    $implosion = implode(',', $values);
                    break;
                }
            default: {
                    $implosion = implode("_x0027_,_x0027_", $values);
                    $implosion = str_replace("'", "{$backslash}'", $implosion);
                    $implosion = str_replace("_x0027_", "'", $implosion);

                    // $implosion = implode('\',\'', $values);
                    $implosion = "'{$implosion}'";
                }
        }

        $this->connectionToken = isset($otherOptions['$connectionToken'])?$otherOptions['$connectionToken']: $this->connectionToken;
        $otherOptions['$connectionToken'] = &$this->connectionToken;

        $additionalFilter = isset($otherOptions['more_filter']) ? "({$otherOptions['more_filter']}) and " : '';
        $result = $this->getItems($entityBrowser, $select, "{$additionalFilter}{$entityField->getDisplayName()} IN({$implosion})", $expands, $otherOptions);
        return $result;
    }

    public function updateItem($entityBrowser, $id, \stdClass $object, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : (isset($this->entitiesByDisplayName[$entityBrowser])?$this->entitiesByDisplayName[$entityBrowser]:NULL);
        if(is_null($entityBrowser)){
            throw new \Exception('Invalid entity could not be found.');
        }

        $entityBrowser = $this->setStrategies($entityBrowser);

        $retryCount = isset($otherOptions['retryCount'])?$otherOptions['retryCount']:0;
        $otherOptions['retryCount'] = $retryCount + 1;

        // Strip-out invalid fields
        $setFields = $entityBrowser->getValidFieldsByDisplayName(array_keys(get_object_vars($object)));

        $obj = new \stdClass();
        foreach ($setFields as $setField) {
            // avoid objects
            if (!is_object($object->{$setField->getDisplayName()}) && !is_array($object->{$setField->getDisplayName()})) {
                $obj->{$setField->getDisplayName()} = $object->{$setField->getDisplayName()};
            }
        }

        if (!isset($otherOptions['$select'])) {
            $otherOptions['$select'] = EntityFieldDefinition::getDisplayNames($setFields);
        } else {
            $abccd = is_string($otherOptions['$select']) ? explode(',', $otherOptions['$select']) : (is_array($otherOptions['$select']) ? $otherOptions['$select'] : []);
            $abccc = array_merge($abccd, EntityFieldDefinition::getDisplayNames($setFields));
            $otherOptions['$select'] = array_unique($abccc);
        }

        if (!isset($otherOptions['$expand'])) {
            $otherOptions['$expand'] = '';
        }

        try{
            if ($this->updateItemInternal($entityBrowser, $this->connectionToken, $id, $obj, $otherOptions)) {
                return $this->getItemById($entityBrowser, $id, $otherOptions['$select'], $otherOptions['$expand'], $otherOptions);
            }
        } catch(\Exception $exc){
            if($retryCount < $this->maxRetries){
                return $this->updateItem($entityBrowser, $id, $object, $otherOptions);
            } else {
                throw $exc;
            }
        }
    }

    public function createItem($entityBrowser, \stdClass $object, array $otherOptions = []) {

        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : (isset($this->entitiesByDisplayName[$entityBrowser])?$this->entitiesByDisplayName[$entityBrowser]:NULL);
        if(is_null($entityBrowser)){
            throw new \Exception('Invalid entity could not be found.');
        }
        
        $entityBrowser = $this->setStrategies($entityBrowser);
        $retryCount = isset($otherOptions['retryCount'])?$otherOptions['retryCount']:0;
        $otherOptions['retryCount'] = $retryCount + 1;

        // Strip-out invalid fields
        $setFields = $entityBrowser->getValidFieldsByDisplayName(array_keys(get_object_vars($object)));

        $obj = new \stdClass();
        foreach ($setFields as $setField) {
            // avoid objects
            if (!is_object($object->{$setField->getDisplayName()}) && !is_array($object->{$setField->getDisplayName()})) {
                $obj->{$setField->getDisplayName()} = $object->{$setField->getDisplayName()};
            }
        }

        if (!isset($otherOptions['$select'])) {
            $otherOptions['$select'] = EntityFieldDefinition::getDisplayNames($setFields);
        } else {
            $abccd = is_string($otherOptions['$select']) ? explode(',', $otherOptions['$select']) : (is_array($otherOptions['$select']) ? $otherOptions['$select'] : []);
            $abccc = array_merge($abccd, EntityFieldDefinition::getDisplayNames($setFields));
            $otherOptions['$select'] = array_unique($abccc);
        }

        if (!isset($otherOptions['$expand'])) {
            $otherOptions['$expand'] = '';
        }

        $res = $this->createItemInternal($entityBrowser, $this->connectionToken, $obj);
        if (property_exists($res, 'd') && $res->success == TRUE) {
            $return = $this->getItemById($entityBrowser, $res->d, $otherOptions['$select'], $otherOptions['$expand'], $otherOptions);
            return $return;
        } else {
            if($retryCount < $this->maxRetries){
                return $this->createItem($entityBrowser, $object, $otherOptions);
            } else{
                throw new \Exception("Unable to create a new record in {$entityBrowser->getDisplayName()} of ".__CLASS__);
            }
        }
    }

    public function deleteItem($entityBrowser, $id, array $otherOptions = [], &$deleteCount = 0) {
        
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : (isset($this->entitiesByDisplayName[$entityBrowser])?$this->entitiesByDisplayName[$entityBrowser]:NULL);
        if(is_null($entityBrowser)){
            throw new \Exception('Invalid entity could not be found.');
        }
        
        $entityBrowser = $this->setStrategies($entityBrowser);
        
        $retryCount = isset($otherOptions['retryCount'])?$otherOptions['retryCount']: 0;
        $otherOptions['retryCount'] = $retryCount + 1;

        try{
            $deleteResult = $this->deleteItemInternal($entityBrowser, $this->connectionToken, $id, $otherOptions);
            $select = isset($otherOptions['$select'])?$otherOptions['$select']:['Id','Created','Modified'];
            $filter = isset($otherOptions['$filter'])?$otherOptions['$filter']:'';
            $expand = isset($otherOptions['$expand'])?$otherOptions['$expand']:'';

            $deleteCount = $deleteResult->d;
            try{                
                $return = $this->getItems($entityBrowser, $select, $filter, $expand);
                $deleteResult = $return;
                $deleteResult->deleteCount = $deleteCount;
            } catch(\Exception $ex) {
                $deleteResult = [];
            }
         
            return $deleteResult;
        } catch(\Exception $exc){
            if($retryCount < $this->maxRetries){
                return $this->deleteItem($entityBrowser, $id, $otherOptions);
            } else {
                throw $exc;
            }
        }
    }

    /**
     * Returns an array of entity items.
     *
     * @param [type] $entityBrowser
     * @param [type] $fields
     * @param [type] $filter
     * @param string $expandeds
     * @param array $otherOptions
     * @param array $performance
     * @return void
     */
    public function getItems($entityBrowser, $fields, $filter, $expandeds = '', $otherOptions = [], &$performance = []) {
        
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : (isset($this->entitiesByDisplayName[$entityBrowser])?$this->entitiesByDisplayName[$entityBrowser]:NULL);
        if(is_null($entityBrowser)){
            throw new \Exception('Invalid entity could not be found.');
        }

        $scope = $this;
        $entityBrowser = $this->setStrategies($entityBrowser);
        
        $retryCount = isset($otherOptions['retryCount'])?$otherOptions['retryCount']:0;
        $otherOptions['retryCount'] = $retryCount + 1;
        
        // Set the default limit        
        if (!isset($otherOptions['$top'])) {
            $otherOptions['$top'] = 100;
        } else {
            $top = intval($otherOptions['$top']);
            $otherOptions['$top'] = $top < 1 ? 100 : $top;
        }

        if (!isset($otherOptions['$skip'])) {
            $otherOptions['$skip'] = 0;
        } else {
            $skip = intval($otherOptions['$skip']);
            $otherOptions['$skip'] = $skip < 0 ? 0 : $skip;
        }

        if (!isset($otherOptions['$pageNumber'])) {
            $otherOptions['$pageNumber'] = 1;
        } else {
            $pageNumber = intval($otherOptions['$pageNumber']);
            $otherOptions['$pageNumber'] = $pageNumber < 1 ? 1 : $pageNumber;
        }

        if (!isset($otherOptions['$pageSize'])) {
            $otherOptions['$pageSize'] = $otherOptions['$top'];
        } else {
            $pageSize = intval($otherOptions['$pageSize']);
            $otherOptions['$pageSize'] = $pageSize < 1 ? $otherOptions['$top'] : $pageSize;
        }

        if (!isset($otherOptions['$distinct'])) {
            $otherOptions['$distinct'] = [];
        } else {
            $distinct = str_replace(' ', '', $otherOptions['$distinct']);
            $distinctRe = [];
            $distinctEx = explode(',', $distinct);
            foreach($distinctEx as $dis){
                $distinctRe[] = $entityBrowser->getFieldByDisplayName($dis)->getInternalName();
            }
            $otherOptions['$distinct'] = array_unique($distinctRe);
        }

        // if (!isset($otherOptions['$orderBy'])) {
        //     $otherOptions['$orderBy'] = 0;
        // } else {
        //     $orderBy = $otherOptions['$orderBy'];//str_replace(' ', '', $otherOptions['$orderBy']);
        //     $orderByRe = [];
        //     $orderByEx = explode(',', $orderBy);
        //     foreach($orderByEx as $dis){
        //         $distinctRe[] = $entityBrowser->getFieldByDisplayName($dis)->getInternalName();
        //     }
        //     $otherOptions['$orderBy'] = implode(',', $distinctRe);
        // }

        //TODO: Stop overriding $orderBy and implement code to rename fields.
        $otherOptions['$orderBy'] = "{$entityBrowser->getIdField()->getInternalName()} ASC";
        
        // Set the default filter
        $filter = strlen(trim($filter)) < 1 ? $this->getDefaultFilter() : $filter;

        // Convert the select parameter into an array.
        $fields = is_array($fields) ? $fields : preg_split('@(?:\s*,\s*|^\s*|\s*$)@', $fields, NULL, PREG_SPLIT_NO_EMPTY);
        $driverScope = $this;

        // Cleanse the $select parameter
        $select = self::getCurrentSelections($entityBrowser, $fields);

        // Process the $filter statement
        $filterExpression = MiddlewareODataFilterProcessor::convert($entityBrowser, $filter, NULL, $this->getStringer());

        // Convert the $expand parameter into an array
        $expands = [];
        $expandeds = is_array($expandeds) ? $expandeds : preg_split('@(?:\s*,\s*|^\s*|\s*$)@', $expandeds, NULL, PREG_SPLIT_NO_EMPTY);

        $yyy = [];
        foreach ($expandeds as &$expand) {
            if (($pos = strpos($expand, '/')) > 0) {
                $key = substr($expand, 0, $pos);
                $val = substr($expand, $pos + 1);
                $expand = $key;

                if (isset($expands[$expand])) {
                    if (!isset($expands[$expand]['expand'])) {
                        $expands[$expand]['expand'] = [];
                    }

                    $yyy = &$expands[$expand]['expand'];
                }

                if (!isset($yyy[$key])) {
                    $yyy[$key] = [$val];
                } else {
                    $yyy[$key][] = $val;
                }

                $expands[$expand]['expand'] = $yyy;
            }
            $fieldInfo = $entityBrowser->getFieldByDisplayName($expand);

            //check if this field can be expaanded
            if ($fieldInfo->isExpandable()) {
                // Get a reference to the remote driver
                $remoteDriver = $fieldInfo->getRemoteDriver();

                // $remoteEntityBrowser = $remoteDriver->entitiesByDisplayName[$fieldInfo->getRemoteEntityName()];
                $remoteEntityBrowser = isset($remoteDriver->entitiesByDisplayName[$fieldInfo->getRemoteEntityName()])? $remoteDriver->entitiesByDisplayName[$fieldInfo->getRemoteEntityName()]:NULL;

                //TODO: Review this later. Problem is because of cached entities
                if(!is_null($remoteEntityBrowser)){ //TODO: 
                    $remoteField = $remoteEntityBrowser->getFieldByDisplayName($fieldInfo->getRelatedForeignFieldName());

                    // Get the selected subfields of this expanded field
                    $expandX = self::getCurrentExpansions($entityBrowser, $expand, $fields);

                    // Ensure that the lookup field of the remote entity is included in the remote entity's selection
                    if (!in_array($remoteField->getDisplayName(), $expandX)) {
                        $expandX[] = $remoteField->getDisplayName();
                    }

                    $ex0 = isset($expands[$expand]) ? $expands[$expand] : [];
                    $ex1 = array_merge(['select' => $expandX, 'ids' => [], 'info' => $fieldInfo, 'remoteFieldInfo' => $remoteField, 'data' => []], $ex0);
                    $expands[$expand] = $ex1;

                    // Ensure the field this expansion depends on is selected.
                    $localFieldName = $fieldInfo->getRelatedLocalFieldName();
                    if (!in_array($localFieldName, $select)) {
                        $select[] = $localFieldName;
                    }
                }
            } else {
                throw new \Exception("Field {$expand} can not be expanded.");
            }
        }

        // Fetch the data of matching records
        $select = array_unique($entityBrowser->getFieldInternalNames($select));
        $dateFields = $entityBrowser->getFieldsOfTypeByInternalName(['date', 'datetime'], $select);

        $result = $this->getItemsInternal($entityBrowser, $this->connectionToken, $select, "{$filterExpression}", $expands, $otherOptions);

        if (!is_null($result)) {
            $select_map = $entityBrowser->getFieldsByInternalNames($select);
            array_walk($result, function(&$record) use($entityBrowser, $select_map, &$expands, $dateFields) {
                $record = $entityBrowser->renameFields($record, $select_map);
                foreach($dateFields as $dateField) {
                    $dateFieldName = $dateField->getDisplayName();

                    if(is_object($record) && !is_null($record) && !is_null($record->{$dateFieldName})) {
                        $dateVal =  $this->parseDateValue($record->{$dateFieldName});
                        $record->{$dateFieldName} = ( $dateField->isDateTime() ? $dateVal->format('Y-m-d\TH:i:s') : $dateVal->format('Y-m-d') );
                    } else if(is_array($record)) {
                        foreach($record as &$innerRecord){
                            if(is_object($innerRecord) && !is_null($innerRecord) && !is_null($innerRecord->{$dateFieldName})) {
                                $dateVal =  $this->parseDateValue($innerRecord->{$dateFieldName});
                                $innerRecord->{$dateFieldName} = ( $dateField->isDateTime() ? $dateVal->format('Y-m-d\TH:i:s') : $dateVal->format('Y-m-d') );
                            } else {                                
                                throw new \Exception('Error on date field');
                            } 
                        }
                    }      
                }

                // Prepare to fetch expanded data
                foreach ($expands as $expand_key => &$expand_val) {
                    $fieldInfo = $expand_val['info'];
                    $relatedKey = $fieldInfo->getRelatedLocalFieldName();
                    $ids = $entityBrowser->fetchFieldValues($record, $relatedKey);
                    if (is_array($ids)) {
                        $expand_val['ids'] = array_merge($expand_val['ids'], $ids);
                    } else {
                        $expand_val['ids'][] = $ids;
                    }
                }
            });

            // Fetch the related field values in one sweep
            array_walk($expands, function(&$expand_val, $expand_key) use($driverScope) {
                $localField = $expand_val['info'];
                $remoteField = $expand_val['remoteFieldInfo'];
                $remoteEntityBrowser = $remoteField->getParent();
                $remoteDriver = $remoteEntityBrowser->getParent();
                $otherOptions = ['$top' => 10000];

                if (!is_null($localField->getRemoteEntityFilter())) {
                    $otherOptions['more_filter'] = $localField->getRemoteEntityFilter();
                }

                // Remove duplicates
                $expand_val['ids'] = array_unique($expand_val['ids']);

                // Divide the keys into manageable chunks
                $max_chunk_size = $this->getMaxInToOrConversionChunkSize();
                $expand_chunks = array_chunk($expand_val['ids'], $max_chunk_size);
                $data = NULL;

                $ex = isset($expand_val['expand'][$localField->getDisplayName()]) ? $expand_val['expand'][$localField->getDisplayName()] : [];

                foreach ($expand_chunks as $chunk) {
                    $chunkResult = $remoteDriver->getItemsByFieldValues($remoteEntityBrowser, $remoteField, $chunk, $expand_val['select'], implode(',', $ex), $otherOptions);

                    // Combine this chunk result with previous chunk results
                    $data = $remoteEntityBrowser->mergeExpansionChunks($data, $chunkResult, $localField, $remoteField);
                }

                $expand_val['data'] = $data;
            });

            // Attach the fetched values to their corresponding parents
            array_walk($result, function(&$record, $recordIndex) use($entityBrowser, $expands) {

                // Prepare to fetch expanded data
                foreach ($expands as $expand_key => $expand_val) {
                    $fieldInfo = $expand_val['info'];
                    $record = $entityBrowser->joinExpansionToParent($recordIndex, $record, $fieldInfo, $expand_val['data']);
                }
            });
        } else {
            if($retryCount < $this->maxRetries){
                return $this->getItems($entityBrowser, $fields, $filter, $expandeds, $otherOptions) ;
            } else {
                throw new \Exception("Failed to get data from Entity {$entityBrowser->getDisplayName()}");
            }
        }
        
        return $result;
    }

    public function fetchFieldValues($record, $selected_field) {
        return [$record->{$selected_field}];
    }

    public function addExpansionToRecord($entity, &$record, EntityFieldDefinition $fieldInfo, $vals) {

        $keyVal = $record->{$fieldInfo->getRelatedLocalFieldName()};

        if(is_array($vals)){
            $results = isset($vals["{$keyVal}"]) ? $vals["{$keyVal}"] : ($fieldInfo->isMany() ? [] : NULL);
        } else if ($vals instanceof MiddlewareComplexEntity ) {
            $results = $vals->getByKey("{$keyVal}", $fieldInfo->isMany());
        } else {
            $results = NULL;
        }

        $record->{$fieldInfo->getDisplayName()} = $fieldInfo->isMany() ? ['results' => $results] : $results;

        return $record;
    }

    public function renameRecordFields($record, $selected_fields) {

        $r = new \stdClass();

        foreach ($selected_fields as $key => $field) {
            $displayName = $field->getDisplayName();
            if (is_object($record) && property_exists($record, $key)) {
                if (is_array($record->{$key})) {
                    if ($field->isArray()) {
                        $r->{$displayName} = $record->{$key};
                    } else if (count($record->{$key}) > 0) {
                        $r->{$displayName} = $record->{$key}[0];
                    }
                } else {
                    $r->{$displayName} = $field->isInteger()?intval($record->{$key}):$record->{$key};
                }
            } else if (is_array($record) && isset($record[$key])){
                if (is_array($record[$key])) {
                    if ($field->isArray()) {
                        $r->{$displayName} = $record[$key];
                    } else if (count($record[$key]) > 0) {
                        $r->{$displayName} = $record[$key][0];
                    }
                } else {
                    $r->{$displayName} = $field->isInteger()?intval($record[$key]):$record[$key];
                }
            } else {
                $r->{$displayName} = $field->isArray() ? [] : NULL;
            }
        }

        $record = $r;

        return $record;
    }

    public function reverseRenameRecordFields(EntityDefinitionBrowser $brower, \stdClass $record) {

        $r = new \stdClass();
        $keys = array_keys(get_object_vars($record));

        foreach ($keys as $key) {
            $internalName = $brower->getFieldByDisplayName($key)->getInternalName();
            if (property_exists($r, $internalName)) {
                if (!is_null($record->{$key})) {
                    $r->{$internalName} = $record->{$key};
                }
            } else {
                $r->{$internalName} = $record->{$key};
            }
        }

        return $r;
    }

    public function mergeRecordArray($data, $chunkResult, EntityFieldDefinition $localField, EntityFieldDefinition $remoteField = NULL) {
        $r = is_array($data) ? $data : [];

        if (!is_null($chunkResult)) {
            foreach ($chunkResult as $val) {
                if (is_null($remoteField)) {
                    $r[] = $val;
                } else {
                    $remoteFieldName = ($remoteField->isExpandable()) ? $remoteField->getRelatedLocalField()->getDisplayName() : $remoteField->getDisplayName();

                    $remoteFieldValue = $val->{$remoteFieldName};

                    // If there is no key matching the remote field value in the array, add it.
                    if (!isset($r["{$remoteFieldValue}"])) {
                        $r["{$remoteFieldValue}"] = NULL;
                        if ($localField->isMany()) {
                            $r["{$remoteFieldValue}"] = [];
                        }
                    }

                    // Put a value in the remote field key 
                    if ($localField->isMany()) {
                        $r["{$remoteFieldValue}"][] = $val;
                    } else {
                        $r["{$remoteFieldValue}"] = $val;
                    }
                }
            }
        }

        return $r;
    }

    public static function getCurrentSelections(EntityDefinitionBrowser $entityBrowser, array $fields) {

        // set the compulsary fields of the entity
        $required_fields = $entityBrowser->getMandatoryFieldNames();
        foreach ($required_fields as $required_field) {
            if (!in_array($required_field, $fields)) {
                $fields[] = $required_field;
            }
        }

        // remove complex or invalid fields
        $shorts = [];
        $fields = array_values(array_filter($fields, function(&$item) use(&$shorts) {
                    $shorthand = '/[\[]([\w\|\d]+)[\]]/i';
                    $matchs = [];
                    preg_match_all($shorthand, $item, $matchs, PREG_SET_ORDER);

                    if (strpos($item, '/') > -1) {
                        return FALSE;
                    } else
                    if (count($matchs) > 0) {
                        foreach ($matchs as $mat) {
                            $ss = preg_split('@(?:\s*\|\s*|^\s*|\s*$)@', $mat[1], NULL, PREG_SPLIT_NO_EMPTY);
                            foreach ($ss as $s) {
                                if (!in_array($s, $shorts)) {
                                    $shorts[] = $s;
                                }
                            }
                        }
                        return FALSE;
                    } else {
                        return $item;
                    }
                }));

        return array_merge($fields, $shorts);
    }

    public static function getCurrentExpansions(EntityDefinitionBrowser $entityBrowser, $field, $fields) {
        
        $regex = "/({$field})\/([^\s\,]+)/";
        $fieldsR = [];

        foreach ($fields as $item) {
            $a = [];
            preg_match($regex, $item, $a);
            if (count($a) > 0 && !in_array($a[2], $fieldsR)) {
                $fieldsR[] = $a[2];
            }
        }

        return $fieldsR;
    }

    protected function getDefaultFilter() {
        return '';
    }

    private function setStrategies(EntityDefinitionBrowser $entityBrowser) {
        $scope = $this;
        $entityBrowser->setRenameStrategy(function() use($scope) {
            return $scope->renameRecordFields(...func_get_args());
        });

        $entityBrowser->setReverseRenameStrategy(function() use($scope) {
            return $scope->reverseRenameRecordFields(...func_get_args());
        });

        $entityBrowser->setExpansionJoinStrategy(function() use($scope) {
            return $scope->addExpansionToRecord(...func_get_args());
        });

        $entityBrowser->setMergeExpansionChunksStrategy(function() use($scope) {
            return $scope->mergeRecordArray(...func_get_args());
        });

        $entityBrowser->setFieldValueFetchStrategy(function() use($scope) {
            return $scope->fetchFieldValues(...func_get_args());
        });

        return $entityBrowser;
    }

}

class MiddlewareEntity extends \stdClass {

    public function __construct(\stdClass $val = NULL) {
        if (!is_null($val)) {
            foreach ($val as $k => $v) {
                $this->{$k} = $v;
            }
        }
    }

}

class MiddlewareComplexEntity extends MiddlewareEntity {
    public function getByKey($key, $isMany = FALSE){
        return $isMany?[]:NULL;
    }
}

class MiddlewareEntityCollection extends \RecursiveArrayIterator {
    
}
