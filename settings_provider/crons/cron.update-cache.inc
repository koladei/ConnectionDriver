<?php

function settings_provider__update_cache()
{
    return;
    // Get all entities that are exportable.
    $cached_all = module_invoke_all('_get_cached_entity_definitions', 'sql');

    foreach ($cached_all as $sourceDestination => $cached) {
        list($from, $to) = $sd = explode('|', $sourceDestination);

        // Get references to the source and destination data sources
        $source = mware_connection_driver__get_driver($from);
        $destination = mware_connection_driver__get_driver($to);

        if (!is_null($source) && !is_null($destination)) {
            // Get the field names to select
            $selectFields = [];

            foreach ($cached as $entity) {
                foreach($entity['fields'] as $field) {
                    $selectFields[] = $field['preferred_name'];
                };

                $selectFields = implode(', ', $selectFields);
                // watchdog('CACHE', "$from - $to {$selectFields} - {$entity['original_internal_name']} - {$entity['original_display_name']}");
                $newbies = $source->getItems($entity['original_display_name'], $selectFields, '', '', [
                    '$top' => 50,
                    '$skipCache' => 1
                ]);
                
                foreach($newbies as $newbie){
                    // Create item if it does not exist.
                    $destCopy = $destination->getItems($entity['internal_name'], 'Id', "Id eq '{$newbie->Id}'", '');
                    watchdog('CACHE', print_r($newbie, true));
                    if(count($destCopy) < 1){
                        $destination->createItem($entity['internal_name'], $newbie, [
                            '$createId' => 1
                        ]);
                        watchdog('CACHE', "Creating item");
                    }
                    // Other update it.
                    else {
                        $destination->updateItem($entity['internal_name'], $newbie->Id, $newbie);
                        watchdog('CACHE', "Updating item");
                    }
                }
            }
        }
    }

    // Update each entity record.
}
