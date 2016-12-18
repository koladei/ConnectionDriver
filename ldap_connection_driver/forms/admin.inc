<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function ldap_connection_driver_form_settings_provider_admin_alter(&$form, &$form_state) {

    $form['ldap_connection_driver_settings'] = [
        '#type' => 'fieldset'
        , '#title' => t('LDAP Server settings')
        , '#description' => t('Provide connection details for the LDAP server.')
        , '#collapsible' => TRUE
        , '#weight' => 0
        , '#group' => 'general_settings'
    ];

    $form['ldap_connection_driver_settings']['LDAP_CONNECTION_DRIVER_PROTOCOL'] = [
        '#type' => 'select'
        , '#title' => t('Protocol')
        , '#default_value' => variable_get('LDAP_CONNECTION_DRIVER_PROTOCOL', 'ldaps')
        , '#options' => ['ldaps' => 'Secure LDAP', 'ldap' => 'Basic LDAP']
        , '#description' => t('Choose one')
        , '#weight' => 1
    ];

    $form['ldap_connection_driver_settings']['LDAP_CONNECTION_DRIVER_HOST'] = [
        '#type' => 'textfield'
        , '#title' => t('LDAP Host')
        , '#default_value' => variable_get('LDAP_CONNECTION_DRIVER_HOST', 'hostname')
        , '#description' => t('Choose one')
        , '#weight' => 0
    ];

    $form['ldap_connection_driver_settings']['LDAP_CONNECTION_DRIVER_PORT'] = [
        '#type' => 'textfield'
        , '#title' => t('LDAP Port')
        , '#default_value' => variable_get('LDAP_CONNECTION_DRIVER_PORT', '636')
        , '#description' => t('Specify the port')
        , '#size' => 5
        , '#weight' => 2
    ];

    $form['ldap_connection_driver_settings']['LDAP_CONNECTION_DRIVER_USERNAME'] = [
        '#type' => 'textfield'
        , '#title' => t('Username')
        , '#default_value' => variable_get('LDAP_CONNECTION_DRIVER_USERNAME', '')
        , '#description' => t('The username to use to connect to the mailbox')
        , '#weight' => 3
    ];

    $form['ldap_connection_driver_settings']['LDAP_CONNECTION_DRIVER_PASSWORD'] = [
        '#type' => 'password'
        , '#title' => t('Password')
        , '#default_value' => variable_get('LDAP_CONNECTION_DRIVER_PASSWORD', '')
        , '#attributes' => [
            'value' => variable_get('LDAP_CONNECTION_DRIVER_PASSWORD', '')
        ]
        , '#size' => 100
        , '#maxlength' => 100
        , '#description' => t('The password to the mailbox.')
        , '#weight' => 4
    ];

    $form['ldap_connection_driver_settings']['LDAP_CONNECTION_DRIVER_DN'] = [
        '#type' => 'textfield'
        , '#title' => t('DN')
        , '#default_value' => variable_get('LDAP_CONNECTION_DRIVER_DN', 'DC=mainonecable,DC=com')
        , '#description' => t('Provide the connection DN')
        , '#weight' => 5
    ];

    return system_settings_form($form);
}
