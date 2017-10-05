<?php

function settings_provider_admin() {

    // $abc = module_invoke_all('_get_entity_definitions');
    // drupal_set_message(print_r(array_keys($abc), true));
    
    $form['general_settings'] = array(
        '#type' => 'vertical_tabs',
        '#title' => t('General settings'),
        '#description' => t('Provide configuration values below'),
    );

    // $form['general_settings']['general_settings2'] = [
    //     '#type' => 'markup',
    // ];

    // $form['general_settings']['general_settings2']['general_settings3'] = [
    //     '#type' => 'fieldset',
    //     '#title' => t('General settings'),
    //     '#description' => t('Define settings for cached data objects')
    // ];

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
        '#weight' => -1,
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
            $form['data_object_settings']["{$entity['original_display_name']}_LastSync__{$sourceDestination}"] = [
                '#title' => strtoupper("<b>{$from}:</b> {$entity['original_display_name']}"),
                '#type' => 'textfield',
                '#description' => t('When to begin synching'),
                '#default_value' => variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", '1998-01-01T00:00:00')
            ];
            
            $form['data_objects'][$sourceDestination][0]["LastSync"] = [
                '#title' => strtoupper("<b>{$from}:</b> {$entity['original_display_name']}"),
                '#type' => 'textfield',
                '#description' => t('When to begin synching'),
                '#default_value' => variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", '1998-01-01T00:00:00')
            ];
            
            $form['data_objects'][$sourceDestination][0]["NextSync"] = [
                '#title' => strtoupper("<b>{$from}:</b> {$entity['original_display_name']}"),
                '#type' => 'textfield',
                '#description' => t('When to begin synching'),
                '#default_value' => variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", '1998-01-01T00:00:00')
            ];
            
            $form['data_objects'][$sourceDestination][1]["LastSync"] = [
                '#title' => strtoupper("<b>{$from}:</b> {$entity['original_display_name']}"),
                '#type' => 'textfield',
                '#description' => t('When to begin synching'),
                '#default_value' => variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", '1998-01-01T00:00:00')
            ];
            
            $form['data_objects'][$sourceDestination][1]["NextSync"] = [
                '#title' => strtoupper("<b>{$from}:</b> {$entity['original_display_name']}"),
                '#type' => 'textfield',
                '#description' => t('When to begin synching'),
                '#default_value' => variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", '1998-01-01T00:00:00')
            ];
        }
    }

    $form['#validate'][] = 'settings_provider_admin_validate';

    return system_settings_form($form);
}

function settings_provider_admin_validate($form, $form_state){
    // drupal_set_message(print_r($form_state['values']['data_objects'], true));
}
