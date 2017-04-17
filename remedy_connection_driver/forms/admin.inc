<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function remdey_connection_driver_form_settings_provider_admin_alter(&$form, &$form_state) {

    $form['dynamics_ax_settings'] = [
        '#type' => 'fieldset',
        '#title' => t('Dynamics AX Connection settings'),
        '#description' => t('A sample configuration value'),
        '#collapsible' => TRUE
        , '#group' => 'general_settings'
    ];

    $form['dynamics_ax_settings']['DYNAMICS_AX_SETTINGS_USE_PRODUCTION'] = [
        '#type' => 'checkbox',
        '#title' => t('Use production settings'),
        '#default_value' => variable_get('DYNAMICS_AX_SETTINGS_USE_PRODUCTION', FALSE),
        '#description' => t('The source of <b>Customer</b> information'),
        '#weight' => 0
    ];

    return system_settings_form($form);
}
