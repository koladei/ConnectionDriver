<?php

/**
 * Implements hook_form_FORM_ID_alter
 * @return type
 */
function dynamics_ax_settings_provider_form_settings_provider_admin_alter(&$form, &$form_state) {

    if (variable_get('DYNAMICS_AX_SETTINGS_ENDPOINT_URL', FALSE) == FALSE) {
        variable_set('DYNAMICS_AX_SETTINGS_ENDPOINT_URL', variable_get('MWARE_FINANCE_REMOTE_ENDPOINT'));
        variable_set('DYNAMICS_AX_SETTINGS_TABLE_FUNCTION', variable_get('MWARE_FINANCE_REMOTE_ENDPOINT_FUNCTION'));
        variable_set('DYNAMICS_AX_SETTINGS_ENTITIES', variable_get('MWARE_FINANCE_DEFAULT_ENTITY'));
    }

    $form['dynamics_ax_settings'] = array(
        '#type' => 'fieldset',
        '#title' => t('Dynamics AX connection settings'),
    );

    $form['dynamics_ax_settings']['DYNAMICS_AX_SETTINGS_ENDPOINT_URL'] = array(
        '#type' => 'textfield',
        '#title' => t('Remote End-Point URL'),
        '#default_value' => variable_get('DYNAMICS_AX_SETTINGS_ENDPOINT_URL', 'http://service.domain.com/ERP/CPortalService.svc'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t("The web service that provides the information consumed and delivered by the <b>Web Service End-Point</b>."),
        '#required' => TRUE,
    );


    $form['dynamics_ax_settings']['DYNAMICS_AX_SETTINGS_TABLE_FUNCTION'] = array(
        '#type' => 'textfield',
        '#title' => t('Remote End-Point URL'),
        '#default_value' => variable_get('DYNAMICS_AX_SETTINGS_TABLE_FUNCTION', 'ExecuteQuery'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t("The function of the remote end-point to invoke."),
        '#required' => TRUE,
    );

    $form['dynamics_ax_settings']['DYNAMICS_AX_SETTINGS_ENTITIES'] = array(
        '#type' => 'textfield',
        '#title' => t('Entities'),
        '#default_value' => variable_get('DYNAMICS_AX_SETTINGS_ENTITIES', 'MONG,MOSC,MOMU'),
        '#size' => 100,
        '#maxlength' => 100,
        '#description' => t('The financial business entities to query for information.'),
        '#required' => TRUE,
    );

    return system_settings_form($form);
}
