<?php

function settings_provider_admin() {

    // $abc = module_invoke_all('_get_entity_definitions');
    // drupal_set_message(print_r(array_keys($abc), true));
    
    $form['general_settings'] = array(
        '#type' => 'vertical_tabs',
        '#title' => t('General settings'),
        '#description' => t('Provide configuration values below'),
    );

    $form['general_settings']['MWARE_ROOT_CONNECTION_DRIVER__SYNCH_CACHE'] = [
        '#type' => 'checkbox',
        '#weight' => -1,
        '#title' => t('Run Cache Synch'),
        '#description' => t('Whether to run the synch. or not'),
        '#default_value' => variable_get('MWARE_ROOT_CONNECTION_DRIVER__SYNCH_CACHE', FALSE)
    ];

    //MWARE_ROOT_CONNECTION_DRIVER__SYNCH_CACHE
    
    $form['data_object_settings'] = [
        '#type' => 'fieldset',
        '#weight' => -2,
        '#group' => 'general_settings',
        '#title' => t('Data object settings'),
        '#description' => t('Define settings for cached data objects'),
        '#collapsible' => TRUE,
        '#group' => 'general_settings'
    ];
    
    $form['data_objects'] = [
        '#type' => 'fieldset',
        '#weight' => -1,
        '#group' => 'general_settings',
        '#title' => t('Data Objects'),
        '#description' => t('Define settings for cached data objects'),
        '#collapsible' => TRUE,
        '#group' => 'general_settings',
        '#tree' => TRUE
    ];

    // Show cached object settings
    $cached_all = module_invoke_all('_get_cached_entity_definitions', 'sql');
    foreach ($cached_all as $sourceDestination => $cached) {
        list($from, $to) = $sd = explode('|', $sourceDestination);

        
        $form['data_objects'][$sourceDestination] = [
            0 => ['#type' => 'fieldset'],
            1 => ['#type' => 'fieldset']
        ];

        foreach ($cached as $entity) {
            $def = variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}");
            $form['data_object_settings']["{$entity['original_display_name']}_LastSync__{$sourceDestination}"] = [
                '#title' => strtoupper("<b>{$from}:</b> {$entity['original_display_name']}"),
                '#type' => 'fieldset',
                '#description' => t('When to begin synching'),
                '#description' => t("Last run at: {$def['time']}"),
                '#tree' => TRUE
            ];

            if(is_array($def) && isset($def['continue_from'])){
                $form['data_object_settings']["{$entity['original_display_name']}_LastSync__{$sourceDestination}"]['continue_from'] = [
                    '#title' => t('Synch data from'),
                    '#type' => 'textfield',
                    '#description' => t('When to begin synching'),
                    '#default_value' => $def['continue_from']                     
                ]; 
                $form['data_object_settings']["{$entity['original_display_name']}_LastSync__{$sourceDestination}"]['save'] = [
                    '#name' => "{$entity['original_display_name']}_LastSync__{$sourceDestination}_save"
                    , '#type' => 'submit'
                    , '#value' => t('Update')
                    , '#submit' => ['settings_provider__update_sync_information']
                ];
                $form['data_object_settings']["{$entity['original_display_name']}_LastSync__{$sourceDestination}"]['synch_now'] = [
                    '#name' => "{$entity['original_display_name']}_LastSync__{$sourceDestination}_sync_now"
                    , '#type' => 'submit'
                    , '#value' => t('Sync. Now')
                    , '#submit' => ['settings_provider__sync_now']
                ];
                $form['data_object_settings']["{$entity['original_display_name']}_LastSync__{$sourceDestination}"]['time'] = [
                    '#title' => t('Synch data from'),
                    '#type' => 'hidden',
                    '#value' => $def['continue_from'],
                ];    
                $form['data_object_settings']["{$entity['original_display_name']}_LastSync__{$sourceDestination}"]['failure_count'] = [
                    '#title' => t('Failure count'),
                    '#type' => 'textfield',
                    '#value' => $def['failure_count'],
                ];                
            }
            
            // $form['data_objects'][$sourceDestination][0]["LastSync"] = [
            //     '#title' => strtoupper("<b>{$from}:</b> {$entity['original_display_name']}"),
            //     '#type' => 'textfield',
            //     '#description' => t('When to begin synching'),
            //     '#default_value' => variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", '1998-01-01T00:00:00')
            // ];
            
            // $form['data_objects'][$sourceDestination][0]["NextSync"] = [
            //     '#title' => strtoupper("<b>{$from}:</b> {$entity['original_display_name']}"),
            //     '#type' => 'textfield',
            //     '#description' => t('When to begin synching'),
            //     '#default_value' => variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", '1998-01-01T00:00:00')
            // ];
            
            // $form['data_objects'][$sourceDestination][1]["LastSync"] = [
            //     '#title' => strtoupper("<b>{$from}:</b> {$entity['original_display_name']}"),
            //     '#type' => 'textfield',
            //     '#description' => t('When to begin synching'),
            //     '#default_value' => variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", '1998-01-01T00:00:00')
            // ];
            
            // $form['data_objects'][$sourceDestination][1]["NextSync"] = [
            //     '#title' => strtoupper("<b>{$from}:</b> {$entity['original_display_name']}"),
            //     '#type' => 'textfield',
            //     '#description' => t('When to begin synching'),
            //     '#default_value' => variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", '1998-01-01T00:00:00')
            // ];
        }
    }

    $form['#validate'][] = 'settings_provider_admin_validate';

    return system_settings_form($form);
}

function settings_provider__update_sync_information($form, &$form_state){
    // drupal_set_message(print_r($form_state['triggering_element'], true));
    // drupal_form_submit('settings_provider_admin', $form_state);
}

function settings_provider__sync_now($form, &$form_state){
    
}

function settings_provider_admin_validate($form, $form_state){
    // drupal_set_message(print_r($form_state['values']['data_objects'], true));
}
