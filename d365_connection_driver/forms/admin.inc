<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function d365_connection_driver_form_settings_provider_admin_alter(&$form, &$form_state) {

    $defaults = variable_get('d365_settings', [
        'authentication_url' => ''
        , 'resource' => ''
        , 'grant_type' => ''
        , 'client_id' => ''
        , 'client_secret' => ''
    ]);

    $form['d365_settings'] = [
        '#type' => 'fieldset'
        , '#title' => t('D365 connection settings')
        , '#description' => t('A sample configuration value')
        , '#collapsible' => TRUE
        , '#group' => 'general_settings'
        , '#tree' => TRUE
    ];

    //    PRODUCTION SERVER SETTINGS
    $form['d365_settings']['authentication_url'] = [
        '#type' => 'textfield',
        '#title' => t('Authentication URL (OAuth 2.0)'),
        '#default_value' => $defaults['authentication_url'],
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The source of <b>Customer</b> information'),
        '#required' => TRUE,
        '#weight' => 0
    ];

    $form['d365_settings']['resource'] = [
        '#type' => 'textfield',
        '#title' => t('Resource URL'),
        '#default_value' => $defaults['resource'],
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The URL of the application to query'),
        '#required' => TRUE,
        '#weight' => 1
    ];

    $form['d365_settings']['grant_type'] = [
        '#type' => 'radios',
        '#options' => ['client_credentials' => 'Client Credentials'],
        '#title' => t('Grant type'),
        '#default_value' => $defaults['grant_type'],
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The grant type to use for authentication.'),
        '#required' => TRUE,
        '#weight' => 3
    ];

    $form['d365_settings']['client_id'] = [
        '#type' => 'textfield',
        '#title' => t('Client ID'),
        '#default_value' => $defaults['client_id'],
        '#size' => 100,
        '#maxlength' => 300,
        '#description' => t('The remote end-point client id.'),
        '#required' => TRUE,
        '#weight' => 4
    ];

    $form['d365_settings']['client_secret'] = [
        '#type' => 'textfield',
        '#title' => t('Client secrete'),
        '#default_value' => $defaults['client_secret'],
        '#size' => 100,
        '#maxlength' => 300,
        '#description' => t('The remote end-point client secret.'),
        '#required' => TRUE,
        '#weight' => 5
    ];

    return system_settings_form($form);
}
