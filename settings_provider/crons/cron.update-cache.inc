<?php

function settings_provider__update_cache()
{
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

                $now = (new \DateTime());
                $lastSynched    = \DateTime::createFromFormat('Y-m-d\TH:i:s', $lastSyncInfo['time']);
                $nextRun        = \DateTime::createFromFormat('Y-m-d\TH:i:s', $lastSyncInfo['time']);
                $freq = isset($entity['cache_freq']) ? $entity['cache_freq'] : 30; //In minutes
                $freqInterval = new \DateInterval("PT{$freq}M");

                $nextRun->add($freqInterval);
                $interval = $now > $nextRun;
                $badLastRun = ($defaultSyncData['time'] != $defaultSyncData['continue_from'])?TRUE:FALSE;
                                
                if ($interval || ($badLastRun && $defaultSyncData['failure_count'] < 10)) {
                    foreach ($entity['fields'] as $field) {
                        if($field['type'] != 'detail'){
                            $selectFields[] = $field['preferred_name'];
                        }
                    };

                    $selectFields = implode(', ', $selectFields);
                    $filter = "Created ge datetime'{$lastSynched->format('Y-m-d\TH:i:s')}' or Modified ge datetime'{$lastSynched->format('Y-m-d\TH:i:s')}'";
                    $newbies = [];
                    $generalErrorOccured = FALSE;
                    try {
                        $newbies = $source->getItems(
                            $entity['original_display_name'], 
                            $selectFields, 
                            $filter, 
                            '', 
                            [
                                '$all' => 1,
                                '$skipCache' => 1
                            ]
                        );

                    } catch(\Exception $ce){
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

                    $synched = 0;
                    foreach ($newbies as $newbie) {
                        try {
                            // Create item if it does not exist.
                            $destCopy = $destination->getItems($entity['internal_name'], 'Id', "Id eq '{$newbie->Id}'", '');
                        
                            if (count($destCopy) < 1) {
                                try {
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

                    // $lastSyncInfo['continue_from'] = $lastSyncInfo['time'];

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
                        if($lastSyncInfo['failure_count'] > 10){
                            $lastSyncInfo['continue_from'] = $now->format('Y-m-d\TH:i:s');
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
            }
        } else {
            watchdog('UPDATING CACHE ERROR', 'One or more sources could not be loaded.');
        }
    }
}
