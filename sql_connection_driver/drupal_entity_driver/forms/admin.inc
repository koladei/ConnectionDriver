<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function data_dictionary_driver_form_settings_provider_admin_alter(&$form, &$form_state) {

    $form['data_dictionary_settings'] = [
        '#type' => 'fieldset',
        '#title' => t('Data Dictionary Settings'),
        '#description' => t('A sample configuration value'),
        '#collapsible' => TRUE
        , '#group' => 'general_settings'
    ];

    return system_settings_form($form);
}
