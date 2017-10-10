<?php

function settings_provider__update_sms_status()
{
    // Get all messages that are pending.
    $smsgateway = mware_connection_driver__get_driver('smsgateway');
    if(!is_null($smsgateway)){        
        $smsgateway->executeFunction('updatedeliverystatus');
    }
}
