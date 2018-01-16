<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function sharepoint_connection_driver_form_settings_provider_admin_alter(&$form, &$form_state) {
    //variable_del('SHAREPOINT_SETTINGS_GROUPS');
    
    // $ser = serialize(['default'=>'DEFAULT']);
    $groups = variable_get('SHAREPOINT_SETTINGS_GROUPS', []);  

    $form['sharepoint_settings'] = [
        '#type' => 'fieldset'
        , '#title' => t('Sharepoint Connection settings')
        , '#description' => t('A sample configuration value')
        , '#collapsible' => TRUE
        , '#tree' => TRUE
        , '#group' => 'general_settings'
        // , '#summary' => 'My summary'
        // , '#attached' => [
        //     'js' => [
        //         'vertical-tabs' => drupal_get_path('module', 'sharepoint_connection_driver') . '/forms/settings.js',
        //     ]
        // ]
    ];

    // $form['sharepoint_settings']['groups'] = [
    //     '#type' => 'markup'
    //     , '#markup' => ''
    // ];

    // $form['sharepoint_settings']['SHAREPOINT_SETTINGS_GROUPS'] = [
    //     '#type' => 'hidden'
    //     , '#default_value' => $def 
    // ];

    // $form['sharepoint_settings'][] = [
    //     '#type' => 'fieldset'
    //     , '#title' => t('New data source')
    //     , 'SHAREPOINT_SETTINGS_NEW_GROUP' => [
    //         '#type' => 'textfield'
    //     ]
    //     , 'SHAREPOINT_SETTINGS_NEW_GROUP_ACTION' => [
    //         '#type' => 'submit'
    //         , '#value' => 'ADD NEW DATASOURCE'
    //         , '#submit' => ['sharepoint_conneciton_driver_settings_add']
    //     ]
    // ];
  
    // $groups = unserialize($def);

    foreach($groups as $key => $value){

        $name = strtoupper($value['name']);
        $form['sharepoint_settings']["{$key}"] = [
            '#type' => 'fieldset'
            , '#title' => "{$name}"
            , '#collapsible' => TRUE
            , '#collapsed' => TRUE
        ];

        $form['sharepoint_settings']["{$key}"]["site_url"] = [
            '#type' => 'textfield'
            , '#title' => t('Site URL')
            , '#default_value' => isset($value['site_url'])?$value['site_url']:''
            , '#description' => t('Sharepoint site URL')
            , '#size' => 100
            , '#maxlength' => 100
        ];

        $form['sharepoint_settings']["{$key}"]["username"] = [
            '#type' => 'textfield'
            , '#title' => t('Username')
            , '#default_value' => isset($value['username'])?$value['username']:''
            , '#description' => t('Username')
            , '#size' => 100
            , '#maxlength' => 100
        ];

        $form['sharepoint_settings']["{$key}"]["password"] = [
            '#type' => 'textfield'
            , '#title' => t('Password')
            , '#default_value' => isset($value['password'])?$value['password']:''
            , '#description' => t('Username')
            , '#size' => 100
            , '#maxlength' => 100
        ];

        $form['sharepoint_settings']["{$key}"]['save'] = [
            '#type' => 'submit'
            , '#value' => 'Save'
            , '#submit' => ['sharepoint_conneciton_driver_settings_add']
        ];
    }

    $form['sharepoint_settings']['add'] = [
        '#type' => 'submit'
        , '#value' => 'Add'
        , '#submit' => ['sharepoint_conneciton_driver_settings_add']
    ];

    // $form['#submit'][] = 'sharepoint_connection_driver_form_settings_provider_admin_submit';

    return system_settings_form($form);
}

function sharepoint_conneciton_driver_settings_add($form, &$form_state) {
    // Get the existing list existing drivers.
    $name = 'default';
    $groups = variable_get('SHAREPOINT_SETTINGS_GROUPS', []);   
    $groups[$name] = [
        'name' => $name
    ];
    
    variable_set('SHAREPOINT_SETTINGS_GROUPS', $groups);    

    echo 'ACBNF';
}