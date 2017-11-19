<?php

namespace com\mainone\middleware;

include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/MiddlewareFilter.php');
include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/MiddlewareODataFilterProcessor.php');
include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/EntityDefinitionBrowser.php');
include_once str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/EncoderDecoder.php');

use com\mainone\middleware\MiddlewareFilter;
use com\mainone\middleware\MiddlewareODataFilterProcessor;
use com\mainone\middleware\EntityDefinitionBrowser;
use com\mainone\middleware\EncoderDecoder;

/**
 * Description of MiddlewareConnectionDriver
 *
 * @author Kolade.Ige
 */
abstract class MiddlewareConnectionDriver
{

    protected $entitiesByInternalName = []; //contains a list of entities, keyed by internal name
    protected $entitiesByDisplayName = []; //contains a list of entities, keyed by display name
    protected $driverLoader = null; //function to be called when there is need to load a driver that has never been loaded.
    protected $drivers = []; //a list of drivers that have been loaded during this session.
    protected $connectionToken = null;
    protected $maxRetries = 50;
    protected $sourceLoader = null;
    protected static $loadedDrivers = [];
    protected $identifier = __CLASS__;    
    protected $preferredDateFormat = 'Y-m-d';
    protected $preferredDateTimeFormat = 'Y-m-d\TH:i:s';
    protected $utilityFunctions = [];

    public function getItemsInternal($entityBrowser, &$connection_token = null, array $select, $filter, $expands = [], $otherOptions = []){
        throw new \Exception('Not yet implemented');
    }

    public function updateItemInternal($entityBrowser, &$connectionToken = null, $id, \stdClass $object, array $otherOptions = []){
        throw new \Exception('Not yet implemented');
    }

    public function createItemInternal($entityBrowser, &$connectionToken = null, \stdClass $object, array $otherOptions = []){
        throw new \Exception('Not yet implemented');
    }

    public function deleteItemInternal($entityBrowser, &$connectionToken = null, $id, array $otherOptions = []){
        throw new \Exception('Not yet implemented');
    }
    
    public function executeFunctionInternal($functionName, array $objects = [], &$connectionToken = null, array $otherOptions = [])
    {
        throw new \Exception('Not yet implemented');
    }

    public function executeEntityFunctionInternal($entityBrowser, $functionName, array $objects = [], &$connectionToken = null, array $otherOptions = [])
    {
        throw new \Exception('Not yet implemented');
    }
    
    public function executeEntityItemFunctionInternal($entityBrowser, $id, $functionName, array $data = [], &$connectionToken = null, array $otherOptions = [])
    {
        throw new \Exception('Not yet implemented');
    }

    public function ensureDataStructureInternal($entityBrowser, &$connectionToken = null, array $otherOptions = []){
        throw new \Exception('Not yet implemented');
    }

    /**
     * Invokes the synch function of the specified driver
     *
     * @param mixed $entityBrowser
     * @param string $date
     * @return void
     */
    public function syncFromDate($entityBrowser, $date = '1900-01-01'){        
        $entityBrowser = $this->getEntityBrowser($entityBrowser);
        if (is_null($entityBrowser)) {
            throw new \Exception('Invalid entity could not be found.');
        }

        if($entityBrowser->shouldCacheData()){
            // Check for date constants
            $now = new \DateTime();
            switch($date){
                case '$today$':{
                    $date = $now->format('Y-m-d');
                    break;
                }
                case '$24HR$':{
                    $interval = new \DateInterval("PT24H");
                    $date = ($now->sub($interval))->format('Y-m-d');
                    break;
                }
                case '$month$':{
                    $interval = new \DateInterval("P1M");
                    $date = ($now->sub($interval))->format('Y-m-d');
                    break;
                }
                case '$year$':{
                    $interval = new \DateInterval("P1Y");
                    $date = ($now->sub($interval))->format('Y-m-d');
                    break;
                }
            }

            if(isset($this->utilityFunctions['date_sync_util'])){
                $sync = $this->utilityFunctions['date_sync_util'];
                $sourceDestination = implode('|', [$this->getIdentifier(), $entityBrowser->getCacheDriverName()]);
                $sync($sourceDestination, $entityBrowser->getDisplayName(), $date);
            }
        }
    }
    
    abstract public function getStringer();

    public function __construct(callable $driverLoader, callable $sourceLoader = null, $identifier = __CLASS__)
    {
        $this->driverLoader = $driverLoader;
        $this->sourceLoader = $sourceLoader;
        self::$loadedDrivers[$identifier] = &$this;
        $this->identifier = $identifier;
    }

    public function addUtilityFunction($name, callable $function){
        $this->utilityFunctions[$name] = $function;
    }

    public function isDriverLoaded($driverName)
    {
        return in_array($driverName, array_keys(self::$loadedDrivers));
    }

    public function getLoadedDrivers()
    {
        return self::$loadedDrivers;
    }

    public function getDriver($driverName)
    {
        return self::$loadedDrivers[$driverName];
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }
    
    /**
     * Loads the entity definition in the DataDictionary into memory.
     *
     * @param array $entities
     * @return MiddlewareConnectonDriver
     */
    public function setEntities(array $entities)
    {
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
    public function getEntityBrowser($entityBrowser)
    {
        $entityBrowser2 = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : (isset($this->entitiesByDisplayName[$entityBrowser])?$this->entitiesByDisplayName[$entityBrowser]:$entityBrowser);
        
        if($entityBrowser2 instanceof EntityDefinitionBrowser){
            $this->setStrategies($entityBrowser2);
            return $entityBrowser2;
        }

        return $entityBrowser;
    }

    public function loadDriver($driverName)
    {
        if (!in_array($driverName, array_keys(self::$loadedDrivers))) {
            $loader = $this->driverLoader;
            $driver = $loader($driverName);
            self::$loadedDrivers[$driverName] = &$driver;
            return self::$loadedDrivers[$driverName];
        }
        return self::$loadedDrivers[$driverName];
    }

    /**
     * Parse Date / DateTime values returned by this connection driver
     * Sub-classes should override this method.
     *
     * @param DateTime $value
     * @return void
     */
    protected function parseDateValue($value)
    {
        $type_1 = '/^(([\d]{4})\-([\d]{2})\-([\d]{2})(T([\d]{2})\:([\d]{2})(\:([\d]{2}))?)?)$/';
        $type_2 = '/^(([\d]{4})\-([\d]{2})\-([\d]{2})(T([\d]{2})\:([\d]{2})))$/';
        $type_3 = '/^([\d]{4})\\-([\d]{2})\-([\d]{2})$/';

        if (preg_match($type_3, $value) == 1) {
            return \DateTime::createFromFormat('!Y-m-d', $value);
        } elseif (preg_match($type_2, $value) == 1) {
            return \DateTime::createFromFormat('!Y-m-d\\TH:i', $value);
        } elseif (preg_match($type_1, $value) == 1) {
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
    public function getMaxInToOrConversionChunkSize()
    {
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
    public function storeValue($key, $value)
    {
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
    public function retrieveValue($key, $default = null)
    {
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
    public function executeFunction($functionName, array $data = [], array $otherOptions = [])
    {        
        $retryCount = isset($otherOptions['retryCount'])?$otherOptions['retryCount']:-1;
        $otherOptions['retryCount'] = $retryCount + 1;

        try {
            $result = $this->executeFunctionInternal($functionName, $data, $this->connectionToken, $otherOptions);
            return $result;
        } catch (\Exception $exc) {
            if ($retryCount < $this->maxRetries) {
                return $this->executeFunction($functionName, $data, $otherOptions);
            } else {
                throw $exc;
            }
        }
    }
    
    public function executeEntityFunction($entityBrowser, $functionName, array $data = [], array $otherOptions = [])
    {
        $entityBrowser = $this->getEntityBrowser($entityBrowser);
        if (is_null($entityBrowser)) {
            throw new \Exception('Invalid entity could not be found.');
        }

        $retryCount = isset($otherOptions['retryCount'])?$otherOptions['retryCount']:-1;
        $otherOptions['retryCount'] = $retryCount + 1;

        try {
            $result = $this->executeEntityFunctionInternal($entityBrowser, $functionName, $data, $this->connectionToken, $otherOptions);
            return $result;
        } catch (\Exception $exc) {
            if ($retryCount < $this->maxRetries) {
                return $this->executeEntityFunctionInternal($entityBrowser, $functionName, $data, $otherOptions);
            } else {
                throw $exc;
            }
        }
    }
    
    public function executeEntityItemFunction($entityBrowser, $id, $functionName, array $data = [], array $otherOptions = [])
    {
        $entityBrowser = $this->getEntityBrowser($entityBrowser);
        if (is_null($entityBrowser)) {
            throw new \Exception('Invalid entity could not be found.');
        }

        $retryCount = isset($otherOptions['retryCount'])?$otherOptions['retryCount']:-1;
        $otherOptions['retryCount'] = $retryCount + 1;

        try {
            $result = $this->executeEntityItemFunctionInternal($entityBrowser, $id, $functionName, $data, $this->connectionToken, $otherOptions);
            return $result;
        } catch (\Exception $exc) {
            if ($retryCount < $this->maxRetries) {
                return $this->executeEntityItemFunctionInternal($entityBrowser, $id, $functionName, $data, $otherOptions);
            } else {
                throw $exc;
            }
        }
    }

    /**
     * Creates the data table structure equivalent of the object schema in this connection driver.
     *
     * @param mixed $entityBrowser
     * @param array $otherOptions
     * @return void
     */
    public function ensureDataStructure($entityBrowser, array $otherOptions = []){
        $entityBrowser = $this->getEntityBrowser($entityBrowser);
        if (is_string($entityBrowser) || is_null($entityBrowser)) {
            throw new \Exception("Invalid entity '{$entityBrowser}' could not be found in {$this->getIdentifier()}");
        }

        // If this entity is cached to another driver
        $skipCache = isset($otherOptions['$skipCache'])?''.$otherOptions['$skipCache']:'0';
        $skipCache = $skipCache == '1'?TRUE:FALSE;
        if ($entityBrowser->shouldCacheData() && ($this->getIdentifier() != $entityBrowser->getCachingDriverName()) && $skipCache == FALSE) {
            // Load the driver instead
            $cacheDriver = $this->loadDriver($entityBrowser->getCachingDriverName());
            
            // Return the results from the cache driver.
            $args = func_get_args();
            $args[0] = strtolower("{$this->getIdentifier()}__{$entityBrowser->getInternalName()}");
            $return = $cacheDriver->ensureDataStructure(...$args);
            return $return;
        }
        
        // If this entity's storage is delegated to another driver.
        if ($entityBrowser->delegatesStorage() && ($this->getIdentifier() != $entityBrowser->getDelegateDriverName())) {
            // Load the driver instead
            $delegateDriver = $this->loadDriver($entityBrowser->getDelegateDriverName());
            
            // Return the results from the cache driver.
            $args = func_get_args();
            $args[0] = strtolower("{$this->getIdentifier()}__{$entityBrowser->getInternalName()}");
            $return = $delegateDriver->ensureDataStructure(...$args);
            return $return;
        }

        return $this->ensureDataStructureInternal($entityBrowser, $this->connectionToken, $otherOptions);
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
    public function getItemById($entityBrowser, $id, $select, $expands = '', $otherOptions = [])
    {
        $entityBrowser = $this->getEntityBrowser($entityBrowser);
        if (is_null($entityBrowser)) {
            throw new \Exception('Invalid entity could not be found.');
        }
        
        $result = $this->getItemsByIds($entityBrowser, [$id], $select, $expands, $otherOptions);

        reset($result);
        $first_key = key($result);

        return count($result) > 0 ? $result[$first_key] : null;
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
    public function getItemsByIds($entityBrowser, $ids, $select, $expands = '', $otherOptions = [])
    {
        $entityBrowser = $this->getEntityBrowser($entityBrowser);
        if (is_null($entityBrowser)) {
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
    public function getItemsByFieldValues($entityBrowser, EntityFieldDefinition $entityField, array $values, $select, $expands = '', &$otherOptions = [])
    {
        $entityBrowser = $this->getEntityBrowser($entityBrowser);
        if (is_string($entityBrowser) || is_null($entityBrowser)) {
            throw new \Exception("Invalid entity '{$entityBrowser}' could not be found in {$this->getIdentifier()}");
        }

        // implode the values based on the type of the field
        $implosion = '';
        $type = $entityField->getDataType();
        switch ($type) {
            case 'int': {
                    $implosion = implode(',', $values);
                    break;
            }
            default: {
                    $implosion = implode("_x0027_,_x0027_", $values);
                    $implosion = EncoderDecoder::escape($implosion);
                    $implosion = str_replace("_x0027_", "'", $implosion);
                    $implosion = "'{$implosion}'";
            }
        }

        $this->connectionToken = isset($otherOptions['$connectionToken'])?$otherOptions['$connectionToken']: $this->connectionToken;
        $otherOptions['$connectionToken'] = &$this->connectionToken;

        $additionalFilter = isset($otherOptions['more_filter']) ? "({$otherOptions['more_filter']}) and " : '';
        $result = $this->getItems($entityBrowser, $select, "{$additionalFilter}{$entityField->getDisplayName()} IN({$implosion})", $expands, $otherOptions);
        return $result;
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
    public function getItems($entityBrowser, $fields = 'Id', $filter = '', $expandeds = '', $otherOptions = [], &$performance = [])
    {
        $entityBrowser = $this->getEntityBrowser($entityBrowser);
        if (is_string($entityBrowser) || is_null($entityBrowser)) {
            throw new \Exception("Invalid entity '{$entityBrowser}' could not be found in {$this->getIdentifier()}");
        }       

        // Handle method redirection
        if($entityBrowser->shouldRedirectRead()){
            $providerInfo = $entityBrowser->getReadProviderInfo();
            $driver = $this;
            if($providerInfo->driver != $this->getIdentifier()){
                $driver = $this->loadDriver($providerInfo->driver);
            }
            
            // Execute the update provider's update method.
            $args = func_get_args();
            $args[0] = $providerInfo->entity;
            $return = NULL;
            
            try {
                $return = $driver->getItems(...$args);
            } 
            // May be the datastructure is faulty
            catch(\Exception $exc){
                $cacheDriver->ensureDataStructure($args[0]);
                $return = $driver->getItems(...$args);
            }
            
            return $return;
        }

        // If this entity is cached to another driver
        $skipCache = isset($otherOptions['$skipCache'])?''.$otherOptions['$skipCache']:'0';
        $skipCache = $skipCache == '1'?TRUE:FALSE;
        if ($entityBrowser->shouldCacheData() && ($this->getIdentifier() != $entityBrowser->getCachingDriverName()) && $skipCache == FALSE) {
            // Load the driver instead
            $cacheDriver = $this->loadDriver($entityBrowser->getCachingDriverName());
            
            // Return the results from the cache driver.
            $args = func_get_args();
            $args[0] = strtolower("{$this->getIdentifier()}__{$entityBrowser->getInternalName()}");
            $return = NULL;
            
            try {
                $return = $cacheDriver->getItems(...$args);
            } 
            // May be the datastructure is faulty
            catch(\Exception $exc){
                $cacheDriver->ensureDataStructure($args[0]);
                $this->syncFromDate($entityBrowser);
                $return = $cacheDriver->getItems(...$args);
            }
            return $return;
        }
        
        // If this entity's storage is delegated to another driver.
        if ($entityBrowser->delegatesStorage() && ($this->getIdentifier() != $entityBrowser->getDelegateDriverName())) {
            // Load the driver instead
            $delegateDriver = $this->loadDriver($entityBrowser->getDelegateDriverName());
            
            // Return the results from the cache driver.
            $args = func_get_args();
            $args[0] = strtolower("{$this->getIdentifier()}__{$entityBrowser->getInternalName()}");
            $return = $delegateDriver->getItems(...$args);
            return $return;
        }

        $scope = $this;
        $entityBrowser = $this->setStrategies($entityBrowser);
        
        $retryCount = isset($otherOptions['retryCount'])?$otherOptions['retryCount']:0;
        $otherOptions['retryCount'] = $retryCount + 1;

        // Take note of the preferred date format
        if(isset($otherOptions['$dateFormat'])){
            $this->preferredDateFormat = $otherOptions['$dateFormat'];
        }

        if(isset($otherOptions['$dateTimeFormat'])){
            $this->preferredDateTimeFormat = $otherOptions['$dateTimeFormat'];
        }
        
        // Set the default limit
        if (!isset($otherOptions['$top'])) {
            $otherOptions['$top'] = 100;
        } else {
            $top = intval($otherOptions['$top']);
            $otherOptions['$top'] = $top < 1 ? 100 : $top;
        }

        if (!isset($otherOptions['$pageNumber'])) {
            $otherOptions['$pageNumber'] = 1;
        } else {
            $pageNumber = intval($otherOptions['$pageNumber']);
            $otherOptions['$pageNumber'] = $pageNumber < 1 ? 1 : $pageNumber;
        }
        
        if (!isset($otherOptions['$skip'])) {
            $otherOptions['$skip'] = 0;
        } else {
            $skip = intval($otherOptions['$skip']);
            $otherOptions['$skip'] = $skip < 0 ? 0 : $skip;
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
            foreach ($distinctEx as $dis) {
                $distinctRe[] = $entityBrowser->getFieldByDisplayName($dis)->getInternalName();
            }
            $otherOptions['$distinct'] = array_unique($distinctRe);
        }

        //TODO: Stop overriding $orderBy and implement code to rename fields.
        $otherOptions['$orderBy'] = "{$entityBrowser->getIdField()->getInternalName()} ASC";
        
        // Set the default filter
        $filter = trim(strlen(trim($filter)) < 1 ? $this->getDefaultFilter() : $filter);
        $includeDeleted = isset($otherOptions['$includeDeleted']) && ($otherOptions['$includeDeleted'] == '1')?TRUE:FALSE;

        // If not stated otherwise, try to exclude already deleted items.
        if($includeDeleted){
            if($isDeletedField = $entityBrowser->hasField('IsDeleted')) {
                $filter = strlen($filter) > 0 ? ' and IsDeleted eq $FALSE$':'IsDeleted eq $FALSE$';
            }
        }

        // Convert the select parameter into an array.
        $fields = is_array($fields) ? $fields : preg_split('@(?:\s*,\s*|^\s*|\s*$)@', $fields, null, PREG_SPLIT_NO_EMPTY);
        $driverScope = $this;

        // Cleanse the $select parameter
        $select = self::getCurrentSelections($entityBrowser, $fields);

        // Process the $filter statement
        $filterExpression = MiddlewareODataFilterProcessor::convert($entityBrowser, $filter, null, $this->getStringer());

        // Convert the $expand parameter into an array
        $expands = [];
        $expandeds = is_array($expandeds) ? $expandeds : preg_split('@(?:\s*,\s*|^\s*|\s*$)@', $expandeds, null, PREG_SPLIT_NO_EMPTY);

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
                $remoteEntityBrowser = isset($remoteDriver->entitiesByDisplayName[$fieldInfo->getRemoteEntityName()])? $remoteDriver->entitiesByDisplayName[$fieldInfo->getRemoteEntityName()]:null;

                // TODO: Review this later. Problem is because of cached entities
                if (!is_null($remoteEntityBrowser)) {
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
                } else {
                    throw new \Exception("Referenced entity '{$fieldInfo->getRemoteEntityName()}' in field '{$fieldInfo->getDisplayName()}' of '{$fieldInfo->getParent()->getDisplayName()}' could not be found in {$fieldInfo->getRemoteDriver()->getIdentifier()}.");
                    // var_dump('ULL REMOTE', $fieldInfo->getRemoteEntityName(), $fieldInfo->getDisplayName(), $fieldInfo->getParent()->getParent()->getIdentifier(), $fieldInfo->getRemoteDriver()->getIdentifier(), $fieldInfo->getParent()->getDisplayName());
                }
            } else {
                throw new \Exception("Field {$expand} can not be expanded.");
            }
        }

        // Fetch the data of matching records
        $select = array_unique($entityBrowser->getFieldInternalNames($select));
        $dateFields = $entityBrowser->getFieldsOfTypeByInternalName(['date', 'datetime'], $select);

        $result = $this->getItemsInternal($entityBrowser, $this->connectionToken, $select, EncoderDecoder::unescapeall("{$filterExpression}"), $expands, $otherOptions);

        if (!is_null($result)) {
            $select_map = $entityBrowser->getFieldsByInternalNames($select);
            array_walk($result, function (&$record) use ($entityBrowser, $select_map, &$expands, $dateFields) {
                $record = $entityBrowser->renameFields($record, $select_map);
                foreach ($dateFields as $dateField) {
                    $dateFieldName = $dateField->getDisplayName();

                    if (is_object($record) && !is_null($record) && !is_null($record->{$dateFieldName})) {
                        $dateVal =  $this->parseDateValue($record->{$dateFieldName});
                        $record->{$dateFieldName} = ( $dateField->isDateTime() ? $dateVal->format($this->preferredDateTimeFormat) : $dateVal->format($this->preferredDateFormat) );
                    } elseif (is_array($record)) {
                        foreach ($record as &$innerRecord) {
                            if (is_object($innerRecord) && !is_null($innerRecord) && !is_null($innerRecord->{$dateFieldName})) {
                                $dateVal =  $this->parseDateValue($innerRecord->{$dateFieldName});
                                $innerRecord->{$dateFieldName} = ( $dateField->isDateTime() ? $dateVal->format($this->preferredDateTimeFormat) : $dateVal->format($this->preferredDateFormat) );
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
            array_walk($expands, function (&$expand_val, $expand_key) use ($driverScope, $skipCache, $includeDeleted) {
                $localField = $expand_val['info'];
                $remoteField = $expand_val['remoteFieldInfo'];
                $remoteEntityBrowser = $remoteField->getParent();
                $remoteDriver = $remoteEntityBrowser->getParent();
                $otherOptions = ['$top' => 1000000000];

                // Propagate $skipCache parameter
                if($skipCache){
                    $otherOptions['$skipCache'] = '1';
                }

                if($includeDeleted){
                    $otherOptions['$includeDeleted'] = '1';
                }

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
            array_walk($result, function (&$record, $recordIndex) use ($entityBrowser, $expands) {
                // Prepare to fetch expanded data
                foreach ($expands as $expand_key => &$expand_val) {
                    $fieldInfo = &$expand_val['info'];
                    $record = $entityBrowser->joinExpansionToParent($recordIndex, $record, $fieldInfo, $expand_val['data']);
                }
            });
        } else {
            if ($retryCount < $this->maxRetries) {
                return $this->getItems($entityBrowser, $fields, $filter, $expandeds, $otherOptions) ;
            } else {
                throw new \Exception("Failed to get data from Entity {$entityBrowser->getDisplayName()}");
            }
        }
        
        return $result;
    }

    public function updateItem($entityBrowser, $id, \stdClass $object, array $otherOptions = [])
    {
        $entityBrowser = $this->getEntityBrowser($entityBrowser);        
        if (is_string($entityBrowser) || is_null($entityBrowser)) {
            throw new \Exception("Invalid entity '{$entityBrowser}' could not be found in {$this->getIdentifier()}");
        }

        // Handle method redirection
        if($entityBrowser->shouldRedirectUpdate()){
            $providerInfo = $entityBrowser->getUpdateProviderInfo();
            $driver = $this;
            if($providerInfo->driver != $this->getIdentifier()){
                $driver = $this->loadDriver($providerInfo->driver);
            }
            
            // Execute the update provider's update method.
            $args = func_get_args();
            $args[0] = $providerInfo->entity;
            $return = $driver->updateItem(...$args);
            return $return;
        }
        
        // Handle storage delegation
        if ($entityBrowser->delegatesStorage() && ($this->getIdentifier() != $entityBrowser->getDelegateDriverName())) {
            // Load the driver instead
            $delegateDriver = $this->loadDriver($entityBrowser->getDelegateDriverName());
            
            // Return the results from the cache driver.
            $args = func_get_args();
            $args[0] = strtolower("{$this->getIdentifier()}__{$entityBrowser->getInternalName()}");
            $return = $delegateDriver->updateItem(...$args);
            return $return;
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
        
        if($entityBrowser->shouldManageTimestamps()){
            $now = new \DateTime();
            if(property_exists($obj, 'Created')){
                unset($obj->Created);
            }
            $obj->Modified = $now->format('Y-m-d\TH:i:s');
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

        try {
            if ($this->updateItemInternal($entityBrowser, $this->connectionToken, $id, $obj, $otherOptions)) {
                // Try to write the update to the cache also
                try {
                    if ($entityBrowser->shouldCacheData() && ($this->getIdentifier() != $entityBrowser->getCachingDriverName())) {
                        // Load the driver instead
                        $cacheDriver = $this->loadDriver($entityBrowser->getCachingDriverName());
                        
                        // Refactor the arguments to target the cache.
                        $args = func_get_args();
                        $args[0] = strtolower("{$this->getIdentifier()}__{$entityBrowser->getInternalName()}");
                        $now = (new \DateTime())->format('Y-m-d');
                        try {
                            $cacheDriver->updateItem(...$args);
                            $this->syncFromDate($entityBrowser, $now);
                        } 
                        // May be the datastructure is faulty
                        catch(\Exception $exc){
                            $cacheDriver->ensureDataStructure($args[0]);
                            $cacheDriver->updateItem(...$args);
                            $this->syncFromDate($entityBrowser, $now);
                        }
                    }
                } 
                // Fail silently
                catch(\Exception $exp){}

                // Return the item that was just updated.
                return $this->getItemById($entityBrowser, $id, $otherOptions['$select'], $otherOptions['$expand'], $otherOptions);
            }
        } catch (\Exception $exc) {
            if ($retryCount < $this->maxRetries) {
                return $this->updateItem($entityBrowser, $id, $object, $otherOptions);
            } else {
                throw $exc;
            }
        }
    }

    public function createItem($entityBrowser, \stdClass $object, array $otherOptions = [])
    {
        $entityBrowser = $this->getEntityBrowser($entityBrowser);
        if (is_string($entityBrowser) || is_null($entityBrowser)) {
            throw new \Exception("Invalid entity '{$entityBrowser}' could not be found in {$this->getIdentifier()}");
        }        

        // Handle method redirection
        if($entityBrowser->shouldRedirectCreate()){
            $providerInfo = $entityBrowser->getCreateProviderInfo();
            $driver = $this;
            if($providerInfo->driver != $this->getIdentifier()) {
                $driver = $this->loadDriver($providerInfo->driver);
            }
            // Execute the provider's create method.
            $args = func_get_args();
            $args[0] = $providerInfo->entity;
            $return = $driver->createItem(...$args);
            return $return;
        }

        // Handle storage delegation
        if ($entityBrowser->delegatesStorage() && ($this->getIdentifier() != $entityBrowser->getDelegateDriverName())) {

            // Load the driver instead
            $delegateDriver = $this->loadDriver($entityBrowser->getDelegateDriverName());
            
            // Return the results from the cache driver.
            $args = func_get_args();
            $args[0] = strtolower("{$this->getIdentifier()}__{$entityBrowser->getInternalName()}");
            $args[2]['$setId'] = '1';
            $return = $delegateDriver->createItem(...$args);
            return $return;
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

        if($entityBrowser->shouldManageTimestamps()){
            $now = new \DateTime();
            $obj->Created = $now->format('Y-m-d\TH:i:s');
            $obj->Modified = $now->format('Y-m-d\TH:i:s');
        }

        // Prepare the selected fields for the return
        if (!isset($otherOptions['$select'])) {
            $otherOptions['$select'] = EntityFieldDefinition::getDisplayNames($setFields);
        } else {
            $abccd = is_string($otherOptions['$select']) ? explode(',', $otherOptions['$select']) : (is_array($otherOptions['$select']) ? $otherOptions['$select'] : []);
            $abccc = array_merge($abccd, EntityFieldDefinition::getDisplayNames($setFields));
            $otherOptions['$select'] = array_unique($abccc);
        }

        // Prepare the expanded fields for the returned value
        if (!isset($otherOptions['$expand'])) {
            $otherOptions['$expand'] = '';
        }

        // Invoke the internal create method.
        $res = $this->createItemInternal($entityBrowser, $this->connectionToken, $obj, $otherOptions);
        
        // Requery and return the created object.
        if (property_exists($res, 'd') && $res->success == true) {
            // Try to write the update to the cache also
            try {
                if ($entityBrowser->shouldCacheData() && ($this->getIdentifier() != $entityBrowser->getCachingDriverName())) {
                    // Load the driver instead
                    $cacheDriver = $this->loadDriver($entityBrowser->getCachingDriverName());
                    
                    // Refactor the arguments to target the cache.
                    $args = func_get_args();
                    $args[0] = strtolower("{$this->getIdentifier()}__{$entityBrowser->getInternalName()}");
                    $args[1]->Id = $res->d;
                    $args[2]['$setId'] = '1';
                    $now = (new \DateTime())->format('Y-m-d');
                    try {
                        $cacheDriver->createItem(...$args);
                        $this->syncFromDate($entityBrowser, $now);
                    } 
                    // May be the datastructure is faulty
                    catch(\Exception $exc){
                        $cacheDriver->ensureDataStructure($args[0]);
                        $cacheDriver->createItem(...$args);
                        $this->syncFromDate($entityBrowser, $now);
                    }
                }
            } 
            // Fail silently
            catch(\Exception $exp){}

            $return = $this->getItemById($entityBrowser, $res->d, $otherOptions['$select'], $otherOptions['$expand'], $otherOptions);
            return $return;
        } 
        
        // Otherwise, if something is wrong, retry
        else {
            if ($retryCount < $this->maxRetries) {
                return $this->createItem($entityBrowser, $object, $otherOptions);
            } else {
                throw new \Exception("Unable to create a new record in {$entityBrowser->getDisplayName()} of ".__CLASS__);
            }
        }
    }

    public function deleteItem($entityBrowser, $id, array $otherOptions = [], &$deleteCount = 0)
    {        
        $entityBrowser = $this->getEntityBrowser($entityBrowser);
        if (is_string($entityBrowser) || is_null($entityBrowser)) {
            throw new \Exception("Invalid entity '{$entityBrowser}' could not be found in {$this->getIdentifier()}");
        }
        
        // Handle method redirection
        if($entityBrowser->shouldRedirectDelete()){
            $providerInfo = $entityBrowser->getDeleteProviderInfo();
            $driver = $this;
            if($providerInfo->driver != $this->getIdentifier()){
                $driver = $this->loadDriver($providerInfo->driver);
            }
            // Execute the update provider's update method.
            $args = func_get_args();
            $args[0] = $providerInfo->entity;
            $return = $driver->deleteItem(...$args);
            return $return;
        }

        // Handle storage delegation
        if ($entityBrowser->delegatesStorage() && ($this->getIdentifier() != $entityBrowser->getDelegateDriverName())) {
            // Load the driver instead
            $delegateDriver = $this->loadDriver($entityBrowser->getDelegateDriverName());
            
            // Return the results from the cache driver.
            $args = func_get_args();
            $args[0] = strtolower("{$this->getIdentifier()}__{$entityBrowser->getInternalName()}");
            $return = $delegateDriver->deleteItem(...$args);
            return $return;
        }
        
        $entityBrowser = $this->setStrategies($entityBrowser);
        
        $retryCount = isset($otherOptions['retryCount'])?$otherOptions['retryCount']: 0;
        $otherOptions['retryCount'] = $retryCount + 1;

        try {
            $deleteResult = $this->deleteItemInternal($entityBrowser, $this->connectionToken, $id, $otherOptions);
            $select = isset($otherOptions['$select'])?$otherOptions['$select']:['Id','Created','Modified'];
            $filter = isset($otherOptions['$filter'])?$otherOptions['$filter']:'';
            $expand = isset($otherOptions['$expand'])?$otherOptions['$expand']:'';

            $deleteCount = $deleteResult->d;
            try {
                // $return = $this->getItems($entityBrowser, $select, $filter, $expand);
                $deleteResult = [];//$return;
                $deleteResult['deleteCount'] = $deleteCount;
            } catch (\Exception $ex) {
                $deleteResult = [];
            }         
            return $deleteResult;
        } catch (\Exception $exc) {
            if ($retryCount < $this->maxRetries) {
                return $this->deleteItem($entityBrowser, $id, $otherOptions);
            } else {
                throw $exc;
            }
        }
    }

    public function fetchFieldValues($record, $selected_field)
    {
        $value = EncoderDecoder::escapeinner($record->{$selected_field});
        return [$value];
    }

    public function addExpansionToRecord($entity, &$record, EntityFieldDefinition $fieldInfo, $vals)
    {
        $keyVal = $record->{$fieldInfo->getRelatedLocalFieldName()};

        if (is_array($vals)) {

            $results = isset($vals["{$keyVal}"]) ? $vals["{$keyVal}"] : ($fieldInfo->isMany() ? [] : NULL);
        } elseif ($vals instanceof MiddlewareComplexEntity) {
            $results = $vals->getByKey("{$keyVal}", $fieldInfo->isMany());
        } else {
            $results = null;
        }

        $record->{$fieldInfo->getDisplayName()} = $fieldInfo->isMany() ? ['results' => $results] : $results;

        return $record;
    }

    public function renameRecordFields($record, $selected_fields)
    {

        $r = new \stdClass();

        foreach ($selected_fields as $key => $field) {
            $displayName = $field->getDisplayName();
            if (is_object($record) && property_exists($record, $key)) {
                if (is_array($record->{$key})) {
                    if ($field->isArray()) {
                        $r->{$displayName} = $record->{$key};
                    } elseif (count($record->{$key}) > 0) {
                        $r->{$displayName} = $record->{$key}[0];
                    }
                } else {
                    $r->{$displayName} = $field->isInteger()?intval($record->{$key}):($field->isBoolean()?boolVal($record->{$key}):$record->{$key});
                }
            } elseif (is_array($record) && isset($record[$key])) {
                if (is_array($record[$key])) {
                    if ($field->isArray()) {
                        $r->{$displayName} = $record[$key];
                    } elseif (count($record[$key]) > 0) {
                        $r->{$displayName} = $record[$key][0];
                    }
                } else {
                    $r->{$displayName} = $field->isInteger()?intval($record[$key]):($field->isBoolean()?boolVal($record[$key]):$record[$key]);
                }
            } else {
                $r->{$displayName} = $field->isArray() ? [] : null;
            }
        }

        $record = $r;

        return $record;
    }

    public function reverseRenameRecordFields(EntityDefinitionBrowser $brower, \stdClass $record)
    {

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

    public function mergeRecordArray($data, $chunkResult, EntityFieldDefinition $localField, EntityFieldDefinition $remoteField = null)
    {
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
                        $r["{$remoteFieldValue}"] = null;
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

    public static function getCurrentSelections(EntityDefinitionBrowser $entityBrowser, array $fields)
    {

        // set the compulsary fields of the entity
        $required_fields = $entityBrowser->getMandatoryFieldNames();
        foreach ($required_fields as $required_field) {
            if (!in_array($required_field, $fields)) {
                $fields[] = $required_field;
            }
        }

        // remove complex or invalid fields
        $shorts = [];
        $fields = array_values(array_filter($fields, function (&$item) use (&$shorts) {
                    $shorthand = '/[\[]([\w\|\d]+)[\]]/i';
                    $matchs = [];
                    preg_match_all($shorthand, $item, $matchs, PREG_SET_ORDER);

            if (strpos($item, '/') > -1) {
                return false;
            } elseif (count($matchs) > 0) {
                foreach ($matchs as $mat) {
                    $ss = preg_split('@(?:\s*\|\s*|^\s*|\s*$)@', $mat[1], null, PREG_SPLIT_NO_EMPTY);
                    foreach ($ss as $s) {
                        if (!in_array($s, $shorts)) {
                            $shorts[] = $s;
                        }
                    }
                }
                return false;
            } else {
                return $item;
            }
        }));

        return array_merge($fields, $shorts);
    }

    public static function getCurrentExpansions(EntityDefinitionBrowser $entityBrowser, $field, $fields)
    {
        
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

    protected function getDefaultFilter()
    {
        return '';
    }

    private function setStrategies(EntityDefinitionBrowser $entityBrowser)
    {
        $scope = &$this;

        $entityBrowser->setRenameStrategy(function () use ($scope) {
            return $scope->renameRecordFields(...func_get_args());
        });

        $entityBrowser->setReverseRenameStrategy(function () use ($scope) {
            return $scope->reverseRenameRecordFields(...func_get_args());
        });

        $entityBrowser->setExpansionJoinStrategy(function () use ($scope) {            
            return $scope->addExpansionToRecord(...func_get_args());
        });

        $entityBrowser->setMergeExpansionChunksStrategy(function () use ($scope) {
                return $scope->mergeRecordArray(...func_get_args());
        });

        $entityBrowser->setFieldValueFetchStrategy(function () use ($scope) {
            return $scope->fetchFieldValues(...func_get_args());
        });

        return $entityBrowser;
    }
}

class MiddlewareEntity extends \stdClass
{

    public function __construct(\stdClass $val = null)
    {
        if (!is_null($val)) {
            foreach ($val as $k => $v) {
                $this->{$k} = $v;
            }
        }
    }
}

class MiddlewareComplexEntity extends MiddlewareEntity
{
    public function getByKey($key, $isMany = false)
    {
        return $isMany?[]:null;
    }
}

class MiddlewareEntityCollection extends \RecursiveArrayIterator
{
    
}
