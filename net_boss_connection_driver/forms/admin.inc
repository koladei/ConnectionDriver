<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function net_boss_connection_driver_form_settings_provider_admin_alter(&$form, &$form_state) {
    
    return system_settings_form($form);
}
