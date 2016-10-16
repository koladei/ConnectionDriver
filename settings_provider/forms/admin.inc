<?php

function settings_provider_admin() {
    
    $form['general_settings'] = array(
        '#type' => 'fieldset',
        '#title' => t('General settings'),
        '#description' => t('A sample configuration value'),
    );

    return system_settings_form($form);
}
