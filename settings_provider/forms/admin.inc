<?php

function settings_provider_admin() {
    
    $form['general_settings'] = array(
        '#type' => 'vertical_tabs',
        '#title' => t('General settings'),
        '#description' => t('Provide configuration values below'),
    );

    $form['data_object_settings'] = [
        '#type' => 'fieldset',
        '#weight' => -1,
        '#group' => 'general_settings',
        '#title' => t('Data object settings'),
        '#description' => t('Define settings for cached data objects'),
        '#collapsible' => TRUE,
        '#group' => 'general_settings'
    ];

    // Show cached object settings
    $cached_all = module_invoke_all('_get_cached_entity_definitions', 'sql');
    foreach ($cached_all as $sourceDestination => $cached) {
        list($from, $to) = $sd = explode('|', $sourceDestination);

        foreach ($cached as $entity) {
            $form['data_object_settings']["{$entity['original_display_name']}_LastSync__{$sourceDestination}"] = [
                '#title' => strtoupper("<b>{$from}:</b> {$entity['original_display_name']}"),
                '#type' => 'textfield',
                '#description' => t('When to begin synching'),
                '#default_value' => variable_get("{$entity['original_display_name']}_LastSync__{$sourceDestination}", '1998-01-01T00:00:00')
            ];
        }
    }

    return system_settings_form($form);
}
