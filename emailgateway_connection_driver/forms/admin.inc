<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function emailgateway_connection_driver_form_settings_provider_admin_alter(&$form, &$form_state) {
    $emailgatewayupdateInfo = variable_get('EMAILGATEWAY_UPDATEINFO', [
        'lastrun' => '1998-01-01T00:00:00'
        , 'frequency_min' => 30
        , 'frequency_sec' => 30
        , 'providers' => []
    ]);

    $form['EMAILGATEWAY_UPDATEINFO_X'] = [
        '#type' => 'fieldset',
        '#title' => t('Mailbox Settings'),
        '#description' => t('Provide information about the mailbox to connect to here.'),
        '#collapsible' => TRUE,
    ];

    $form['#after_build'][] = 'ace_editor_node_block_edit_form_attach_js';
    $form['EMAILGATEWAY_UPDATEINFO'] = [
        '#type'=>'fieldset'
        , '#tree' => TRUE
        , '#title' => t('Email Driver settings')
        , '#group' => 'general_settings'
        , '#attached' => [
            'js' => [
                'vertical-tabs' => drupal_get_path('module', 'emailgateway_connection_driver') . '/forms/settings.js',
            ]
        ]
    ];    

    $form['EMAILGATEWAY_UPDATEINFO_X']['MWARE_EMAIL_SMS_MAILBOX_SERVER'] = [
        '#type' => 'textfield',
        '#title' => t('Server host'),
        '#default_value' => variable_get('MWARE_EMAIL_SMS_MAILBOX_SERVER', FALSE),
        '#description' => t('The username to use to connect to the mailbox'),
        '#weight' => 0
    ];

    $form['EMAILGATEWAY_UPDATEINFO_X']['MWARE_EMAIL_SMS_MAILBOX_USER_NAME'] = [
        '#type' => 'textfield',
        '#title' => t('Username'),
        '#default_value' => variable_get('MWARE_EMAIL_SMS_MAILBOX_USER_NAME', FALSE),
        '#description' => t('The username to use to connect to the mailbox'),
        '#weight' => 0
    ];

    $form['EMAILGATEWAY_UPDATEINFO_X']['MWARE_EMAIL_SMS_MAILBOX_USER_PASSWORD'] = [
        '#type' => 'password',
        '#title' => t('Password'),
        '#default_value' => variable_get('MWARE_EMAIL_SMS_MAILBOX_USER_PASSWORD', ''),
        '#attributes' => array(
            'value' => variable_get('MWARE_EMAIL_SMS_MAILBOX_USER_PASSWORD', ''),
        ),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The password to the mailbox.'),
        '#weight' => 2
    ];
    
    $form['EMAILGATEWAY_UPDATEINFO']['frequency_min'] = [
        '#type' => 'textfield'
        , '#title' => 'Delivery Check Frequency (min)'
        , '#description' => 'How frequently should the system check for Email delivery status'
        , '#default_value' => $emailgatewayupdateInfo['frequency_min']
    ];
    
    $form['EMAILGATEWAY_UPDATEINFO']['frequency_sec'] = [
        '#type' => 'textfield'
        , '#title' => 'Delivery Check Frequency (sec)'
        , '#description' => 'How frequently should the system check for Email delivery status'
        , '#default_value' => $emailgatewayupdateInfo['frequency_sec']
    ];
    
    $form['EMAILGATEWAY_UPDATEINFO']['lastrun'] = [
        '#type' => 'textfield'
        , '#title' => 'Last run'
        , '#default_value' => $emailgatewayupdateInfo['lastrun']
    ];
    
    $form['somthing']['providers'] = [
        '#type' => 'markup'
        , '#title' => t('Email accounts')
    ];
    
    $form['EMAILGATEWAY_UPDATEINFO']['providers']['addnew1'] = [
        '#type' => 'submit'
        , '#value' => t('Add account')
        , '#submit' => ['emailgateway_connection_driver__add_email_provider']
    ];

    $sql = mware_connection_driver__get_driver('emailgateway');
    
    try {
        $providers = $sql->getItems('emailgatewayinfo', 'Name,Description', '', '', ['$all' => '1']);

        if(count($providers) > 1){        
        
            $form['EMAILGATEWAY_UPDATEINFO']['providers']['addnew1'] = [
                '#type' => 'submit'
                , '#value' => t('Add account')
                , '#submit' => ['emailgateway_connection_driver__add_email_provider']
            ];
        }

        // Display each of the SMS providers in the database.
        foreach($providers as $provider) {      
            $form['EMAILGATEWAY_UPDATEINFO']['providers'][$provider->Id] = [
                '#type' => 'fieldset'
                , '#title' => t($provider->Name)
                , '#description' => t('Define how to communicate with the remote gateway.')
                , '#collapsible' => TRUE
                , '#collapsed' => TRUE
                , '#tree' => TRUE
            ];
            $thisProvider = &$form['EMAILGATEWAY_UPDATEINFO']['providers'][$provider->Id];
            if(!isset($emailgatewayupdateInfo['providers'][$provider->Id])){
                $emailgatewayupdateInfo['providers'][$provider->Id] = [];
            }
            $thisProviderValues = $emailgatewayupdateInfo['providers'][$provider->Id];
            
            $thisProvider['name'] = [
                '#type' => 'textfield'
                , '#title' => t('Display name')
                , '#default_value' =>  $provider->Name//isset($thisProviderValues['name'])?$thisProviderValues['name']:''  
            ];
            
            // $thisProvider['logic'] = [
            //     '#type' => 'textarea'
            //     , '#title' => t('Delivery logic')
            //     , '#default_value' => $provider->Logic//isset($thisProviderValues['logic'])?$thisProviderValues['logic']:''    
            //     , '#attributes' => ['class' => ['filter-wrapper logic-editor']]    
            // ];
            
            // $thisProvider['report'] = [
            //     '#type' => 'textarea'
            //     , '#title' => t('Report logic')
            //     , '#default_value' => $provider->ReportLogic//isset($thisProviderValues['logic'])?$thisProviderValues['logic']:''    
            //     , '#attributes' => ['class' => ['filter-wrapper logic-editor']]    
            // ];
            
            $thisProvider['description'] = [
                '#type' => 'textarea'
                , '#title' => t('Description')
                , '#default_value' => $provider->Description//isset($thisProviderValues['description'])?$thisProviderValues['description']:''    
            ];
            
            $thisProvider['save'] = [
                '#type' => 'submit'
                , '#value' => t('Update Provider')
                , '#submit' => ['emailgateway_connection_driver__update_sms_provider']
                , '#attributes' => ['provider-id' => [$provider->Id]]  
            ];
            
            $thisProvider['delete'] = [
                '#type' => 'submit'
                , '#value' => t('Delete Provider')
                , '#submit' => ['emailgateway_connection_driver__remove_sms_provider']
                , '#attributes' => ['provider-id' => [$provider->Id]]  
            ];
        }
    }catch(\Exception $fmExp){
        
    }
        
    $form['EMAILGATEWAY_UPDATEINFO']['providers']['addnew2'] = [
        '#type' => 'submit'
        , '#value' => t('Add account')
        , '#submit' => ['emailgateway_connection_driver__add_email_provider']
    ];

    return system_settings_form($form);
}

function emailgateway_connection_driver__add_email_provider($form, &$form_state){
    $sql = mware_connection_driver__get_driver('emailgateway');
    $provider = new \stdClass();
    $provider->Id = 'SPID'.time();
    $provider->Name = 'Untitled SMS Provider';
    $sql->createItem('emailgatewayinfo', $provider, [
        '$setId' => '1'
    ]);
}

function emailgateway_connection_driver__remove_sms_provider($form, &$form_state){
    $sql = mware_connection_driver__get_driver('emailgateway');
    
    if(!$sql){
        form_set_error($form_state['triggering_element']['#id'], 'There was a problem removing the SMS Provider');
        return;
    }

    $providerId = $form_state['triggering_element']['#attributes']['provider-id'][0];
    $provider = $sql->getItemById('emailgatewayinfo', $providerId, 'Name,Description', '');
    if($sql->deleteItem('emailgatewayinfo', $providerId)){
        drupal_set_message("The Email Provider '{$provider->Name}' has been removed from the database.");
    }
}

function emailgateway_connection_driver__update_sms_provider($form, &$form_state){
    // Get a reference to the SMS driver
    $sql = mware_connection_driver__get_driver('emailgateway');
    
    if(!$sql){
        form_set_error($form_state['triggering_element']['#id'], 'There was a problem updating the SMS Provider');
        return;
    }

    // Get the information of the particular gateway to be updated.
    $providerId = $form_state['triggering_element']['#attributes']['provider-id'][0];
    $provider = $sql->getItemById('emailgatewayinfo', $providerId, 'Name,Description', '');

    $providerSettings = $form_state['values']['EMAILGATEWAY_UPDATEINFO']['providers'][$provider->Id];
    $provider->Name = $providerSettings['name'];
    $provider->DeliveryLogic = $providerSettings['logic'];
    $provider->ReportLogic = $providerSettings['report'];
    $provider->Description = $providerSettings['description'];
    if($sql->updateItem('emailgatewayinfo', $providerId, $provider)){
        drupal_set_message("The SMS Provider '{$provider->Name}' was successfully updated.");
    }
}