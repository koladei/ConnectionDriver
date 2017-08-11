<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function dynamics_connection_driver_form_settings_provider_admin_alter(&$form, &$form_state) {

    $form['dynamics_ax_settings'] = [
        '#type' => 'fieldset',
        '#title' => t('Dynamics AX Connection settings'),
        '#description' => t('A sample configuration value'),
        '#collapsible' => TRUE
        , '#group' => 'general_settings'
    ];

    $form['dynamics_ax_settings']['DYNAMICS_AX_SETTINGS_ENDPOINT_URL'] = [
        '#type' => 'textfield',
        '#title' => t('Endpoint URL'),
        '#default_value' => variable_get('DYNAMICS_AX_SETTINGS_ENDPOINT_URL', ''),
        '#description' => t('The URL of the dynamics service'),
        '#weight' => 0
    ];

    return system_settings_form($form);
}
