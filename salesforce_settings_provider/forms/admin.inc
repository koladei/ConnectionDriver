<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function salesforce_settings_provider_form_settings_provider_admin_alter(&$form, &$form_state) {

    //This is as backward compatibility block.
    if (variable_get('SALESFORCE_SETTINGS_ENDPOINT_URL', FALSE) == FALSE) {
        variable_set('SALESFORCE_SETTINGS_ENDPOINT_URL', variable_get('MWARE_CUSTOMER_REMOTE_ENDPOINT'));
        variable_set('SALESFORCE_SETTINGS_USERNAME', variable_get('MWARE_CUSTOMER_REMOTE_ENDPOINT_USERNAME'));
        variable_set('SALESFORCE_SETTINGS_PASSWORD', variable_get('MWARE_CUSTOMER_REMOTE_ENDPOINT_PASSWORD'));
        variable_set('SALESFORCE_SETTINGS_GRANT_TYPE', variable_get('MWARE_CUSTOMER_REMOTE_ENDPOINT_GRANT_TYPE'));
        variable_set('SALESFORCE_SETTINGS_CLIENT_ID', variable_get('MWARE_CUSTOMER_REMOTE_ENDPOINT_CLIENT_ID'));
        variable_set('SALESFORCE_SETTINGS_CLIENT_SECRET', variable_get('MWARE_CUSTOMER_REMOTE_ENDPOINT_CLIENT_SECRET'));
    }

    $form['salesforce_settings'] = [
        '#type' => 'fieldset',
        '#title' => t('Salesforce connection settings'),
        '#description' => t('A sample configuration value'),
        '#collapsible' => TRUE
        , '#group' => 'general_settings'
    ];

    $form['salesforce_settings']['SALESFORCE_SETTINGS_USE_PRODUCTION'] = [
        '#type' => 'checkbox',
        '#title' => t('Use production settings'),
        '#default_value' => variable_get('SALESFORCE_SETTINGS_USE_PRODUCTION', FALSE),
        '#description' => t('The source of <b>Customer</b> information'),
        '#weight' => 0
    ];

    $form['salesforce_settings']['production_salesforce_settings'] = [
        '#type' => 'fieldset',
        '#title' => t('Production Environment'),
        '#description' => t('Settings for the production environment'),
        '#collapsible' => TRUE,
    ];

    $form['salesforce_settings']['sandbox_salesforce_settings'] = [
        '#type' => 'fieldset',
        '#title' => t('Sandbox Environment'),
        '#description' => t('Settings for the sandbox environment'),
        '#collapsible' => TRUE,
    ];

    //    PRODUCTION SERVER SETTINGS
    $form['salesforce_settings']['production_salesforce_settings']['SALESFORCE_SETTINGS_ENDPOINT_URL'] = [
        '#type' => 'textfield',
        '#title' => t('Remote End-Point URL'),
        '#default_value' => variable_get('SALESFORCE_SETTINGS_ENDPOINT_URL', 'https://login.salesforce.com/services/oauth2/token'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The source of <b>Customer</b> information'),
        '#required' => TRUE,
        '#weight' => 0
    ];

    $form['salesforce_settings']['production_salesforce_settings']['SALESFORCE_SETTINGS_USERNAME'] = [
        '#type' => 'textfield',
        '#title' => t('Remote End-Point username'),
        '#default_value' => variable_get('SALESFORCE_SETTINGS_USERNAME', 'user.name@domain.com'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The username to logon to the remote webservice'),
        '#required' => TRUE,
        '#weight' => 1
    ];

    $form['salesforce_settings']['production_salesforce_settings']['SALESFORCE_SETTINGS_PASSWORD'] = [
        '#type' => 'password',
        '#title' => t('Remote End-Point password'),
        '#default_value' => variable_get('SALESFORCE_SETTINGS_PASSWORD', ''),
        '#attributes' => array(
            'value' => variable_get('SALESFORCE_SETTINGS_PASSWORD', ''),
        ),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The password to the remote webservice.'),
        '#weight' => 2
    ];

    $form['salesforce_settings']['production_salesforce_settings']['SALESFORCE_SETTINGS_GRANT_TYPE'] = [
        '#type' => 'radios',
        '#options' => ['password' => 'Password'],
        '#title' => t('Grant type'),
        '#default_value' => variable_get('SALESFORCE_SETTINGS_GRANT_TYPE', 'password'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The grant type to use'),
        '#required' => TRUE,
        '#weight' => 3
    ];

    $form['salesforce_settings']['production_salesforce_settings']['SALESFORCE_SETTINGS_CLIENT_ID'] = [
        '#type' => 'textfield',
        '#title' => t('Client ID'),
        '#default_value' => variable_get('SALESFORCE_SETTINGS_CLIENT_ID', 'ID'),
        '#size' => 100,
        '#maxlength' => 300,
        '#description' => t('The remote end-point client id.'),
        '#required' => TRUE,
        '#weight' => 4
    ];

    $form['salesforce_settings']['production_salesforce_settings']['SALESFORCE_SETTINGS_CLIENT_SECRET'] = [
        '#type' => 'textfield',
        '#title' => t('Client secrete'),
        '#default_value' => variable_get('SALESFORCE_SETTINGS_CLIENT_SECRET', 'SECRET'),
        '#size' => 100,
        '#maxlength' => 300,
        '#description' => t('The remote end-point client secret.'),
        '#required' => TRUE,
        '#weight' => 5
    ];

    //    SANDBOX SERVER SETTINGS
    $form['salesforce_settings']['sandbox_salesforce_settings']['SALESFORCE_SANDBOX_SETTINGS_ENDPOINT_URL'] = [
        '#type' => 'textfield',
        '#title' => t('Remote End-Point URL'),
        '#default_value' => variable_get('SALESFORCE_SANDBOX_SETTINGS_ENDPOINT_URL', 'https://login.salesforce.com/services/oauth2/token'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The source of <b>Customer</b> information'),
        '#required' => TRUE,
        '#weight' => 0
    ];

    $form['salesforce_settings']['sandbox_salesforce_settings']['SALESFORCE_SANDBOX_SETTINGS_USERNAME'] = [
        '#type' => 'textfield',
        '#title' => t('Remote End-Point username'),
        '#default_value' => variable_get('SALESFORCE_SANDBOX_SETTINGS_USERNAME', 'user.name@domain.com'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The username to logon to the remote webservice'),
        '#required' => TRUE,
        '#weight' => 1
    ];

    $form['salesforce_settings']['sandbox_salesforce_settings']['SALESFORCE_SANDBOX_SETTINGS_PASSWORD'] = [
        '#type' => 'password',
        '#title' => t('Remote End-Point password'),
        '#default_value' => variable_get('SALESFORCE_SANDBOX_SETTINGS_PASSWORD', ''),
        '#attributes' => array(
            'value' => variable_get('SALESFORCE_SANDBOX_SETTINGS_PASSWORD', ''), //'sms!p@ss'
        ),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The password to the remote webservice.'),
        '#weight' => 2
    ];

    $form['salesforce_settings']['sandbox_salesforce_settings']['SALESFORCE_SANDBOX_SETTINGS_GRANT_TYPE'] = [
        '#type' => 'radios',
        '#options' => ['password' => 'Password'],
        '#title' => t('Grant type'),
        '#default_value' => variable_get('SALESFORCE_SANDBOX_SETTINGS_GRANT_TYPE', 'password'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The grant type to use'),
        '#required' => TRUE,
        '#weight' => 3
    ];

    $form['salesforce_settings']['sandbox_salesforce_settings']['SALESFORCE_SANDBOX_SETTINGS_CLIENT_ID'] = [
        '#type' => 'textfield',
        '#title' => t('Client ID'),
        '#default_value' => variable_get('SALESFORCE_SANDBOX_SETTINGS_CLIENT_ID', 'ID'),
        '#size' => 100,
        '#maxlength' => 300,
        '#description' => t('The remote end-point client id.'),
        '#required' => TRUE,
        '#weight' => 4
    ];

    $form['salesforce_settings']['sandbox_salesforce_settings']['SALESFORCE_SANDBOX_SETTINGS_CLIENT_SECRET'] = [
        '#type' => 'textfield',
        '#title' => t('Client secrete'),
        '#default_value' => variable_get('SALESFORCE_SANDBOX_SETTINGS_CLIENT_SECRET', 'SECRET'),
        '#size' => 100,
        '#maxlength' => 300,
        '#description' => t('The remote end-point client secret.'),
        '#required' => TRUE,
        '#weight' => 5
    ];

    return system_settings_form($form);
}
