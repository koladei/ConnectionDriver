<?php

namespace com\mainone\middleware;

use com\mainone\middleware\MiddlewareConnectionDriver;
use com\mainone\middleware\MiddlewareFilter;
use com\mainone\middleware\EntityDefinitionBrowser;
use \PDO;

/**
 * Description of SMSGatewayConnectionDriver
 *
 * @author Kolade.Ige
 */
class SMSGatewayConnectionDriver extends MiddlewareConnectionDriver
{

    /**
     * Instantiates and returns an instance of a SMSGatewayConnectionDriver
     *
     * @param callable $driverLoader A callable reference that can be used to retrieve data that can be found in other connnection driver instances.
     * @param callable $sourceLoader A callable reference that can be used to load data from various named connections within the current driver.
     */
    public function __construct(callable $driverLoader, callable $sourceLoader, $identifier = __CLASS__)
    {
        parent::__construct($driverLoader, $sourceLoader, $identifier);
    }

    public function executeFunctionInternal($functionName, array $objects = [], &$connectionToken = null, array $otherOptions = [])
    {
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
            global $base_url;
            $return = new \stdClass();
            $authorization = \base64_encode("{$connectionToken->username}:{$connectionToken->password}");
            // $sql = self::loadDriver($connectionToken->messageLogDriverName);
            
            // return $this->getItems('smslog', 'Id,Delivered,Status,SentBy/[DisplayName]', "BatchId eq 'MIDDLEWARE-DEV.MAINONE.NET-20171009153449'", 'SentBy');

            switch ($functionName) {

                case 'sendsms': {
                    return $this->deliverSMS($objects, $connectionToken, $otherOptions);
                }
                case 'updatedeliverystatus':{
                    return $this->updateDeliveryStatus($objects, $connectionToken, $otherOptions);
                }
                case 'getdeliverystatus':{
                    return $this->getDeliveryStatus($objects, $connectionToken, $otherOptions);
                }
                default:{
                    throw new \Exception("Sorry! the function '{$functionName}' is not supported yet.");
                }
            }
        } else {
            throw new \Exception('There was a problem getting the connection token');
        }
    }
    
    function deliverSMS($objects, $connectionToken = NULL, $otherOptions = []){
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
            global $base_url;
            $return = new \stdClass();
            $authorization = \base64_encode("{$connectionToken->username}:{$connectionToken->password}");

            // Require the recipients
            if (!isset($objects['recipients'])) {
                throw new \Exception("Parameter 'recipients' is required.");
            }

            // Validate the recipients
            $recipients = is_string($objects['recipients'])?preg_split('/[\s*,\s*]*,+[\s*,\s*]*/', $objects['recipients']):(is_array($objects['recipients'])?$objects['recipients']:[]);
            if (count($recipients) < 1) {
                throw new \Exception("Parameter 'recipients' must contain at least 1 valid number");
            }
            
            // validate the message
            if (!isset($objects['message']) || strlen($objects['message']) < 1) {
                throw new \Exception("Parameter 'message' is required and cannot be empty.");
            }
            
            // Validate the sender id
            if (isset($objects['senderid']) && strlen($objects['senderid']) > 11) {
                throw new \Exception("Parameter 'senderid' cannot be longer than 11 characters");
            }
            $senderid = isset($objects['senderid'])?$objects['senderid']:$connectionToken->defaultSenderId;
            $batchId = strtoupper(trim(substr($base_url, 6), '/').'-'.(new \DateTime())->format('YmdHis'));
            
            $obj = new \stdClass();
            $obj->from = $senderid;
            $obj->to = $recipients;
            $obj->text = $objects['message'];
            $obj->bulkId = $batchId;

            foreach ($recipients as $recipient) {
                // Log the SMS to the database.
                $msg = new \stdClass();
                $msg->BatchId = $bulkId;
                $msg->Id = "{$batchId}-{$recipient}";
                $msg->Body = $objects['message'];
                $msg->Recipient = $recipient;
                $msg->From = $senderid;
                $msg->SentBy = 'appdev';
                $msg->BatchId = $batchId;
                $msg->Status = 'PENDING';
                $msg->SentThrough = $connectionToken->providerName;
                $x = $this->createItem('smslog', $msg, [
                    '$setId' => '1'
                ]);
            }
        
            // Try sending the SMS.
            $options = [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . $authorization
                    , 'Content-Type: application/json'
                    , 'Accept: application/json'
                ]
                , CURLOPT_PROTOCOLS => CURLPROTO_HTTPS
                , CURLOPT_SSL_VERIFYPEER => 0
                , CURLOPT_SSL_VERIFYHOST => 0
                , CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
                , CURLOPT_POSTFIELDS => json_encode($obj)
            ];

            // Execute the POST request.
            $feed = mware_blocking_http_request($connectionToken->url, ['options' => $options]);
        
            // Process the request
            $res = json_decode($feed->getContent());

            if ($res->status == 'success') {
                // Update the status of each message
                foreach ($res->data->messages as $message) {
                    $msg = new \stdClass();
                    $msg->SMSCount = $message->smsCount;
                    $msg->Status = $message->status->groupName;
                    $msg->RemoteId = $message->messageId;

                    $this->updateItem('smslog', "{$res->data->bulkId}-{$message->to}", $msg, []);
                }
            }

            // Return the status of the SMS.                    
            return [
                'batchId' => $batchId
            ];
        } else {
            throw new \Exception('There was a problem getting the connection token');
        }
    }
    
    function updateDeliveryStatus($objects = [], $connectionToken = NULL, $otherOptions = []){
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
            $now = new \DateTime();
            $authorization = \base64_encode("{$connectionToken->username}:{$connectionToken->password}");

            // Get all the messages that are pending by their batch id
            $batchIds = [];
            $pendingMessages = $this->getItems('smslog', 'BatchId', "Status eq 'PENDING'", '');     
            foreach($pendingMessages as $pendingMessage){
                if(!in_array($pendingMessage->BatchId, $batchIds)){
                    $batchIds[] = $pendingMessage->BatchId;
                }
            }
        
            // Try sending the SMS.
            $options = [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . $authorization
                    , 'Content-Type: application/json'
                    , 'Accept: application/json'
                ]
                , CURLOPT_PROTOCOLS => CURLPROTO_HTTPS
                , CURLOPT_SSL_VERIFYPEER => 0
                , CURLOPT_SSL_VERIFYHOST => 0
                , CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
            ];

            // Get the status of each batch
            foreach($batchIds as $batchId){
                try {
                    // Execute the POST request.
                    $feed = mware_blocking_http_request("{$connectionToken->reportUrl}?bulkId.eq={$batchId}", ['options' => $options]);
                
                    // Process the request
                    $res = json_decode($feed->getContent());

                    if ($res->status == 'success') {
                        try {
                            // Update the status of each message
                            foreach ($res->data->rows as $message) {
                                $msg = new \stdClass();
                                $msg->SMSCount = $message->smsCount;
                                $msg->Status = $message->statusGroup;
                                $msg->RemoteId = $message->msgid;
                                $msg->LastChecked = $now->format('Y-m-d\TH:i:s');
                                if($message->statusGroup == 'DELIVERED'){
                                    $msg->Delivered = \DateTime::createFromFormat('Y-m-d\TH:i:s', \substr($message->dateSent, 0, 19));
                                }
                                $this->updateItem('smslog', "{$batchId}-{$message->recipient}", $msg, []);
                            }
                        } catch (\Exception $e){}
                    }
                } catch (\Exception $e) {
                    watchdog('SMSGATEWAY', "There was a problem connecting to the service: {$e->getMessage()}", [], WATCHDOG_WARNING);
                }
            }
        } else {
            throw new \Exception('There was a problem getting the connection token');
        }
    }
    
    function getDeliveryStatus($objects, $connectionToken = NULL, $otherOptions = []){
        // Require the recipients
        if (!isset($objects['batchid'])) {
            throw new \Exception("Parameter 'batchid' is required.");
        }

        // $this->updateDeliveryStatus();
        return $this->getItems('smslog', 'Id,Delivered,Status,SentThrough,SentBy/[DisplayName]', "BatchId eq '{$objects['batchid']}'", 'SentBy', []);
    }

    public function getStringer()
    {
        return MiddlewareFilter::SQL;
    }

    public function getConnectionToken()
    {
        $obj = new \stdClass;
        $obj->username = 'licensemanager@mainone.net';
        $obj->password = 'Welcome@123';
        $obj->sendUrl = 'https://api.ozinta.com/v3/sms/simple';
        $obj->reportUrl = 'https://api.ozinta.com/v3/reports';
        $obj->defaultSenderId = 'MAINONE';
        $obj->providerName = 'smstorrent';
        $obj->messageLogDriverName = 'sql';

        return $obj;
    }
}