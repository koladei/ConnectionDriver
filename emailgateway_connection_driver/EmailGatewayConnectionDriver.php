<?php

namespace com\mainone\middleware;

use com\mainone\middleware\MiddlewareConnectionDriver;
use com\mainone\middleware\MiddlewareFilter;
use com\mainone\middleware\EntityDefinitionBrowser;
use ExchangeWebServices;
use EWSType_FindItemType;
use EWSType_ItemResponseShapeType;
use EWSType_DefaultShapeNamesType;
use EWSType_NonEmptyArrayOfBaseFolderIdsType;
use EWSType_DistinguishedFolderIdType;
use EWSType_ItemQueryTraversalType;
use EWSType_DistinguishedFolderIdNameType;
use EWSType_FieldOrderType;
use EWSType_NonEmptyArrayOfFieldOrdersType;
use EWSType_GetItemType;
use EWSType_BodyTypeResponseType;
use EWSType_PathToUnindexedFieldType;
use EWSType_NonEmptyArrayOfPathsToElementType;
use EWSType_NonEmptyArrayOfBaseItemIdsType;
use EWSType_ItemIdType;
use EWSType_MessageType;
use EWSType_EmailAddressType;
use EWSType_BodyType;
use EWSType_SingleRecipientType;
use EWSType_CreateItemType;
use EWSType_NonEmptyArrayOfAllItemsType;
use \PDO;

/**
 * Description of EmailGatewayConnectionDriver
 *
 * @author Kolade.Ige
 */
class EmailGatewayConnectionDriver extends MiddlewareConnectionDriver
{
    private $serviceRef = NULL;

    /**
     * Instantiates and returns an instance of a EmailGatewayConnectionDriver
     *
     * @param callable $driverLoader A callable reference that can be used to retrieve data that can be found in other connnection driver instances.
     * @param callable $sourceLoader A callable reference that can be used to load data from various named connections within the current driver.
     */
    public function __construct(callable $driverLoader, callable $sourceLoader, $identifier = __CLASS__)
    {
        parent::__construct($driverLoader, $sourceLoader, $identifier);
    }

    /**
     * @override
     * Overrides the default implementation.
     *
     * @param \DateTime $value
     * @return void
     */
    protected function parseDateValue($value) {
        $type_1 = '/^([\d]{4})\-([\d]{2})\-([\d]{2})T([\d]{2})\:([\d]{2})\:([\d]{2})Z$/';
        $type_3 = '/^([\d]{4})\\-([\d]{2})\-([\d]{2})$/';

        if (preg_match($type_3, $value) == 1) {
            return \DateTime::createFromFormat('Y-m-d', $value);
        } else if (preg_match($type_1, $value) == 1) {
            $value = substr($value, 0, strpos($value, 'Z'));
            return \DateTime::createFromFormat('Y-m-d\TH:i:s', $value);
        }

        throw new \Exception("The date / datetime format is not known. {$value}");
    }

    public function executeFunctionInternal($functionName, array $objects = [], &$connectionToken = null, array $otherOptions = [])
    {
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
            switch ($functionName) {

                case 'sendEmailMessage': {
                    return $this->sendEmail($objects, $connectionToken, $otherOptions);
                }
                default:{
                    throw new \Exception("Sorry! the function '{$functionName}' is not supported yet.");
                }
            }
        } else {
            throw new \Exception('There was a problem getting the connection token');
        }
    }    

    // public function executeEntityFunctionInternal($entityBrowser, $functionName, array $objects = [], &$connectionToken = null, array $otherOptions = [])
    // {
    //     if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
    //         switch ($functionName) {
    //             case 'sendEmailMessage': {
    //                 return $this->markMessageAsRead($objects, $connectionToken, $otherOptions);
    //             }
    //             default:{
    //                 throw new \Exception("Sorry! the function '{$functionName}' is not supported yet.");
    //             }
    //         }
    //     } else {
    //         throw new \Exception('There was a problem getting the connection token');
    //     }
    // }
    
    public function executeEntityItemFunctionInternal($entityBrowser, $id, $functionName, array $data = [], &$connectionToken = null, array $otherOptions = [])
    {
        if (($connectionToken = (!is_null($connectionToken) ? $connectionToken : $this->getConnectionToken()))) {
            switch ($functionName) {
                case 'markMessageAsRead': {
                    return $this->markMessageAsRead($objects, $connectionToken, $otherOptions);
                }
                default:{
                    throw new \Exception("Sorry! the function '{$functionName}' is not supported yet.");
                }
            }
        } else {
            throw new \Exception('There was a problem getting the connection token');
        }
    }

    public function getItemsInternal($entityBrowser, &$ews = null, array $select, $filter, $expands = [], $otherOptions = []){
        // Get a connection reference
        if (($ews = (!is_null($ews) ? $ews : $this->getConnectionToken()))) {

            // $this->sendEmail(['to'=>'kolade.ige@mainone.net', 'cc'=>'theophilus.ajayi@mainone.net', 'bc'=>'amir.sanni@mainone.net', 'subject' => 'I am here', 'body' => 'Na wa o'], $ews);
                
            $request = new EWSType_FindItemType();
    
            $request->ItemShape = new EWSType_ItemResponseShapeType();
            $request->ItemShape->BaseShape = EWSType_DefaultShapeNamesType::ALL_PROPERTIES;    
            $request->Traversal = EWSType_ItemQueryTraversalType::SHALLOW;    
            $request->ParentFolderIds = new EWSType_NonEmptyArrayOfBaseFolderIdsType();
            $request->ParentFolderIds->DistinguishedFolderId = new EWSType_DistinguishedFolderIdType();
            $request->ParentFolderIds->DistinguishedFolderId->Id = EWSType_DistinguishedFolderIdNameType::INBOX;
    
            $request->QueryString = "isread:FALSE";    
    
            // sort order
            $request->SortOrder = new EWSType_NonEmptyArrayOfFieldOrdersType();
            $request->SortOrder->FieldOrder = [];
            $order = new EWSType_FieldOrderType();
            $order->FieldURI = new \stdClass();
    
            // sorts mails so that oldest appear first
            // more field uri definitions can be found from types.xsd (look for UnindexedFieldURIType)
            $order->FieldURI->FieldURI = 'item:DateTimeReceived';
            $order->Order = 'Ascending';
            $request->SortOrder->FieldOrder[] = $order;
    
            // Bind the Inbox folder to the service object.
            $response = $ews->FindItem($request);            
    
            // Check if the response from exchange contains email messages
            if (property_exists($response->ResponseMessages->FindItemResponseMessage->RootFolder->Items, 'Message')) {
    
                // If the search returns only a single mail, convert it into an array with only one item.
                if (!is_array($response->ResponseMessages->FindItemResponseMessage->RootFolder->Items->Message)) {
                    $response->ResponseMessages->FindItemResponseMessage->RootFolder->Items->Message = [$response->ResponseMessages->FindItemResponseMessage->RootFolder->Items->Message];
                }

                $messages = [];
                // Loop over the message array
                foreach ($response->ResponseMessages->FindItemResponseMessage->RootFolder->Items->Message as $message) {
                    
                    try {
                        if (!is_null($message) && property_exists($message, 'ItemId')) {
                            $m = new \stdClass();
                            $m = $this->getMessageById($message->ItemId->Id);
                            if(!is_null($m)){
                                $m->ChangeKey = $message->ItemId->ChangeKey;
                                $m->Id = $message->ItemId->Id;

                                // Fix copied recipients
                                if(!property_exists($m, 'CcRecipients')){
                                    $m->CcRecipients = new \stdClass();
                                    $m->CcRecipients->MailBox = [];
                                } else if(property_exists($m->CcRecipients, 'MailBox')){
                                    $m->CcRecipients->MailBox = [];
                                }
                                foreach($m->CcRecipients->Mailbox as &$r){
                                    $r = $r->EmailAddress;
                                }

                                $m->CcRecipients = \strtolower(implode(';', $m->CcRecipients->Mailbox));

                                foreach($m->ToRecipients->Mailbox as &$r){
                                    $r = $r->EmailAddress;
                                }
                                $m->ToRecipients = \strtolower(implode(';', $m->ToRecipients->Mailbox));
                                $m->Sender = \strtolower($m->From->Mailbox->EmailAddress);
                                $m->BodyType = $m->Body->BodyType;
                                $m->Body = $m->Body->_;

                                $messages[] = $m;
                            }
                        }
                    } catch (Exception $exc) {
                        // Do nothing. Try again next time.
                    }
                }
                return $messages;
            } else {
                return [];
            }
        }
    }

    private function getMessageById($message_id) {        
        if ($ews =  $this->getConnectionToken()) {
            // Build the request for the parts.
            $request = new EWSType_GetItemType();
            $request->ItemShape = new EWSType_ItemResponseShapeType();
            $request->ItemShape->BaseShape = EWSType_DefaultShapeNamesType::DEFAULT_PROPERTIES;
        
            // You can get the body as HTML, text or "best".
            $request->ItemShape->BodyType = EWSType_BodyTypeResponseType::TEXT;
        
            // Add the body property.
            $body_property = new EWSType_PathToUnindexedFieldType();
            $body_property->FieldURI = 'item:Body';
            $request->ItemShape->AdditionalProperties = new EWSType_NonEmptyArrayOfPathsToElementType();
            $request->ItemShape->AdditionalProperties->FieldURI = array($body_property);
        
            $request->ItemIds = new EWSType_NonEmptyArrayOfBaseItemIdsType();
            $request->ItemIds->ItemId = array();
        
            // Add the message to the request.
            $message_item = new EWSType_ItemIdType();
            $message_item->Id = $message_id;
            $request->ItemIds->ItemId[] = $message_item;
        
            $response = $ews->GetItem($request);
            return $response->ResponseMessages->GetItemResponseMessage->Items->Message;
        }
        // This should never happen
        return NULL;
    }

    private function sendEmail($message = [], $ews = NULL){
        if(!isset($message['to'])){
            throw new \Exception('Parameter \'to\' is required');
        }
        $tos = is_string($message['to'])?explode(',', $message['to']):$message['to'];

        if(!isset($message['subject'])){
            throw new \Exception('Parameter \'subject\' is required');
        }
        $subject = $message['subject'];

        if(!isset($message['body'])){
            throw new \Exception('Parameter \'body\' is required');
        }
        $body = $message['body'];

        $ccs = isset($message['cc'])?(is_string($message['cc'])?explode(',', $message['cc']):$message['cc']):[];
        $bccs = isset($message['bc'])?(is_string($message['bc'])?explode(',', $message['bc']):$message['bc']):[];


        if (($ews = (!is_null($ews) ? $ews : $this->getConnectionToken()))) {

            $msg = new EWSType_MessageType();
            
            $toAddresses = [];
            foreach($tos as $to){
                $toAdd = new EWSType_EmailAddressType();
                $toAdd->EmailAddress = $to;
                $toAddresses[] = $toAdd;
            }
            
            $ccAddresses = [];
            foreach($ccs as $cc){
                $toAdd = new EWSType_EmailAddressType();
                $toAdd->EmailAddress = $cc;
                $ccAddresses[] = $toAdd;
            }
            
            // $bccAddresses = [];
            // foreach($bccs as $to){
            //     $toAdd = new EWSType_EmailAddressType();
            //     $toAdd->EmailAddress = $to;
            //     $bccAddresses[] = $toAdd;
            // }

            $msg->ToRecipients = $toAddresses;
            $msg->CcRecipients = $ccAddresses;
            // $msg->BcRecipients = $bccAddresses;
            
            $fromAddress = new EWSType_EmailAddressType();
            $fromAddress->EmailAddress = isset($message['from'])?$message['from']:'kolade.ige@mainone.net';

            $msg->From = new EWSType_SingleRecipientType();
            $msg->From->Mailbox = $fromAddress;
            
            $msg->Subject = $subject;
            
            $msg->Body = new EWSType_BodyType();
            $msg->Body->BodyType = 'HTML';
            $msg->Body->_ = $body;

            $msgRequest = new EWSType_CreateItemType();
            $msgRequest->Items = new EWSType_NonEmptyArrayOfAllItemsType();
            $msgRequest->Items->Message = $msg;
            $msgRequest->MessageDisposition = 'SendAndSaveCopy';
            $msgRequest->MessageDispositionSpecified = true;
                    
            $response = $ews->CreateItem($msgRequest);
        }
    }

    public function markMessageAsRead($objects, $ews= NULL, $otherOptions = []) {    
        if(!isset($objects['message_id'])){
            throw new \Exception('Parameter \'message_id\' is required');
        }
        $change_key = $objects['message_id'];

        if(!isset($objects['change_key'])){
            throw new \Exception('Parameter \'change_key\' is required');
        }
        $change_key = $objects['change_key'];

        if (!is_null($msg)) {    
            if ($ews = (is_null($ews)? $this->getConnectionToken():$ews)) {
                $request = new EWSType_UpdateItemType();
                
                $request->SendMeetingInvitationsOrCancellations = 'SendToNone';
                $request->MessageDisposition = 'SaveOnly';
                $request->ConflictResolution = 'AlwaysOverwrite';
                $request->ItemChanges = [];
    
                // Build out item change request.
                $change = new EWSType_ItemChangeType();
                $change->ItemId = new EWSType_ItemIdType();
                $change->ItemId->Id = $message_id;
                $change->ItemId->ChangeKey = $change_key;
    
                // Build the set item field object and set the item on it.
                $field = new EWSType_SetItemFieldType();
                $field->FieldURI = new EWSType_PathToUnindexedFieldType();
                $field->FieldURI->FieldURI = "message:IsRead";
                $field->Message = new EWSType_MessageType();
                $field->Message->IsRead = true;
    
                $change->Updates->SetItemField[] = $field;
                $request->ItemChanges[] = $change;
    
                $response = $ews->UpdateItem($request);

                return $response;
            }            
        }
        throw new \Exception('Null message received.');
    }
    
    function deliverEmail($objects, $connectionToken = NULL, $otherOptions = []){
        // Require the recipients
        if (!isset($objects['batchid'])) {
            throw new \Exception("Parameter 'batchid' is required.");
        }

        // Run the check in a separated thread.
        // $thread = new SMSDeliveryStatusChecker($objects, $connectionToken);
        // $thread->start();

        return $this->getItems(
            'smslog', 
            'Id,Delivered,Recipient,Status,LastChecked,Provider/[Name],SentAs,SentBy/[DisplayName]', 
            "BatchId eq '{$objects['batchid']}'", 
            'SentBy,Provider', []
        );
    }

    public function getStringer()
    {
        return MiddlewareFilter::SQL;
    }

    private function getConnectionToken($sourceName = NULL) {
        try {
            $sourceLoader = $this->sourceLoader;
            $settings = $sourceLoader($sourceName);

            $ews = new ExchangeWebServices($settings->server, $settings->username, $settings->password, ExchangeWebServices::VERSION_2010_SP2);
            $this->serviceRef = $ews;
            return $ews;
            // return $settings;            
        } catch (Exception $x) {
            return FALSE;
        }
    }   
}