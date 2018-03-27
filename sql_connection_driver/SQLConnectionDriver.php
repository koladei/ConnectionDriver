<?php

namespace com\mainone\middleware;

use com\mainone\middleware\MiddlewareConnectionDriver;
use com\mainone\middleware\MiddlewareFilter;
use com\mainone\middleware\EntityDefinitionBrowser;
use \PDO;

/**
 * Description of SQLConnectionDriver
 *
 * @author Kolade.Ige
 */
class SQLConnectionDriver extends MiddlewareConnectionDriver {

    /**
     * Instantiates and returns an instance of a SQLConnectionDriver.
     *
     * @param callable $driverLoader A callable reference that can be used to retrieve data that can be found in other connnection driver instances.
     * @param callable $sourceLoader A callable reference that can be used to load data from various named connections within the current driver.
     */
    public function __construct(callable $driverLoader, callable $sourceLoader, $identifier = __CLASS__) {
        parent::__construct($driverLoader, $sourceLoader, $identifier);
    }

    public function ensureDataStructureInternal($entityBrowser, &$connectionToken = null, array $otherOptions = []){
        $source = $entityBrowser->getDataSourceName();
        
        // Get a connection token
        if($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken($source))){
            
            try {
                $pdo = new \PDO($connectionToken->DSN, $connectionToken->Username, $connectionToken->Password);
                $pdo->exec('SET CHARACTER SET utf8');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $createSQL = '';
                $constraint = '';
                $fields = $entityBrowser->getFieldsByInternalNames();
                foreach($fields as $name => $field){
                    $c = [];
                    preg_match('/([\w\d\_]+)_\$LOOKUP\$/', $name, $c);
                    if(count($c)){
                        continue;
                    }
                    $nullable = 'NULL';

                    if($field->getInternalName() == $entityBrowser->getIdField()->getInternalName()){
                        $nullable = 'NOT NULL';
                        $constraint = "CONSTRAINT {$entityBrowser->getInternalName()}_{$field->getInternalName()} UNIQUE({$field->getInternalName()})";
                    }

                    switch($field->getDataType()){
                        case 'string': {
                            $createSQL = "{$createSQL}, {$name} varchar({$field->getFieldDescription()}) {$nullable}";
                            break;
                        }
                        case 'date':
                        case 'datetime':{
                            $createSQL = "{$createSQL}, {$name} datetime2(7) {$nullable}";
                            break;
                        }
                        case 'int':{
                            $createSQL = "{$createSQL}, {$name} int {$nullable}";
                            break;
                        }
                        case 'bigint':{
                            $createSQL = "{$createSQL}, {$name} bigint {$nullable}";
                            break;
                        }
                        case 'decimal':{
                            $createSQL = "{$createSQL}, {$name} decimal({$field->getFieldDescription()}) {$nullable}";
                            break;
                        }
                        case 'boolean':{
                            $createSQL = "{$createSQL}, {$name} bit {$nullable}";
                            break;
                        }
                    }
                }

                $createSQL = trim($createSQL, ', ');
                $constraint = strlen($constraint) > 0?", {$constraint}":'';

                $createSQL = "
                IF NOT EXISTS (select * from sysobjects where name='{$entityBrowser->getInternalName()}' and xtype='U')
                CREATE TABLE {$entityBrowser->getInternalName()} (
                    {$createSQL}{$constraint}
                );";


                $statement = $pdo->exec($createSQL);

                // Query for the columns of the table
                $stmt = $pdo->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='{$entityBrowser->getInternalName()}'");
                $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $re = [];
                foreach(array_values($rs) as $column) {
                    // Remove columns that have been removed
                    if(!in_array($column['COLUMN_NAME'], array_keys($fields))){
                        $pdo->exec("ALTER TABLE {$entityBrowser->getInternalName()} DROP COLUMN {$column['COLUMN_NAME']}");
                    } 
                    
                    else {
                        $re[$column['COLUMN_NAME']] = [];
                        switch( $column['DATA_TYPE']) {
                            case 'varchar':{
                                $re[$column['COLUMN_NAME']]['type'] = 'string';
                                $re[$column['COLUMN_NAME']]['type_description'] = $column['CHARACTER_MAXIMUM_LENGTH'];
                                break;
                            }
                            case 'bit':{
                                $re[$column['COLUMN_NAME']]['type'] = 'boolean';
                                break;
                            }
                            case 'int':{
                                $re[$column['COLUMN_NAME']]['type'] = 'int';
                                // $re[$column['COLUMN_NAME']]['type_description'] = "{$column['NUMERIC_PRECISION']},{$column['NUMERIC_SCALE']}";
                                break;
                            }
                            case 'bigint':{
                                $re[$column['COLUMN_NAME']]['type'] = 'bigint';
                                // $re[$column['COLUMN_NAME']]['type_description'] = "{$column['NUMERIC_PRECISION']},{$column['NUMERIC_SCALE']}";
                                break;
                            }
                            case 'decimal':{
                                $re[$column['COLUMN_NAME']]['type'] = 'decimal';
                                $re[$column['COLUMN_NAME']]['type_description'] = "{$column['NUMERIC_PRECISION']},{$column['NUMERIC_SCALE']}";
                                break;
                            }
                            case 'datetime2':{
                                $re[$column['COLUMN_NAME']]['type'] = 'datetime';
                                $re[$column['COLUMN_NAME']]['type_description'] = "{$column['NUMERIC_PRECISION']},{$column['NUMERIC_SCALE']}";
                                break;
                            }
                            default:{
                                $re[$column['COLUMN_NAME']]['type'] = $column['DATA_TYPE'];
                                $re[$column['COLUMN_NAME']]['type_description'] = '255';
                            }
                        }
                    }
                }

                $modifiedAColumn = FALSE;

                // Add new columns
                foreach($fields as $name => $field) {
                    $c = [];

                    // Skip lookup columns
                    preg_match('/([\w\d\_]+)_\$LOOKUP\$/', $name, $c);
                    if(count($c)){
                        continue;
                    }

                    // Skip columns whose data type has not changed.
                    if(
                        in_array($name, array_keys($re)) && 
                        (
                            $re[$name]['type'] == $field->getDataType() || 
                            ($re[$name]['type'] == 'datetime' && $field->getDataType() == 'date')
                        )
                    ) {
                        continue;
                    } 
                    
                    // Drop the columns that have changed
                    else {
                        if(in_array($name, array_keys($re))){
                            $pdo->exec("ALTER TABLE {$entityBrowser->getInternalName()} DROP COLUMN {$name}");
                            $modifiedAColumn = TRUE;
                        }
                    };

                    $nullable = 'NULL';

                    switch($field->getDataType()) {
                        case 'string': {
                            $pdo->exec("ALTER TABLE {$entityBrowser->getInternalName()} ADD {$name} varchar({$field->getFieldDescription()}) {$nullable}");
                            break;
                        }
                        case 'date':
                        case 'datetime':{
                            $pdo->exec("ALTER TABLE {$entityBrowser->getInternalName()} ADD {$name} datetime2(7) {$nullable}");
                            break;
                        }
                        case 'int':{
                            $pdo->exec("ALTER TABLE {$entityBrowser->getInternalName()} ADD {$name} int {$nullable}");
                            break;
                        }
                        case 'bigint':{
                            $pdo->exec("ALTER TABLE {$entityBrowser->getInternalName()} ADD {$name} bigint {$nullable}");
                            break;
                        }
                        case 'decimal':{
                            $pdo->exec("ALTER TABLE {$entityBrowser->getInternalName()} ADD {$name} decimal({$field->getFieldDescription()}) {$nullable}");
                            break;
                        }
                        case 'boolean':{
                            $pdo->exec("ALTER TABLE {$entityBrowser->getInternalName()} ADD {$name} bit {$nullable}");
                            break;
                        }
                    }    
                }

                // If a column changed, mark all records as due for update
                if($modifiedAColumn == TRUE){
                    try {
                        if($isUpdated = $entityBrowser->hasField('_IsUpdated')){
                            // $pdo->exec("UPDATE TABLE {$entityBrowser->getInternalName()} SET _IsUpdated = 0");
                            $filter = '';
                            if($isDeleted = $entityBrowser->hasField('IsDeleted')){
                                $filter = " WHERE {$isDeleted->getInternalName()} = :IsDeleted";
                            }

                            $statement = $pdo->prepare("UPDATE TABLE {$entityBrowser->getInternalName()} SET {$isUpdated->getInternalName()} = :IsUpdated {$filter}");
                            $statement->bindValue(':IsUpdated', FALSE);
                            $statement->bindValue(':IsDeleted', FALSE);
                            $statement->execute();
                        }
                    } catch(\Exception $exp){}
                }
                
                return $re;

            } catch (\Exception $e) {
                throw new \Exception('Connection failed: ' . $e->getMessage());
            }       

            // Get the resulting data afresh
            $selectFields = array_keys(get_object_vars($object));

            return $this->getItemById($entityBrowser, $id, $selectFields);
        } else {
            throw new \Exception('The connection datasource settings could not be retrieved, contact the administrator.');
        }
    }

    /**
     * @override
     * Overrides the default implementation.
     *
     * @param \DateTime $value
     * @return void
     */
    protected function parseDateValue($value) {
        $type_1 = '/^([\d]{4})\-([\d]{2})\-([\d]{2})[\s]{1,}([\d]{2})\:([\d]{2})\:([\d]{2})\.([\d]+)$/';
        $type_3 = '/^([\d]{4})\\-([\d]{2})\-([\d]{2})$/';
        //2016-04-13 17:30:54.9300000

        if (preg_match($type_3, $value) == 1) {
            //$value = substr($value, 0, strpos($value, '.'));
            return \DateTime::createFromFormat('Y-m-d', $value);
        } else if (preg_match($type_1, $value) == 1) {
            $value = substr($value, 0, strpos($value, '.'));
            return \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        }

        throw new \Exception("The date / datetime format is not known. {$value}");
    }
    
    public function updateItemInternal($entityBrowser, &$connectionToken = NULL, $id, \stdClass $object, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];
        $source = $entityBrowser->getDataSourceName();
        
        // Get a connection token
        if($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken($source))){
            $sets = '';
            // Remove the Id field if present            
            if(property_exists($object, 'Id')) {
                unset($object->Id);
            }   

            // Convert the object to an array
            $obj = get_object_vars($object);

            // Get the fields to be updated.
            $updatedFieldNames = array_keys($obj);
            $idField = $entityBrowser->getIdField();
            $updatedFields = $entityBrowser->getFieldsByDisplayNames($updatedFieldNames);

            // Prepare the set commands
            $counter = 0;
            $count = count($updatedFieldNames);
            $sql = "";
            try {
                $pdo = new \PDO($connectionToken->DSN, $connectionToken->Username, $connectionToken->Password);
                $pdo->exec('SET CHARACTER SET utf8');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                foreach($updatedFields as $updatedField) {
                    $comma = $counter < ($count - 1) ? ',' : '';
                    $sets = "{$sets} {$updatedField->getInternalName()}=:{$updatedField->getInternalName()}{$comma}";
                    $counter = $counter + 1;
                }

                // Execute the Update request.
                $sql = "UPDATE {$entityBrowser->getInternalName()} SET {$sets} WHERE {$idField->getInternalName()} = :{$idField->getInternalName()}";
                $statement = $pdo->prepare($sql);

                foreach($updatedFields as $updatedField) {                    
                    $val = isset($obj["{$updatedField->getDisplayName()}"])?$obj["{$updatedField->getDisplayName()}"]:NULL;
                    
                    if(is_null($val)){
                        $val = NULL;
                    } 
                    
                    else if($updatedField->isDate()) {
                        $val = $updatedField->getValue($val)->format('Y-m-d');
                        $val = "{$val}";
                    } 
                    
                    else if($updatedField->isDateTime()) {      
                        $val = $updatedField->getValue($val)->format('Y-m-d H:i:s');
                        $val = "{$val}";
                    }
                    
                    $statement->bindValue(":{$updatedField->getInternalName()}", $val);
                }
                $statement->bindValue(":{$idField->getInternalName()}", $id);

                // Execute the update
                $statement->execute();
            } catch (\Exception $e) {
                throw new \Exception("STATEMENT: $sql ERROR:" . $e->getMessage());
            }            

            // Get the resulting data afresh
            $selectFields = array_keys(get_object_vars($object));

            return $id;
        }  else {
            throw new \Exception('The connection datasource settings could not be retrieved, contact the administrator.');
        }
    }

    /**
     * Implements MiddlewareConnectionDriver.createItemInternal
     *
     * @param EntityDefinitionBrowser $entityBrowser
     * @param \stdClass $connectionToken
     * @param \stdClass $obj 
     * @param array $otherOptions
     * @return void
     */
    public function createItemInternal($entityBrowser, &$connectionToken = NULL, \stdClass $obj, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];
        $source = $entityBrowser->getDataSourceName();
        
        // Get a connection token
        if($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken($source))){
            $sets = [];
            $xets = [];
            $xetz = [];
            $createId = isset($otherOptions['$setId'])?"{$otherOptions['$setId']}":'0';
            $createId = $createId == '1'?TRUE:FALSE;
            $id = \property_exists($obj, 'Id')?$obj->Id:NULL;

            if($createId == TRUE && !property_exists($obj, 'Id')){
                throw new \Exception('The request states that the \'Id\' field should be set but does not provide it');
            }

            // Remove the Id field if present            
            else if ($createId == FALSE && property_exists($obj, 'Id')) {
                unset($obj->Id);
            }

            // Convert the object to an array
            $obj = $entityBrowser->reverseRenameFields($obj); 
            $obj = get_object_vars($obj);

            // Get the fields to be updated.
            $updatedFieldNames = array_keys($obj);

            $idField = $entityBrowser->getIdField();
            $updatedFields = $entityBrowser->getFieldsByInternalNames($updatedFieldNames);
            $sql = "";

            // Prepare the query
            try {
                
                $pdo = new \PDO($connectionToken->DSN, $connectionToken->Username, $connectionToken->Password);
                $pdo->exec('SET CHARACTER SET utf8');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                foreach($updatedFields as $updatedField) {
                    $sets[] = $updatedField->getInternalName();
                    $xetz[] = ":{$updatedField->getInternalName()}";
                }

                // Prepare the statement.
                $sets = implode(',', $sets);
                $xets = implode(',', $xetz);

                $sql = str_replace("\\'", "''", "INSERT INTO {$entityBrowser->getInternalName()}({$sets}) VALUES({$xets})");
                $statement = $pdo->prepare($sql);

                // Bind values to the statement
                foreach($updatedFields as $updatedField) {                    
                    $val = isset($obj["{$updatedField->getInternalName()}"])?$obj["{$updatedField->getInternalName()}"]:NULL;
                    
                    if(is_null($val)){
                        $val = NULL;
                    } 
                    
                    elseif($updatedField->isDate()) {
                        $val = $updatedField->getValue($val)->format('Y-m-d');
                        $val = "{$val}";
                    } 
                    
                    else if($updatedField->isDateTime()) {      
                        $val = $updatedField->getValue($val)->format('Y-m-d H:i:s');
                        $val = "{$val}";
                    }

                    $statement->bindValue(":{$updatedField->getInternalName()}", $val);
                }

                try {
                    // Execute the statement
                    $retu = $statement->execute();
                    
                    $d = new \stdClass();
                    $d->d = ($createId == TRUE) ? $id : $pdo->lastInsertId();
                    $d->success = TRUE;
                    return $d;
                }  catch (\Exception $e) {
                    $info = print_r($statement->errorInfo(), true);
                    throw new \Exception("CREATE STATEMENT 1: {$sql} ERROR: {$e->getMessage()} | {$info}");
                }

            } catch (\Exception $e) {
                throw new \Exception("CREATE STATEMENT 2: {$sql} ERROR: {$e->getMessage()}");
            }

        }  else {
            throw new \Exception('The connection datasource settings could not be retrieved, contact the administrator.');
        }
    }

    public function deleteItemInternal($entityBrowser, &$connectionToken = NULL, $id, array $otherOptions = []) {
        $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];
        $source = $entityBrowser->getDataSourceName();
        
        // Get a connection token
        if($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken($source))){
            
            // Get the Id field
            $idField = $entityBrowser->getIdField();

            // Prepare the query
            try {
                // Connect to the database
                $pdo = new \PDO($connectionToken->DSN, $connectionToken->Username, $connectionToken->Password);
                $pdo->exec('SET CHARACTER SET utf8');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Execute the Update request.
                $sql = "DELETE FROM {$entityBrowser->getInternalName()} WHERE {$idField->getInternalName()} = :{$idField->getInternalName()}";
                $statement = $pdo->prepare($sql);
                $statement->bindValue(":{$idField->getInternalName()}", $id);
                $statement->execute();
                 
                $d = new \stdClass();
                $d->d = $statement->rowCount();
                $d->success = TRUE;
                return $d;

            } catch (\Exception $e) {
                throw new \Exception('Connection failed: ' . $e->getMessage());
            }

        }  else {
            throw new \Exception('The connection datasource settings could not be retrieved, contact the administrator.');
        }
    }

    /**
     * Implements the get items operation.
     * @param type $entityBrowser the specific entity whose record is to be retrieved.
     * @param type $connectionToken an existing token object that can be used in this operation.
     * @param array $select An array of fields to be returned by this query
     * @param array $filter The filter expression to be passed to the remote server.
     * @param array $expands The fields to be expanded to get.
     * @param array $otherOptions An array of other additional options.
     * @return array An array of the retrieved values.
     * @throws \Exception when something goes wrong.
     */
    public function getItemsInternal($entityBrowser, &$connectionToken = NULL, array $select, $filter, $expands = [], $otherOptions = []) {
        // $entityBrowser = ($entityBrowser instanceof EntityDefinitionBrowser) ? $entityBrowser : $this->entitiesByInternalName[$entityBrowser];
        $source = $entityBrowser->getDataSourceName();

        if($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken($source))){
            // Set defaults
            $top = $otherOptions['$top'];
            $skip = $otherOptions['$skip'];
            $pageNumber = $otherOptions['$pageNumber'];
            $pageSize = $otherOptions['$pageSize'];
            $orderBy = $otherOptions['$orderBy'];
            $all = isset($otherOptions['$all']) && ''.$otherOptions['$all'] = '1'?TRUE:FALSE;

            // Remove distinct fields from select
            $distinct = $otherOptions['$distinct'];
            $sel = [];
            foreach($select as $dis){
                if(!in_array($dis, $distinct)){
                    $sel[] = $dis;
                }
            }
            $select2 = implode(',', $select);

            // $distinct = implode(',', $otherOptions['$distinct']);
            $occurence = '';
            if (count($distinct) < 1) {
                $distinct = 'ID';
            } else {
                $distinct = implode(',', $otherOptions['$distinct']);
                $distinct = "{$distinct}";
                $occurence = ' WHERE OCCURENCE = 1 ';
            }

            // Determin the record to start from based on the $pageSize and $pageNumber;
            $start = ($pageSize * ($pageNumber - 1)) + 1 + $skip;
            $end = $start + $pageSize;

            // Generate the SQL query to send in the POST request   
            $where = (strlen($filter) > 0 ? "  WHERE {$filter} " : '');         
            $sel = implode(',', $sel);
            
            $query_url = "
                WITH DEDUPE AS (
                    SELECT {$select2}, ROW_NUMBER() OVER (PARTITION BY {$distinct} ORDER BY {$distinct}) AS OCCURENCE
                    FROM {$entityBrowser->getInternalName()}
                    {$where}
                )

                SELECT  {$select2}
                FROM    ( SELECT  ROW_NUMBER() OVER ( ORDER BY {$orderBy} ) AS RowNum, {$select2}
                        FROM DEDUPE {$occurence}
                        ) AS RowConstrainedResult
                WHERE   RowNum >= {$start}
                        AND RowNum < {$end}
                ORDER BY RowNum
            ";

            if($all){
                $query_url = "SELECT {$select2} FROM {$entityBrowser->getInternalName()} {$where} ORDER BY {$orderBy}";
            }

            $query_url = str_replace("\\'", "''", $query_url);
            try {
                $pdo = new \PDO($connectionToken->DSN, $connectionToken->Username, $connectionToken->Password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $pdo->query($query_url);
                $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                return array_values($rs);
            } catch (\Exception $e) {
                throw new \Exception('Connection failed: ' . $e->getMessage());
            }
        } else {
            throw new \Exception('The connection datasource settings could not be retrieved, contact the administrator.');
        }   
    }

    public function getStringer() {
        return MiddlewareFilter::SQL;
    }

    /**
     * Returns a connection token to aid communication with the datasource.
     * @return boolean
     */
    private function getConnectionToken($sourceName) {
        try {
            $sourceLoader = $this->sourceLoader;
            $settings = $sourceLoader($sourceName);

            return $settings;            
        } catch (Exception $x) {
            return FALSE;
        }
    }   

    // public function fetchFieldValues($record, $selected_field)
    // {
    //     return parent::fetchFieldValues($record->{$selected_field}, ['(', ')', "'"], ['_y0028_','_y0029_', '_y0027_'], true);
    // }
}

class SQLEntity extends MiddlewareEntity {
    
}

class SQLComplexEntity extends MiddlewareComplexEntity {
    
}

class SQLEntityCollection extends MiddlewareEntityCollection {
    
}
