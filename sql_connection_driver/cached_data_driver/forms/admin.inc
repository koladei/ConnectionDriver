<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function cached_data_driver_form_settings_provider_admin_alter(&$form, &$form_state) {
    // $form['#submit'][] = 'cached_data_driver_form_settings_provider_admin_submit';

    return system_settings_form($form);
}

function cached_data_driver_form_settings_provider_admin_submit($form, &$form_state) {

    $groups = cached_data_driver_settings_groups();
    foreach($groups as $key => &$name){  
        if(variable_get("SQL_SETTINGS_DELETE_{$key}", FALSE) == FALSE){                    
            // Update the display name              
            $x = variable_get("SQL_SETTINGS_ALIAS_{$key}");
            $name = strlen($x) > 0 ? $x: 'NEW SOURCE';
        } else{

            // Remove unwanted sources
            cached_data_driver_settings_groups_delete($key);
            unset($groups[$key]);
        }
    }

    cached_data_driver_settings_groups_save($groups);
}

function cached_data_driver_settings_groups_save($groups){
    $ser = serialize($groups);
    variable_set('SQL_SETTINGS_GROUPS', $ser);
}

function cached_data_driver_settings_groups_delete($key){
    variable_del("SQL_SETTINGS_ALIAS_{$key}");
    variable_del("SQL_SETTINGS_DSN_{$key}");
    variable_del("SQL_SETTINGS_USERNAME_{$key}");
    variable_del("SQL_SETTINGS_PASSWORD_{$key}");
    variable_del("SQL_SETTINGS_DBTYPE_{$key}");
    variable_del("SQL_SETTINGS_DELETE_{$key}");
}

function cached_data_driver_settings_groups(){
    $ser = serialize(['default'=>'DEFAULT']);
    $def = variable_get('SQL_SETTINGS_GROUPS', $ser);  
    return unserialize($def);
}

function cached_data_driver_settings_add($form, &$form_state) {
    // Get the existing list existing drivers.
    $ser = serialize(['default'=>'DEFAULT']);
    $def = variable_get('SQL_SETTINGS_GROUPS', $ser);
    $groups = unserialize($def);
    
    // Add the new group to the list
    $name = $form_state['values']['SQL_SETTINGS_NEW_GROUP'];
    if(!array_key_exists($name, $groups)){
        $groups[strtolower($name)] = $name;

        $ser = serialize($groups);
        variable_set('SQL_SETTINGS_GROUPS', $ser);
    }
}