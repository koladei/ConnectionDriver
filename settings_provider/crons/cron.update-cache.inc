<?php

function settings_provider__update_cache()
{
    // Get all entities that are exportable.
    $cached_all = module_invoke_all('_get_cached_entity_definitions', 'sql');

    foreach ($cached_all as $sourceDestination => $cached) {
        list($from, $to) = $sd = explode('|', $sourceDestination);

        // Get references to the source and destination data sources
        $source = mware_connection_driver__get_driver($from);
        $destination = mware_connection_driver__get_driver($to);
        $sql = mware_connection_driver__get_driver('sql');

        if (!is_null($source) && !is_null($destination) && !is_null($sql)) {
            foreach ($cached as $entity) {
                // Get the field names to select
                $selectFields = [];

                // Check the cache frequecy of this entity and act accordingly.                
                $now = (new \DateTime());
                $lastSynched    = \DateTime::createFromFormat('Y-m-d\TH:i:s', variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", '1998-01-01T00:00:00'));
                $nextRun        = \DateTime::createFromFormat('Y-m-d\TH:i:s', variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", '1998-01-01T00:00:00'));
                $freq = isset($entity['cache_freq']) ? $entity['cache_freq'] : 30; //In minutes
                $freqInterval = new \DateInterval("PT{$freq}M");

                $nextRun->add($freqInterval);
                $interval = $now > $nextRun;
                                
                if ($interval) {
                    
                    foreach ($entity['fields'] as $field) {
                        if($field['type'] != 'detail'){
                            $selectFields[] = $field['preferred_name'];
                        }
                    };

                    $selectFields = implode(', ', $selectFields);

                    $filter = "Created ge datetime'{$lastSynched->format('Y-m-d\TH:i:s')}' or Modified ge datetime'{$lastSynched->format('Y-m-d\TH:i:s')}'";
                    $newbies = $source->getItems(
                        $entity['original_display_name'], 
                        $selectFields, 
                        $filter, 
                        '', 
                        [
                            '$top' => 5000,
                            '$skipCache' => 1
                        ]
                    );
                    
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
                                    $info->Details = $ce->getMessage();
                                    $info->LineNumber = "{__LINE__}";
                                    $info->FileName = "{__DIR__} {__FILE__}";
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
                                    $info->Details = $ce->getMessage();
                                    $info->LineNumber = "{__LINE__}";
                                    $info->FileName = "{__DIR__} {__FILE__}";
                                    $info->Created = $now->format('Y-m-d\TH:i:s');
                                    $info->Modified = $now->format('Y-m-d\TH:i:s');
                                    $sql->createItem('syncherrorlog', $info);
                                }
                            }
                        } catch(\Exception $ce){
                            // Log this synchronization error.
                            $info = new \stdClass();
                            $info->Name = 'GENERAL ITEM SYNCH ERROR';
                            $info->Details = $ce->getMessage();
                            $info->LineNumber = "{__LINE__}";
                            $info->FileName = "{__DIR__} {__FILE__}";
                            $info->Created = $now->format('Y-m-d\TH:i:s');
                            $info->Modified = $now->format('Y-m-d\TH:i:s');
                            $sql->createItem('syncherrorlog', $info);
                        }
                    }

                    if($synched == count($newbies)){
                        variable_set("{$entity['original_display_name']}_LastSync__{$sourceDestination}", $now->format('Y-m-d\TH:i:s'));
                    }
                }
            }
        }
    }

    // Update each entity record.
}
