<?php

function settings_provider__update_cache($sourceDestination = NULL, $entityId = NULL, $start = NULL, $idsToSync = [])
{
    $start = (!is_null($start) && strlen($start) === 10)?"{$start}T00:00:00":$start;
    $entityId = (!is_null($entityId) && strtolower($entityId ) == '$null$')?NULL:$entityId;

    // Get all entities that are exportable.
    $cached_all = module_invoke_all('_get_cached_entity_definitions', 'sql');
    
    foreach ($cached_all as $sourceDestination => $cached) {
        list($from, $to) = explode('|', $sourceDestination);

        // Get references to the source and destination data sources
        $source = mware_connection_driver__get_driver($from);
        $destination = mware_connection_driver__get_driver($to);
        $sql = mware_connection_driver__get_driver('sql');

        if ($source && $destination && $sql) {
            foreach ($cached as $entity) {                
                if(!is_null($entityId) && $entity['original_display_name'] != $entityId){
                    continue;
                }

                settings_provider__update_cache_inner($sourceDestination, $entity, $source, $destination, $sql, $start, $idsToSync);
            }
        } else {
            watchdog('UPDATING CACHE ERROR', 'One or more sources could not be loaded.');
        }
    }
}

function settings_provider__update_cache_inner($sourceDestination, $entity, $source, $destination, $sql, $start = NULL, $idsToSync = [])
{
    // var_dump($idsToSync);
    // echo $source->getDisplayName();
    if ($source && $destination && $sql) {
            $idsToSync = is_string($idsToSync) ? explode(',', $idsToSync) : $idsToSync;
            // Get the field names to select
            $selectFields = [];

            // Check the cache frequecy of this entity and act accordingly.    
            $defaultSyncData = [
                'time' => '1998-01-01T00:00:00',
                'continue_from' => '1998-01-01T00:00:00',
                'failure_count' => 0
            ]; 

            $lastSyncInfo = variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", $defaultSyncData);

            // For backward version upgrade.
            if(is_string($lastSyncInfo)){
                $lastSyncInfo = $defaultSyncData;
                variable_set("{$entity['original_display_name']}_LastSync__{$sourceDestination}", $lastSyncInfo);
            }
            $lastSyncInfo = array_merge($defaultSyncData, $lastSyncInfo);
            $bypass = FALSE;

            // In case this is an ad-hoc run
            if(!is_null($start)){
                $defaultSyncData['time'] = $start;
                $defaultSyncData['continue_from'] = $start;
                $defaultSyncData['failure_count'] = 0;
                $lastSyncInfo = $defaultSyncData;
            } else if (count($idsToSync) > 0){
                $bypass = TRUE;
            }

            $now = (new \DateTime());
            $lastSynched    = \DateTime::createFromFormat('Y-m-d\TH:i:s', $lastSyncInfo['time']);
            $nextRun        = \DateTime::createFromFormat('Y-m-d\TH:i:s', $lastSyncInfo['time']);
            $freq = isset($entity['cache_freq']) ? $entity['cache_freq'] : 30; //In minutes
            $freqInterval = new \DateInterval("PT{$freq}M");

            $nextRun->add($freqInterval);
            $interval = $now > $nextRun;
            $badLastRun = ($lastSyncInfo['time'] != $lastSyncInfo['continue_from'])?TRUE:FALSE;
                            
            if ($interval || ($badLastRun && $lastSyncInfo['failure_count'] < 10) || $bypass) {
                set_time_limit (900); //avoid timeouts
                try {
                    $destination->ensureDataStructure($entity['internal_name']);
                } catch (\Exception $exe){}


                foreach ($entity['fields'] as $fieldName => $field) {
                    if($field['type'] != 'detail' && $field['type'] != 'formula'){
                        if(!in_array($fieldName, ['_IsUpdated'])) {
                            $selectFields[] = $field['preferred_name'];
                        }
                    }
                };

                $selectFields = implode(', ', $selectFields);
                $filter = '';
                // check if the date fields exist.
                $sourceBrowser = $source->getEntityBrowser($entity['original_display_name']);
                try {
                    if($sourceBrowser->hasField('Created')){
                        $filter = "Created ge datetime'{$lastSynched->format('Y-m-d\TH:i:s')}'";
                    }

                    if($sourceBrowser->hasField('Modified')){
                        $filter = "{$filter} or Modified ge datetime'{$lastSynched->format('Y-m-d\TH:i:s')}'";
                    }
                } catch(\Exception $ex){
                    var_dump($ex);
                }

                // $filter = "Created ge datetime'{$lastSynched->format('Y-m-d\TH:i:s')}' or Modified ge datetime'{$lastSynched->format('Y-m-d\TH:i:s')}'";
                // echo $filter. ' > '. $source->getEntityBrowser($entity['original_display_name'])->getDisplayName();
                // var_dump($source);
                $newbies = [];
                $generalErrorOccured = FALSE;
                try {
                    if(count($idsToSync) > 0){
                        $newbies = $source->getItemsByIds($entity['original_display_name'], $idsToSync, $selectFields, '',  [
                            '$all' => 1,
                            '$skipCache' => 1,
                            '$includeDeleted' => 1
                        ]);
                    } else {

                        $newbies = $source->getItems(
                            $entity['original_display_name'], 
                            $selectFields, 
                            $filter, 
                            '', 
                            [
                                '$all' => 1,
                                '$skipCache' => 1,
                                '$includeDeleted' => 1
                            ]
                        );
                    }

                } catch(\Exception $ce){
                    // var_dump($ce);
                    $generalErrorOccured = TRUE;

                    // Log this synchronization error.
                    $info = new \stdClass();
                    $info->Name = 'GET DATA ERROR';
                    $info->EntityName = $entity['original_display_name'];
                    $info->Details = $ce->getTraceAsString() . ' :: '. $ce->getMessage();
                    $info->LineNumber = $ce->getLine();
                    $info->FileName = $ce->getFile();
                    $info->Created = $now->format('Y-m-d\TH:i:s');
                    $info->Modified = $now->format('Y-m-d\TH:i:s');

                    $sql->createItem('syncherrorlog', $info);
                }
                $newbieCount = count($newbies);
                
                $synched = 0;
                foreach ($newbies as $newbie) {
                    try {
                        // Create item if it does not exist.
                        $check_query = "Id eq '{$newbie->Id}'";
                        $check_select = 'Id';

                        if(isset($entity['cache_duplicate_check_query'])){
                            $check_query = $entity['cache_duplicate_check_query'];
                            $check_query = preg_replace_callback(
                                '|(\{\}\->)([\w]+[\w\d]*)|',
                                function ($matches) use($newbie) {
                                    if(is_object($newbie) && property_exists($newbie, $matches[2])) {
                                        return $newbie->{$matches[2]};
                                    } 
                                    else if(is_array($newbie) && isset($newbie[$matches[2]])){
                                        return $newbie[$matches[2]];
                                    } else {
            
                                        throw new \Exception("The requested arrow property '{$matches[2]}' could not be found");
                                    }
                                },
                                $check_query
                            );
                        }

                        if(isset($entity['cache_duplicate_check_select'])){
                            $check_select = $entity['cache_duplicate_check_select'];
                        }
                        
                        $destCopy = $destination->getItems($entity['internal_name'], $check_select, $check_query, '', [
                            '$includeDeleted' => 1,
                            '$distinct' => $check_select,
                            '$orderBy' => $check_select
                        ]);
                    
                        if (count($destCopy) < 1) {
                            try {
                                $newbie->_IsUpdated = TRUE;
                                $destination->createItem($entity['internal_name'], $newbie, [
                                    '$setId' => '1'
                                ]);
                                $synched = $synched + 1;
                            } catch(\Exception $ce){
                                // Log this synchronization error.
                                $info = new \stdClass();
                                $info->Name = 'CREATING CACHE ITEM';
                                $info->EntityName = $entity['original_display_name'];
                                $info->Details = $ce->getTraceAsString() . ' :: '. $ce->getMessage();
                                $info->LineNumber = $ce->getLine();
                                $info->FileName = $ce->getFile();
                                $info->Created = $now->format('Y-m-d\TH:i:s');
                                $info->Modified = $now->format('Y-m-d\TH:i:s');
                                $sql->createItem('syncherrorlog', $info);
                            }
                        }
                        
                        // Other update it.
                        else {
                            try {
                                $newbie->_IsUpdated = TRUE;
                                $destination->updateItem($entity['internal_name'], $newbie->Id, $newbie);
                                $synched = $synched + 1;
                            } catch(\Exception $ce){
                                // Log this synchronization error.
                                $info = new \stdClass();
                                $info->Name = 'UPDATING CACHE ITEM';
                                $info->EntityName = $entity['original_display_name'];
                                $info->Details = $ce->getTraceAsString() . ' :: '. $ce->getMessage();
                                $info->LineNumber = $ce->getLine();
                                $info->FileName = $ce->getFile();
                                $info->Created = $now->format('Y-m-d\TH:i:s');
                                $info->Modified = $now->format('Y-m-d\TH:i:s');
                                $sql->createItem('syncherrorlog', $info);
                            }
                        }
                    } catch(\Exception $ce){
                        // Log this synchronization error.
                        $info = new \stdClass();
                        $info->Name = 'GENERAL ITEM SYNCH ERROR';
                        $info->EntityName = $entity['original_display_name'];
                        $info->Details = $ce->getTraceAsString() . ' :: '. $ce->getMessage();
                        $info->LineNumber = $ce->getLine();
                        $info->FileName = $ce->getFile();
                        $info->Created = $now->format('Y-m-d\TH:i:s');
                        $info->Modified = $now->format('Y-m-d\TH:i:s');
                        $sql->createItem('syncherrorlog', $info);
                    }
                }

                // watchdog("MIDDLEWRE CACHE {$entity['original_display_name']}", "DOWNLOADED {$newbieCount}, SYNCHED {$synched}", [], WATCHDOG_INFO);               

                // If everything went on smoothly.
                if($synched == count($newbies) && $generalErrorOccured != TRUE) {
                    $lastSyncInfo['continue_from'] = $now->format('Y-m-d\TH:i:s');
                    $lastSyncInfo['failure_count'] = 0; // reset the error counter.
                    $lastSyncInfo['time'] = $now->format('Y-m-d\TH:i:s');
                    variable_set("{$entity['original_display_name']}_LastSync__{$sourceDestination}", $lastSyncInfo);
                } 

                // Otherwise
                else {
                    $lastSyncInfo['failure_count'] = $lastSyncInfo['failure_count'] + 1;
                    $lastSyncInfo['time'] = $lastSynched->format('Y-m-d\TH:i:s'); // So that this block can be rerun asap.
                    
                    // After 10 errored tries, move forward but log the problem.
                    if($lastSyncInfo['failure_count'] > 50){
                        //Send a mail to appdev team.
                        $message = "There has been multiple failures synching {$entity['original_display_name']} {$sourceDestination}.<br/>
                        Failure count is {$lastSyncInfo['failure_count']}.";                        
                        $admin_mail = 'kolade.ige@mainone.net;amir.sanni@mainone.net';//variable_get('site_mail', ini_get('sendmail_from'));

                        try {                            
                            $emailDriver = mware_connection_driver__get_driver('emailgateway');
                            if($emailDriver){                                
                                $emailDriver->executeFunction('sendEmailMessage', [
                                    'to'=> [$admin_mail], 
                                    'cc'=> [], 
                                    'bc'=> [],
                                    'subject' => t('Multiple Cache Synch Errors'), 
                                    'body' => $message
                                ]);
                            }
                        } catch (\Exception $exp){}

                        // drupal_mail('mware_sms', 'cache_synch_error', $admin_mail, language_default(), $params);
                        

                        // $lastSyncInfo['continue_from'] = $now->format('Y-m-d\TH:i:s'); // Experimentally ommitted.
                        $lastSyncInfo['time'] = $now->format('Y-m-d\TH:i:s');
                        $lastSyncInfo['failure_count'] = 0;

                        // Log the error.
                        $info = new \stdClass();
                        $info->Name = 'MULTIPLE CACHE FAILURES';
                        $info->EntityName = $entity['original_display_name'];
                        $info->Details = "After {$lastSyncInfo['failure_count']} unsuccessful tries, the system is moving on.";
                        $info->LineNumber = ''.__LINE__;
                        $info->FileName = __FILE__;
                        $info->Created = $now->format('Y-m-d\TH:i:s');
                        $info->Modified = $now->format('Y-m-d\TH:i:s');
                        $sql->createItem('syncherrorlog', $info);
                    }
                    variable_set("{$entity['original_display_name']}_LastSync__{$sourceDestination}", $lastSyncInfo);
                }
            }
    } else {
        watchdog('UPDATING CACHE ERROR', 'One or more sources could not be loaded.');
    }
}