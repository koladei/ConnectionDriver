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
                        $msg->SentBy = 'kolade.ige';
                        $msg->BatchId = $batchId;
                        $msg->Status = 'pending';
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
                }
                case 'retry':{
                    break;
                }
                default:{
                    throw new \Exception("Sorry! the function '{$functionName}' is not supported yet.");
                }
            }
        } else {
            throw new \Exception('There was a problem getting the connection token');
        }
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
        $obj->url = 'https://api.ozinta.com/v3/sms/simple';
        $obj->defaultSenderId = 'MAINONE';
        $obj->providerName = 'smstorrent';
        $obj->messageLogDriverName = 'sql';

        return $obj;
    }
}
