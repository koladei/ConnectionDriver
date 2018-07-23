<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function d365_connection_driver_form_settings_provider_admin_alter(&$form, &$form_state) {

    $form['d365_settings'] = [
        '#type' => 'fieldset',
        '#title' => t('Salesforce connection settings'),
        '#description' => t('A sample configuration value'),
        '#collapsible' => TRUE
        , '#group' => 'general_settings'
    ];

    $form['d365_settings']['D365_SETTINGS_USE_PRODUCTION'] = [
        '#type' => 'checkbox',
        '#title' => t('Use production settings'),
        '#default_value' => variable_get('D365_SETTINGS_USE_PRODUCTION', FALSE),
        '#description' => t('The source of <b>Customer</b> information'),
        '#weight' => 0
    ];

    $form['d365_settings']['D365_PROXY_SERVER'] = [
        '#type' => 'textfield'
        , '#title' => t('Proxy server IP')
        , '#default_value' => variable_get('D365_PROXY_SERVER', '')
        , '#size' => 100
        , '#maxlength' => 100
        , '#description' => t('Provide the IP address of the Proxy server.')
    ];

    $form['d365_settings']['D365_PROXY_SERVER_PORT'] = [
        '#type' => 'textfield'
        , '#title' => t('Proxy server port')
        , '#default_value' => variable_get('D365_PROXY_SERVER_PORT', '3128')
        , '#size' => 100
        , '#maxlength' => 100
        , '#description' => t('The port on which the proxy server is listening.')
    ];

    $form['d365_settings']['production_d365_settings'] = [
        '#type' => 'fieldset',
        '#title' => t('Production Environment'),
        '#description' => t('Settings for the production environment'),
        '#collapsible' => TRUE,
    ];

    $form['d365_settings']['sandbox_d365_settings'] = [
        '#type' => 'fieldset',
        '#title' => t('Sandbox Environment'),
        '#description' => t('Settings for the sandbox environment'),
        '#collapsible' => TRUE,
    ];

    //    PRODUCTION SERVER SETTINGS
    $form['d365_settings']['production_d365_settings']['D365_SETTINGS_ENDPOINT_URL'] = [
        '#type' => 'textfield',
        '#title' => t('Remote End-Point URL'),
        '#default_value' => variable_get('D365_SETTINGS_ENDPOINT_URL', 'https://login.d365.com/services/oauth2/token'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The source of <b>Customer</b> information'),
        '#required' => TRUE,
        '#weight' => 0
    ];

    $form['d365_settings']['production_d365_settings']['D365_SETTINGS_USERNAME'] = [
        '#type' => 'textfield',
        '#title' => t('Remote End-Point username'),
        '#default_value' => variable_get('D365_SETTINGS_USERNAME', 'user.name@domain.com'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The username to logon to the remote webservice'),
        '#required' => TRUE,
        '#weight' => 1
    ];

    $form['d365_settings']['production_d365_settings']['D365_SETTINGS_PASSWORD'] = [
        '#type' => 'password',
        '#title' => t('Remote End-Point password'),
        '#default_value' => variable_get('D365_SETTINGS_PASSWORD', ''),
        '#attributes' => array(
            'value' => variable_get('D365_SETTINGS_PASSWORD', ''),
        ),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The password to the remote webservice.'),
        '#weight' => 2
    ];

    $form['d365_settings']['production_d365_settings']['D365_SETTINGS_GRANT_TYPE'] = [
        '#type' => 'radios',
        '#options' => ['password' => 'Password'],
        '#title' => t('Grant type'),
        '#default_value' => variable_get('D365_SETTINGS_GRANT_TYPE', 'password'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The grant type to use'),
        '#required' => TRUE,
        '#weight' => 3
    ];

    $form['d365_settings']['production_d365_settings']['D365_SETTINGS_CLIENT_ID'] = [
        '#type' => 'textfield',
        '#title' => t('Client ID'),
        '#default_value' => variable_get('D365_SETTINGS_CLIENT_ID', 'ID'),
        '#size' => 100,
        '#maxlength' => 300,
        '#description' => t('The remote end-point client id.'),
        '#required' => TRUE,
        '#weight' => 4
    ];

    $form['d365_settings']['production_d365_settings']['D365_SETTINGS_CLIENT_SECRET'] = [
        '#type' => 'textfield',
        '#title' => t('Client secrete'),
        '#default_value' => variable_get('D365_SETTINGS_CLIENT_SECRET', 'SECRET'),
        '#size' => 100,
        '#maxlength' => 300,
        '#description' => t('The remote end-point client secret.'),
        '#required' => TRUE,
        '#weight' => 5
    ];

    $form['d365_settings']['production_d365_settings']['D365_SETTINGS_USE_PROXY'] = [
        '#type' => 'checkbox'
        , '#title' => t('Use proxy')
        , '#default_value' => variable_get('D365_SETTINGS_USE_PROXY', FALSE)
        , '#description' => t('Whether or not to use the proxy server to reach d365.')
    ];

    //    SANDBOX SERVER SETTINGS
    $form['d365_settings']['sandbox_d365_settings']['D365_SANDBOX_SETTINGS_ENDPOINT_URL'] = [
        '#type' => 'textfield',
        '#title' => t('Remote End-Point URL'),
        '#default_value' => variable_get('D365_SANDBOX_SETTINGS_ENDPOINT_URL', 'https://login.d365.com/services/oauth2/token'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The source of <b>Customer</b> information'),
        '#required' => TRUE,
        '#weight' => 0
    ];

    $form['d365_settings']['sandbox_d365_settings']['D365_SANDBOX_SETTINGS_USERNAME'] = [
        '#type' => 'textfield',
        '#title' => t('Remote End-Point username'),
        '#default_value' => variable_get('D365_SANDBOX_SETTINGS_USERNAME', 'user.name@domain.com'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The username to logon to the remote webservice'),
        '#required' => TRUE,
        '#weight' => 1
    ];

    $form['d365_settings']['sandbox_d365_settings']['D365_SANDBOX_SETTINGS_PASSWORD'] = [
        '#type' => 'password',
        '#title' => t('Remote End-Point password'),
        '#default_value' => variable_get('D365_SANDBOX_SETTINGS_PASSWORD', ''),
        '#attributes' => array(
            'value' => variable_get('D365_SANDBOX_SETTINGS_PASSWORD', ''), //'sms!p@ss'
        ),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The password to the remote webservice.'),
        '#weight' => 2
    ];

    $form['d365_settings']['sandbox_d365_settings']['D365_SANDBOX_SETTINGS_GRANT_TYPE'] = [
        '#type' => 'radios',
        '#options' => ['password' => 'Password'],
        '#title' => t('Grant type'),
        '#default_value' => variable_get('D365_SANDBOX_SETTINGS_GRANT_TYPE', 'password'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The grant type to use'),
        '#required' => TRUE,
        '#weight' => 3
    ];

    $form['d365_settings']['sandbox_d365_settings']['D365_SANDBOX_SETTINGS_CLIENT_ID'] = [
        '#type' => 'textfield',
        '#title' => t('Client ID'),
        '#default_value' => variable_get('D365_SANDBOX_SETTINGS_CLIENT_ID', 'ID'),
        '#size' => 100,
        '#maxlength' => 300,
        '#description' => t('The remote end-point client id.'),
        '#required' => TRUE,
        '#weight' => 4
    ];

    $form['d365_settings']['sandbox_d365_settings']['D365_SANDBOX_SETTINGS_CLIENT_SECRET'] = [
        '#type' => 'textfield',
        '#title' => t('Client secrete'),
        '#default_value' => variable_get('D365_SANDBOX_SETTINGS_CLIENT_SECRET', 'SECRET'),
        '#size' => 100,
        '#maxlength' => 300,
        '#description' => t('The remote end-point client secret.'),
        '#required' => TRUE,
        '#weight' => 5
    ];

    $form['d365_settings']['sandbox_d365_settings']['D365_SANDBOX_SETTINGS_USE_PROXY'] = [
        '#type' => 'checkbox'
        , '#title' => t('Use proxy')
        , '#default_value' => variable_get('D365_SANDBOX_SETTINGS_USE_PROXY', FALSE)
        , '#description' => t('Whether or not to use the proxy server to reach d365.')
    ];

    return system_settings_form($form);
}
