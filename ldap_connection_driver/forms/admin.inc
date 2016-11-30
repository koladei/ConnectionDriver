<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function ldap_connection_driver_form_settings_provider_admin_alter(&$form, &$form_state) {

    $form['dynamics_ax_settings'] = [
        '#type' => 'fieldset',
        '#title' => t('Salesforce connection settings'),
        '#description' => t('A sample configuration value'),
        '#collapsible' => TRUE,
    ];

    $form['dynamics_ax_settings']['DYNAMICS_AX_SETTINGS_USE_PRODUCTION'] = [
        '#type' => 'checkbox',
        '#title' => t('Use production settings'),
        '#default_value' => variable_get('DYNAMICS_AX_SETTINGS_USE_PRODUCTION', FALSE),
        '#description' => t('The source of <b>Customer</b> information'),
        '#weight' => 0
    ];

    $form['dynamics_ax_settings']['production_dynamics_ax_settings'] = [
        '#type' => 'fieldset',
        '#title' => t('Production Environment'),
        '#description' => t('Settings for the production environment'),
        '#collapsible' => TRUE,
    ];

    $form['dynamics_ax_settings']['sandbox_dynamics_ax_settings'] = [
        '#type' => 'fieldset',
        '#title' => t('Sandbox Environment'),
        '#description' => t('Settings for the sandbox environment'),
        '#collapsible' => TRUE,
    ];

    //    PRODUCTION SERVER SETTINGS
    $form['dynamics_ax_settings']['production_dynamics_ax_settings']['DYNAMICS_AX_SETTINGS_ENDPOINT_URL'] = [
        '#type' => 'textfield',
        '#title' => t('Remote End-Point URL'),
        '#default_value' => variable_get('DYNAMICS_AX_SETTINGS_ENDPOINT_URL', 'https://login.salesforce.com/services/oauth2/token'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The source of <b>Customer</b> information'),
        '#required' => TRUE,
        '#weight' => 0
    ];

    $form['dynamics_ax_settings']['production_dynamics_ax_settings']['DYNAMICS_AX_SETTINGS_USERNAME'] = [
        '#type' => 'textfield',
        '#title' => t('Remote End-Point username'),
        '#default_value' => variable_get('DYNAMICS_AX_SETTINGS_USERNAME', 'user.name@domain.com'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The username to logon to the remote webservice'),
        '#required' => TRUE,
        '#weight' => 1
    ];

    $form['dynamics_ax_settings']['production_dynamics_ax_settings']['DYNAMICS_AX_SETTINGS_PASSWORD'] = [
        '#type' => 'password',
        '#title' => t('Remote End-Point password'),
        '#default_value' => variable_get('DYNAMICS_AX_SETTINGS_PASSWORD', ''),
        '#attributes' => array(
            'value' => variable_get('DYNAMICS_AX_SETTINGS_PASSWORD', ''),
        ),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The password to the remote webservice.'),
        '#weight' => 2
    ];

    $form['dynamics_ax_settings']['production_dynamics_ax_settings']['DYNAMICS_AX_SETTINGS_TIMEOUT'] = [
        '#type' => 'radios',
        '#options' => ['password' => 'Password'],
        '#title' => t('Grant type'),
        '#default_value' => variable_get('DYNAMICS_AX_SETTINGS_TIMEOUT', 'password'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The grant type to use'),
        '#required' => TRUE,
        '#weight' => 3
    ];

    //    SANDBOX SERVER SETTINGS
    $form['dynamics_ax_settings']['sandbox_dynamics_ax_settings']['SALESFORCE_SANDBOX_SETTINGS_ENDPOINT_URL'] = [
        '#type' => 'textfield',
        '#title' => t('Remote End-Point URL'),
        '#default_value' => variable_get('SALESFORCE_SANDBOX_SETTINGS_ENDPOINT_URL', 'https://login.salesforce.com/services/oauth2/token'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The source of <b>Customer</b> information'),
        '#required' => TRUE,
        '#weight' => 0
    ];

    $form['dynamics_ax_settings']['sandbox_dynamics_ax_settings']['SALESFORCE_SANDBOX_SETTINGS_USERNAME'] = [
        '#type' => 'textfield',
        '#title' => t('Remote End-Point username'),
        '#default_value' => variable_get('SALESFORCE_SANDBOX_SETTINGS_USERNAME', 'user.name@domain.com'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The username to logon to the remote webservice'),
        '#required' => TRUE,
        '#weight' => 1
    ];

    $form['dynamics_ax_settings']['sandbox_dynamics_ax_settings']['SALESFORCE_SANDBOX_SETTINGS_PASSWORD'] = [
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

    $form['dynamics_ax_settings']['sandbox_dynamics_ax_settings']['DYNAMICS_AX_SETTINGS_TIMEOUT'] = [
        '#type' => 'radios',
        '#options' => ['password' => 'Password'],
        '#title' => t('Grant type'),
        '#default_value' => variable_get('DYNAMICS_AX_SETTINGS_TIMEOUT', 'password'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The grant type to use'),
        '#required' => TRUE,
        '#weight' => 3
    ];

    return system_settings_form($form);
}
