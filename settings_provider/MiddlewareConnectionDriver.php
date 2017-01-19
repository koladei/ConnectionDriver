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

    public abstract function getItemsInternal($entityBrowser, &$connection_token = NULL, array $select, $filter, $expands = [], $otherOptions = []);

    public abstract function updateItemInternal($entityBrowser, &$connectionToken = NULL, $id, \stdClass $object, array $otherOptions = []);

    public abstract function createItemInternal($entityBrowser, &$connectionToken = NULL, \stdClass $object, array $otherOptions = []);

    public abstract function deleteItemInternal($entityBrowser, &$connectionToken = NULL, $id, array $otherOptions = []);

    public abstract function getStringer();

    public function __construct(callable $driverLoader) {
        $this->driverLoader = $driverLoader;
    }

    public function setEntities($entities) {

        foreach ($entities as $entity_name => $entity) {
            $entityDef = new EntityDefinitionBrowser($entity_name, $entity, $this);
            $this->entitiesByInternalName[$entity['internal_name']] = $entityDef;
            $this->entitiesByDisplayName[$entityDef->getDisplayName()] = &$this->entitiesByInternalName[$entity['internal_name']]; //$entityDef;//&$this->entitiesByInternalName[$entity['internal_name']];
        }

        return $this;
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

    public function getItemById($entityBrowser, $id, $select, $expands = '', $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByDisplayName[$entityBrowser];
        $result = $this->getItemsByIds($entityBrowser, [$id], $select, $expands, $otherOptions);

        return count($result) > 0 ? $result[0] : NULL;
    }

    public function getItemsByIds($entityBrowser, $ids, $select, $expands = '', $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByDisplayName[$entityBrowser];

        $result = $this->getItemsByFieldValues($entityBrowser, $entityBrowser->getIdField(), $ids, $select, $expands, $otherOptions);
        return $result;
    }

    public function getItemsByFieldValues($entityBrowser, EntityFieldDefinition $entityField, array $values, $select, $expands = '', $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByDisplayName[$entityBrowser];

        // implode the values based on the type of the field
        $implosion = '';
        $type = $entityField->getDataType();
        switch ($type) {
            case 'int': {
                    $implosion = implode(',', $values);
                    break;
                }
            default: {
                    $implosion = implode('\',\'', $values);
                    $implosion = "'{$implosion}'";
                }
        }

        $additionalFilter = isset($otherOptions['more_filter']) ? "({$otherOptions['more_filter']}) and " : '';
        $result = $this->getItems($entityBrowser, $select, "{$additionalFilter}{$entityField->getDisplayName()} IN({$implosion})", $expands);
        return $result;
    }

    public function updateItem($entityBrowser, $id, \stdClass $object, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByDisplayName[$entityBrowser];
        $entityBrowser = $this->setStrategies($entityBrowser);

        $object = $entityBrowser->reverseRenameFields($object);
        return $this->updateItemInternal($entityBrowser, $this->connectionToken, $id, $object, $otherOptions);
    }

    public function createItem($entityBrowser, \stdClass $object, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByDisplayName[$entityBrowser];
        $entityBrowser = $this->setStrategies($entityBrowser);

        $object = $entityBrowser->reverseRenameFields($object);
        return $this->createItemInternal($entityBrowser, $this->connectionToken, $object, $otherOptions);
    }

    public function deleteItem($entityBrowser, $id, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByDisplayName[$entityBrowser];
        $entityBrowser = $this->setStrategies($entityBrowser);

        $result = $this->createItemInternal($entityBrowser, $this->connectionToken, $id, $otherOptions);
    }

    public function getItems($entityBrowser, $fields, $filter, $expandeds = '', $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByDisplayName[$entityBrowser];
        $scope = $this;
        $entityBrowser = $this->setStrategies($entityBrowser);

        // Set the default limit        
        if (!isset($otherOptions['$top'])) {
            $otherOptions['$top'] = 100;
        }

        // Set the default filter
        $filter = strlen(trim($filter)) < 1 ? $this->getDefaultFilter() : $filter;

        // Convert the select parameter into an array.
        $fields = is_array($fields) ? $fields : preg_split('@(?:\s*,\s*|^\s*|\s*$)@', $fields, NULL, PREG_SPLIT_NO_EMPTY);
        $driverScope = $this;

        // Cleanse the $select parameter
        $select = self::getCurrentSelections($entityBrowser, $fields);

        // Process the $filter statement
        $filterExpression = MiddlewareODataFilterProcessor::convert($entityBrowser, $filter, $this->getStringer());

        // Convert the $expand parameter into an array
        $expands = [];
        $expandeds = is_array($expandeds) ? $expandeds : preg_split('@(?:\s*,\s*|^\s*|\s*$)@', $expandeds, NULL, PREG_SPLIT_NO_EMPTY);

        foreach ($expandeds as $expand) {
            if (($pos = strpos($expand, '/')) > 0) {
                $key = substr($expand, 0, $pos);
                $val = substr($expand, $pos + 1);
                var_dump($key, $val);
                $ex0 = isset($expands[$expand]) ? $expands[$expand] : ['expand' => []];
                $ex1 = array_merge(['select' => $expandX, 'ids' => [], 'info' => $fieldInfo, 'remoteFieldInfo' => $remoteField, 'data' => []], $ex0);
                if (!in_array($ex1['expand'], $key)) {
                    $ex1['expand'][$key] = [];
                }

                if (!in_array($ex1['expand'][$key], $val)) {
                    $ex1['expand'][$key][] = $val;
                }

                $expands[$expand] = $ex1;
            } else {

                $fieldInfo = $entityBrowser->getFieldByDisplayName($expand);

                //check if this field can be expaanded
                if ($fieldInfo->isExpandable()) {
                    // Get a reference to the remote driver
                    $remoteDriver = $fieldInfo->getRemoteDriver();
                    $remoteEntityBrowser = $remoteDriver->entitiesByDisplayName[$fieldInfo->getRemoteEntityName()];
                    $remoteField = $remoteEntityBrowser->getFieldByDisplayName($fieldInfo->getRelatedForeignFieldName());

                    // Get the selected subfields of this expanded field
                    $expandX = self::getCurrentExpansions($entityBrowser, $expand, $fields);

                    // Ensure that the lookup of the remote entity is included in the remote entity's selection
                    if (!in_array($remoteField->getDisplayName(), $expandX)) {
                        $expandX2 = $expandX;
                        $expandX[] = $remoteField->getDisplayName();
                    }

                    $ex0 = isset($expands[$expand]) ? $expands[$expand] : [];
                    $ex1 = array_merge(['select' => $expandX, 'ids' => [], 'info' => $fieldInfo, 'remoteFieldInfo' => $remoteField, 'data' => [], 'expand' => []], $ex0);
                    $expands[$expand] = $ex1;

                    // Ensure the field this expansion depends on is selected.
                    $localFieldName = $fieldInfo->getRelatedLocalFieldName();
                    if (!in_array($localFieldName, $select)) {
                        $select[] = $localFieldName;
                    }
                } else {
                    throw new \Exception("Field {$expand} can not be expanded.");
                }
            }
        }

        // Fetch the data of matching records
        $select = array_unique($entityBrowser->getFieldInternalNames($select));

        $result = $this->getItemsInternal($entityBrowser, $this->connectionToken, $select, "{$filterExpression}", $expands, $otherOptions);

        if (!is_null($result)) {
            $select_map = $entityBrowser->getFieldsByInternalNames($select);
            array_walk($result, function(&$record) use($entityBrowser, $select_map, &$expands) {
                $record = $entityBrowser->renameFields($record, $select_map);

                // Prepare to fetch expanded data
                foreach ($expands as $expand_key => &$expand_val) {
                    $fieldInfo = $expand_val['info'];
                    $relatedKey = $fieldInfo->getRelatedLocalFieldName();
                    $ids = $entityBrowser->fetchFieldValues($record, $relatedKey);
                    $expand_val['ids'] = array_merge_recursive($expand_val['ids'], $ids);
                }
            });

            // Fetch the related field values in one sweep
            array_walk($expands, function(&$expand_val, $expand_key) use($driverScope) {
                $localField = $expand_val['info'];
                $remoteField = $expand_val['remoteFieldInfo'];
                $remoteEntityBrowser = $remoteField->getParent();
                $remoteDriver = $remoteEntityBrowser->getParent();
                $otherOptions = ['$top' => 2000];

                if (!is_null($localField->getRemoteEntityFilter())) {
                    $otherOptions['more_filter'] = $localField->getRemoteEntityFilter();
                }

                // Remove duplicates
                $expand_val['ids'] = array_unique($expand_val['ids']);

                // Divide the keys into manageable chunks
                $expand_chunks = array_chunk($expand_val['ids'], 50);
                $data = NULL;

                var_dump($expand_val['expand'], $localField->getDisplayName());
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
            return [];
        }

        return $result;
    }

    public function fetchFieldValues($record, $selected_field) {
        return [$record->{$selected_field}];
    }

    public function addExpansionToRecord($entity, &$record, EntityFieldDefinition $fieldInfo, $vals) {

        $keyVal = $record->{$fieldInfo->getRelatedLocalFieldName()};
        $results = isset($vals["{$keyVal}"]) ? $vals["{$keyVal}"] : ($fieldInfo->isMany() ? [] : NULL);
        $record->{$fieldInfo->getDisplayName()} = $fieldInfo->isMany() ? ['results' => $results] : $results;
//        unset($record->{$fieldInfo->getRelatedLocalFieldName()});

        return $record;
    }

    public function renameRecordFields($record, $selected_fields) {

        $r = new \stdClass();

        foreach ($selected_fields as $key => $field) {
            $displayName = $field->getDisplayName();
            if (property_exists($record, $key)) {
                if (is_array($record->{$key})) {
                    if ($field->isArray()) {
                        $r->{$displayName} = $record->{$key};
                    } else if (count($record->{$key}) > 0) {
                        $r->{$displayName} = $record->{$key}[0];
                    }
                } else {
                    $r->{$displayName} = $record->{$key};
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
            $r->{$internalName} = $record->{$key};
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
                    $remoteFieldName = $remoteField->getDisplayName();

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
        $fields = array_values(array_filter($fields, function(&$item) {
                    if (strpos($item, '/')) {
                        return FALSE;
                    }

                    return $item;
                }));

        return $fields;
    }

    public static function getCurrentExpansions(EntityDefinitionBrowser $entityBrowser, $field, $fields) {
        $regex = "/({$field})\/([^\/]+)[^\w]*/";
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
    
}

class MiddlewareEntityCollection extends \RecursiveArrayIterator {
    
}
