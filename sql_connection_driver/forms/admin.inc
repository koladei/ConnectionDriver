<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function sql_connection_driver_form_settings_provider_admin_alter(&$form, &$form_state) {
    //variable_del('SQL_SETTINGS_GROUPS');
    
    $ser = serialize(['default'=>'DEFAULT']);
    $def = variable_get('SQL_SETTINGS_GROUPS', $ser);  

    $form['sql_settings'] = [
        '#type' => 'fieldset'
        , '#title' => t('SQL Connection settings')
        , '#description' => t('A sample configuration value')
        , '#collapsible' => TRUE
        , '#group' => 'general_settings'
        , '#summary' => 'My summary'
        , '#attached' => [
            'js' => [
                'vertical-tabs' => drupal_get_path('module', 'sql_connection_driver') . '/forms/settings.js',
            ]
        ]
    ];

    $form['sql_settings']['groups'] = [
        '#type' => 'markup'
        , '#markup' => ''
    ];

    $form['sql_settings']['SQL_SETTINGS_GROUPS'] = [
        '#type' => 'hidden'
        , '#default_value' => $def 
    ];

    $form['sql_settings'][] = [
        '#type' => 'fieldset'
        , '#title' => t('New data source')
        , 'SQL_SETTINGS_NEW_GROUP' => [
            '#type' => 'textfield'
        ]
        , 'SQL_SETTINGS_NEW_GROUP_ACTION' => [
            '#type' => 'submit'
            , '#value' => 'ADD NEW DATASOURCE'
            , '#submit' => ['sql_conneciton_driver_settings_add']
        ]
    ];
  
    $groups = unserialize($def);

    foreach($groups as $key => $value){
        $form['sql_settings']['groups']["{$key}"] = [
            '#type' => 'fieldset'
            , '#title' => "{$value} : ({$key})"
            , '#collapsible' => TRUE
            , '#collapsed' => TRUE
        ];

        $form['sql_settings']['groups']["{$key}"]["SQL_SETTINGS_ALIAS_{$key}"] = [
            '#type' => 'textfield'
            , '#title' => t('Connection alias')
            , '#default_value' => variable_get("SQL_SETTINGS_ALIAS_{$key}", $value)
            , '#description' => t('The unique alias name for this datasource')
            , '#size' => 100
            , '#maxlength' => 100
        ];

        $form['sql_settings']['groups']["{$key}"]["SQL_SETTINGS_DSN_{$key}"] = [
            '#type' => 'textfield'
            , '#title' => t('DSN')
            , '#default_value' => variable_get("SQL_SETTINGS_DSN_{$key}", '')
            , '#description' => t('The DSN string for this datasource')
            , '#size' => 100
            , '#maxlength' => 100
        ];

        $form['sql_settings']['groups']["{$key}"]["SQL_SETTINGS_USERNAME_{$key}"] = [
            '#type' => 'textfield'
            , '#title' => t('Username')
            , '#default_value' => variable_get("SQL_SETTINGS_USERNAME_{$key}", '')
            , '#description' => t('The username to connect to the datasource')
            , '#size' => 100
            , '#maxlength' => 100
        ];

        $form['sql_settings']['groups']["{$key}"]["SQL_SETTINGS_PASSWORD_{$key}"] = [
            '#type' => 'password'
            , '#title' => t('Password')
            , '#default_value' => variable_get("SQL_SETTINGS_PASSWORD_{$key}", '')
            , '#description' => t('The password to connect to the datasource ')
            , '#attributes' => [
                'value' => variable_get("SQL_SETTINGS_PASSWORD_{$key}", '')
            ]
            , '#size' => 100
            , '#maxlength' => 100
        ];

        $form['sql_settings']['groups']["{$key}"]["SQL_SETTINGS_DBTYPE_{$key}"] = [
            '#type' => 'select'
            , '#title' => t('Database')
            , '#default_value' => variable_get("SQL_SETTINGS_DBTYPE_{$key}", '')
            , '#options' => ['mysql'=>t('My SQL'), 'mssql'=>t('Microsoft SQL')]
            , '#description' => t('The type of database')
        ];

        $form['sql_settings']['groups']["{$key}"]["SQL_SETTINGS_DELETE_{$key}"] = [
            '#type' => 'checkbox'
            , '#title' => t('Delete this data source?')
            , '#default_value' => FALSE
            , '#description' => t('If checked, this data source will be removed on save.')
        ];
    }

    $form['#submit'][] = 'sql_connection_driver_form_settings_provider_admin_submit';

    return system_settings_form($form);
}

function sql_connection_driver_form_settings_provider_admin_submit($form, &$form_state) {

    $groups = sql_conneciton_driver_settings_groups();
    foreach($groups as $key => &$name){  
        if(variable_get("SQL_SETTINGS_DELETE_{$key}", FALSE) == FALSE){                    
            // Update the display name              
            $x = variable_get("SQL_SETTINGS_ALIAS_{$key}");
            $name = strlen($x) > 0 ? $x: 'NEW SOURCE';
        } else{

            // Remove unwanted sources
            sql_conneciton_driver_settings_groups_delete($key);
            unset($groups[$key]);
        }
    }

    sql_conneciton_driver_settings_groups_save($groups);
}

function sql_conneciton_driver_settings_groups_save($groups){
    $ser = serialize($groups);
    variable_set('SQL_SETTINGS_GROUPS', $ser);
}

function sql_conneciton_driver_settings_groups_delete($key){
    variable_del("SQL_SETTINGS_ALIAS_{$key}");
    variable_del("SQL_SETTINGS_DSN_{$key}");
    variable_del("SQL_SETTINGS_USERNAME_{$key}");
    variable_del("SQL_SETTINGS_PASSWORD_{$key}");
    variable_del("SQL_SETTINGS_DBTYPE_{$key}");
    variable_del("SQL_SETTINGS_DELETE_{$key}");
}

function sql_conneciton_driver_settings_groups(){
    $ser = serialize(['default'=>'DEFAULT']);
    $def = variable_get('SQL_SETTINGS_GROUPS', $ser);  
    return unserialize($def);
}

function sql_conneciton_driver_settings_add($form, &$form_state) {
    // Get the existing list existing drivers.
    $ser = serialize(['default'=>'DEFAULT']);
    $def = variable_get('SQL_SETTINGS_GROUPS', $ser);
    $groups = unserialize($def);
    
    // Add the new group to the list
    $name = $form_state['values']['SQL_SETTINGS_NEW_GROUP'];
    if(!array_key_exists($name, $groups)){
        $groups[strtolower($name)] = $name;

        $ser = serialize($groups);
        variable_set('SQL_SETTINGS_GROUPS', $ser);
    }
}