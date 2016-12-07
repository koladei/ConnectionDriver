<?php

function settings_provider_admin() {
    
    $form['general_settings'] = array(
        '#type' => 'vertical_tabs',
        '#title' => t('General settings'),
        '#description' => t('These settings are common to all connection drivers.'),
    );

    return system_settings_form($form);
}
