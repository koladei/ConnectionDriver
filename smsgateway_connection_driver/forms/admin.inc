<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function smsgateway_connection_driver_form_settings_provider_admin_alter(&$form, &$form_state) {
    $smsgatewayupdateInfo = variable_get('SMSGATEWAY_UPDATEINFO', [
        'lastrun' => '1998-01-01T00:00:00'
        , 'frequency_min' => 30
        , 'frequency_sec' => 30
        , 'providers' => []
    ]);

    $form['#after_build'][] = 'ace_editor_node_block_edit_form_attach_js';
    $form['SMSGATEWAY_UPDATEINFO'] = [
        '#type'=>'fieldset'
        , '#tree' => TRUE
        , '#title' => t('SMS Driver settings')
        , '#group' => 'general_settings'
        , '#attached' => [
            'js' => [
                'vertical-tabs' => drupal_get_path('module', 'smsgateway_connection_driver') . '/forms/settings.js',
            ]
        ]
    ];
    
    $form['SMSGATEWAY_UPDATEINFO']['frequency_min'] = [
        '#type' => 'textfield'
        , '#title' => 'SMS Delivery Frequency (min)'
        , '#description' => 'How frequently should the system check for SMS delivery status'
        , '#default_value' => $smsgatewayupdateInfo['frequency_min']
    ];
    
    $form['SMSGATEWAY_UPDATEINFO']['frequency_sec'] = [
        '#type' => 'textfield'
        , '#title' => 'SMS Delivery Frequency (sec)'
        , '#description' => 'How frequently should the system check for SMS delivery status'
        , '#default_value' => $smsgatewayupdateInfo['frequency_sec']
    ];
    
    $form['SMSGATEWAY_UPDATEINFO']['lastrun'] = [
        '#type' => 'textfield'
        , '#title' => 'Last run'
        , '#default_value' => $smsgatewayupdateInfo['lastrun']
    ];
    
    $form['SMSGATEWAY_UPDATEINFO']['providers'] = [
        '#type' => 'markup'
        , '#title' => t('SMS Providers')
    ];
    
    $form['SMSGATEWAY_UPDATEINFO']['providers']['addnew1'] = [
        '#type' => 'submit'
        , '#value' => t('Add Provider')
        , '#submit' => ['smsgateway_connection_driver__add_sms_provider']
    ];

    $sql = mware_connection_driver__get_driver('smsgateway');
    $providers = $sql->getItems('smsgatewayinfo', 'Name,Description,DeliveryLogic,ReportLogic', '', '', ['$all' => '1']);

    // Display each of the SMS providers in the database.
    foreach($providers as $provider) {      
        $form['SMSGATEWAY_UPDATEINFO']['providers'][$provider->Id] = [
            '#type' => 'fieldset'
            , '#title' => t($provider->Name)
            , '#description' => t('Define how to communicate with the remote gateway.')
            , '#collapsible' => TRUE
            , '#collapsed' => TRUE
            , '#tree' => TRUE
        ];
        $thisProvider = &$form['SMSGATEWAY_UPDATEINFO']['providers'][$provider->Id];
        if(!isset($smsgatewayupdateInfo['providers'][$provider->Id])){
            $smsgatewayupdateInfo['providers'][$provider->Id] = [];
        }
        $thisProviderValues = $smsgatewayupdateInfo['providers'][$provider->Id];
        
        $thisProvider['name'] = [
            '#type' => 'textfield'
            , '#title' => t('Display name')
            , '#default_value' =>  $provider->Name//isset($thisProviderValues['name'])?$thisProviderValues['name']:''  
        ];
        
        $thisProvider['logic'] = [
            '#type' => 'textarea'
            , '#title' => t('Delivery logic')
            , '#default_value' => $provider->DeliveryLogic//isset($thisProviderValues['logic'])?$thisProviderValues['logic']:''    
            , '#attributes' => ['class' => ['filter-wrapper logic-editor']]    
        ];
        
        $thisProvider['report'] = [
            '#type' => 'textarea'
            , '#title' => t('Report logic')
            , '#default_value' => $provider->ReportLogic//isset($thisProviderValues['logic'])?$thisProviderValues['logic']:''    
            , '#attributes' => ['class' => ['filter-wrapper logic-editor']]    
        ];
        
        $thisProvider['description'] = [
            '#type' => 'textarea'
            , '#title' => t('Description')
            , '#default_value' => $provider->Description//isset($thisProviderValues['description'])?$thisProviderValues['description']:''    
        ];
        
        $thisProvider['save'] = [
            '#type' => 'submit'
            , '#value' => t('Update Provider')
            , '#submit' => ['smsgateway_connection_driver__update_sms_provider']
            , '#attributes' => ['provider-id' => [$provider->Id]]  
        ];
        
        $thisProvider['delete'] = [
            '#type' => 'submit'
            , '#value' => t('Delete Provider')
            , '#submit' => ['smsgateway_connection_driver__remove_sms_provider']
            , '#attributes' => ['provider-id' => [$provider->Id]]  
        ];
    }
    
    $form['SMSGATEWAY_UPDATEINFO']['providers']['addnew2'] = [
        '#type' => 'submit'
        , '#value' => t('Add Provider')
        , '#submit' => ['smsgateway_connection_driver__add_sms_provider']
    ];

    return system_settings_form($form);
}

function smsgateway_connection_driver__add_sms_provider($form, &$form_state){
    $sql = mware_connection_driver__get_driver('smsgateway');
    $provider = new \stdClass();
    $provider->Id = 'SPID'.time();
    $provider->Name = 'Untitled SMS Provider';
    $sql->createItem('smsgatewayinfo', $provider, [
        '$setId' => '1'
    ]);
}

function smsgateway_connection_driver__remove_sms_provider($form, &$form_state){
    $sql = mware_connection_driver__get_driver('smsgateway');
    
    if(!$sql){
        form_set_error($form_state['triggering_element']['#id'], 'There was a problem removing the SMS Provider');
        return;
    }

    $providerId = $form_state['triggering_element']['#attributes']['provider-id'][0];
    $provider = $sql->getItemById('smsgatewayinfo', $providerId, 'Name,Description', '');
    if($sql->deleteItem('smsgatewayinfo', $providerId)){
        drupal_set_message("The SMS Provider '{$provider->Name}' has been removed from the database.");
    }
}

function smsgateway_connection_driver__update_sms_provider($form, &$form_state){
    // Get a reference to the SMS driver
    $sql = mware_connection_driver__get_driver('smsgateway');
    
    if(!$sql){
        form_set_error($form_state['triggering_element']['#id'], 'There was a problem updating the SMS Provider');
        return;
    }

    // Get the information of the particular gateway to be updated.
    $providerId = $form_state['triggering_element']['#attributes']['provider-id'][0];
    $provider = $sql->getItemById('smsgatewayinfo', $providerId, 'Name,Description', '');

    $providerSettings = $form_state['values']['SMSGATEWAY_UPDATEINFO']['providers'][$provider->Id];
    $provider->Name = $providerSettings['name'];
    $provider->DeliveryLogic = $providerSettings['logic'];
    $provider->ReportLogic = $providerSettings['report'];
    $provider->Description = $providerSettings['description'];
    if($sql->updateItem('smsgatewayinfo', $providerId, $provider)){
        drupal_set_message("The SMS Provider '{$provider->Name}' was successfully updated.");
    }
}