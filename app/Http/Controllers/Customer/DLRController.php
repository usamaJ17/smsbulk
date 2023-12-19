<?php

namespace App\Http\Controllers\Customer;

use App\Events\MessageReceived;
use App\Http\Controllers\Controller;
use App\Library\SMSCounter;
use App\Models\Blacklists;
use App\Models\Campaigns;
use App\Models\ChatBox;
use Illuminate\Support\Facades\Log;
use App\Models\ChatBoxMessage;
use App\Models\ContactGroups;
use App\Models\Contacts;
use App\Models\CustomerBasedPricingPlan;
use App\Models\Keywords;
use App\Models\Notifications;
use App\Models\CampaignUser;
use App\Models\PhoneNumbers;
use App\Models\PlansCoverageCountries;
use App\Models\Reports;
use App\Models\Senderid;
use App\Models\SendingServer;
use App\Models\User;
use App\Repositories\Eloquent\EloquentCampaignRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Throwable;
use Twilio\TwiML\Messaging\Message;
use Twilio\TwiML\MessagingResponse;

class DLRController extends Controller
{

    /**
     * update dlr
     *
     * @param      $message_id
     * @param      $status
     *
     * @return mixed
     */
    public static function updateDLR($message_id, $status): mixed
    {

        $get_data = Reports::whereLike(['status'], $message_id)->firstOrFail();
        $get_data->update(['status' => $status.'|'.$message_id]);

        if ($get_data->campaign_id) {
            Campaigns::find($get_data->campaign_id)->updateCache();
        }

        return $status;

    }


    /**
     *twilio dlr
     *
     * @param  Request  $request
     *
     * @return string|void
     */
    public function dlrTwilio(Request $request)
    {
        $message_id = $request->input('MessageSid');
        $status     = $request->input('MessageStatus');

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'delivered' || $status == 'sent') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status);

    }

    /**
     * Route mobile DLR
     *
     * @param  Request  $request
     *
     * @return string|void
     */
    public function dlrRouteMobile(Request $request)
    {
        $message_id = $request->input('sMessageId');
        $status     = $request->input('sStatus');
        $sender_id  = $request->input('sSender');
        $phone      = $request->input('sMobileNo');

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'DELIVRD' || $status == 'ACCEPTED') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status, $sender_id, $phone);
    }


    /**
     * text local DLR
     *
     * @param  Request  $request
     *
     * @return string|void
     */
    public function dlrTextLocal(Request $request)
    {
        $message_id = $request->input('customID');
        $status     = $request->input('status');
        $phone      = $request->input('number');

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        $status = match ($status) {
            'D'     => 'Delivered',
            'U'     => 'Undelivered',
            'P'     => 'Pending',
            'I'     => 'Invalid',
            'E'     => 'Expired',
            default => 'Unknown',
        };

        $this::updateDLR($message_id, $status, null, $phone);
    }


    /**
     * Plivo DLR
     *
     * @param  Request  $request
     *
     * @return string|void
     */
    public function dlrPlivo(Request $request)
    {
        $message_id = $request->input('MessageUUID');
        $status     = $request->input('Status');
        $phone      = $request->input('To');
        $sender_id  = $request->input('From');

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'delivered' || $status == 'sent') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status, $phone, $sender_id);
    }

    /**
     * SMS Global DLR
     *
     * @param  Request  $request
     *
     * @return string|void
     */
    public function dlrSMSGlobal(Request $request)
    {
        $message_id = $request->input('msgid');
        $status     = $request->input('dlrstatus');

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'DELIVRD') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status);
    }


    /**
     * Advance Message System Delivery reports
     *
     * @param  Request  $request
     *
     * @return string|void
     */
    public function dlrAdvanceMSGSys(Request $request)
    {
        $message_id = $request->get('MessageId');
        $status     = $request->get('Status');
        $phone      = $request->get('Destination');
        $sender_id  = $request->get('Source');

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'DELIVRD') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status, $phone, $sender_id);
    }


    /**
     * nexmo now Vonage DLR
     *
     * @param  Request  $request
     *
     * @return string|void
     */
    public function dlrVonage(Request $request)
    {
        $message_id = $request->input('messageId');
        $status     = $request->input('status');
        $phone      = $request->input('msisdn');
        $sender_id  = $request->input('to');

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'delivered' || $status == 'accepted') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status, $phone, $sender_id);
    }

    /**
     * infobip DLR
     *
     * @param  Request  $request
     */
    public function dlrInfobip(Request $request)
    {
        $get_data = $request->getContent();

        $get_data = json_decode($get_data, true);
        if (isset($get_data) && is_array($get_data) && array_key_exists('results', $get_data)) {
            $message_id = $get_data['results']['0']['messageId'];

            foreach ($get_data['results'] as $msg) {

                if (isset($msg['status']['groupName'])) {

                    $status = $msg['status']['groupName'];

                    if ($status == 'DELIVERED') {
                        $status = 'Delivered';
                    }

                    $this::updateDLR($message_id, $status);
                }

            }
        }
    }

    public function dlrEasySendSMS(Request $request)
    {
        $message_id = $request->input('messageid');
        $status     = $request->input('status');
        $phone      = $request->input('mobile');
        $sender_id  = $request->input('sender');

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'delivered') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status, $phone, $sender_id);

        return $status;
    }


    /**
     * AfricasTalking delivery reports
     *
     * @param  Request  $request
     *
     * @return string|void
     */
    public function dlrAfricasTalking(Request $request)
    {
        $message_id = $request->input('id');
        $status     = $request->input('status');
        $phone      = str_replace(['(', ')', '+', '-', ' '], '', $request->input('phoneNumber'));

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'Success') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status, $phone);
    }


    /**
     * 1s2u delivery reports
     *
     * @param  Request  $request
     *
     * @return string|void
     */
    public function dlr1s2u(Request $request)
    {
        $message_id = $request->input('msgid');
        $status     = $request->input('status');
        $phone      = str_replace(['(', ')', '+', '-', ' '], '', $request->input('mno'));
        $sender_id  = $request->input('sid');

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'DELIVRD') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status, $phone, $sender_id);
    }


    /**
     * dlrKeccelSMS delivery reports
     *
     * @param  Request  $request
     *
     * @return string|void
     */
    public function dlrKeccelSMS(Request $request)
    {
        $message_id = $request->input('messageID');
        $status     = $request->input('status');

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'DELIVERED') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status);
    }

    /**
     * dlrGatewayApi delivery reports
     *
     * @param  Request  $request
     *
     * @return string|void
     */
    public function dlrGatewayApi(Request $request)
    {

        $message_id = $request->input('id');
        $status     = $request->input('status');
        $phone      = str_replace(['(', ')', '+', '-', ' '], '', $request->input('msisdn'));

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'DELIVRD' || $status == 'DELIVERED') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status, $phone);
    }


    /**
     * bulk sms delivery reports
     *
     * @param  Request  $request
     */
    public function dlrBulkSMS(Request $request)
    {

        logger($request->all());

    }

    /**
     * SMSVas delivery reports
     *
     * @param  Request  $request
     */
    public function dlrSMSVas(Request $request)
    {

        logger($request->all());

    }


    /**
     * receive inbound message
     *
     * @param      $to
     * @param      $message
     * @param      $sending_sever
     * @param      $cost
     * @param  null  $from
     * @param  null  $media_url
     * @param  null  $extra
     * @param  int  $user_id
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public static function inboundDLR($to, $message, $sending_sever, $cost, $from = null, $media_url = null, $extra = null, int $user_id = 1): JsonResponse|string
    {
        if (config('app.stage') == 'demo') {
            return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry!! This option is not available in demo mode',
            ]);
        }

        $to   = str_replace(['(', ')', '+', '-', ' '], '', trim($to));
        $from = ($from != null) ? str_replace(['(', ')', '+', '-', ' '], '', trim($from)) : null;

        $phoneNumberUtil   = PhoneNumberUtil::getInstance();
        $phoneNumberObject = $phoneNumberUtil->parse('+'.$to);
        $country_code      = $phoneNumberObject->getCountryCode();

        $success = 'Success';
        $failed  = null;
        $ass_user = $ass_user_id = null;

        $sms_type        = ($media_url) ? 'mms' : (($sending_sever == 'Whatsender') ? 'whatsapp' : 'plain');
        $sending_servers = SendingServer::where('settings', $sending_sever)->where('status', 1)->first();
        Log::info('ss- : '.$sending_sever);
        if($sending_servers->settings == 'Ejoin'){
            $cb = ChatBox::where('from',$from)->where('to',$to)->first();
            if($cb){
                $sending_servers = SendingServer::find($cb->sending_server_id);   
            }else{
                $rep = Reports::where('send_by','from')->where('to',$to)->first();
                $sending_servers = SendingServer::find($rep->sending_server_id);   
            }
        }
        $s_id = $sending_servers->id;

        $sms_counter  = new SMSCounter();
        $message_data = $sms_counter->count($message);
        $sms_count    = $message_data->messages;

        if ($extra != null) {
            $sender_id = Senderid::where('sender_id', $extra)->first();

            if ($sender_id) {
                $user_id = $sender_id->user_id;
                $user    = User::find($user_id);

                if ($user->is_customer && $user->sms_unit != '-1') {
                    $coverage = CustomerBasedPricingPlan::where('user_id', $user->id)
                                                        ->whereHas('country', function ($query) use ($country_code) {
                                                            $query->where('country_code', $country_code)
                                                                  ->where('status', 1);
                                                        })
                                                        ->with('sendingServer')
                                                        ->first();

                    if ( ! $coverage) {
                        $coverage = PlansCoverageCountries::where(function ($query) use ($user, $country_code) {
                            $query->whereHas('country', function ($query) use ($country_code) {
                                $query->where('country_code', $country_code)
                                      ->where('status', 1);
                            })->where('plan_id', $user->customer->activeSubscription()->plan_id);
                        })
                                                          ->with('sendingServer')
                                                          ->first();
                    }

                    if ($coverage) {
                        $priceOption = json_decode($coverage->options, true);
                        $unit_price  = $priceOption['receive_plain_sms'];
                        $cost        = $sms_count * $unit_price;

                        if ($cost > $user->sms_unit) {
                            return __('locale.campaigns.not_enough_balance', [
                                    'current_balance' => $user->sms_unit,
                                    'campaign_price'  => $cost,
                            ]);
                        }

                        DB::transaction(function () use ($user, $cost) {
                            $remaining_balance = $user->sms_unit - $cost;
                            $user->update(['sms_unit' => $remaining_balance]);
                        });

                        $sending_servers = $coverage->sendingServer;

                        if ( ! $sending_servers) {
                            return __('locale.campaigns.sending_server_not_available');
                        }
                    } else {
                        $failed .= "Permission to send an SMS has not been enabled for the region indicated by the 'To' number: ".$to;
                    }

                }
                $msg = false;
                $report = Reports::where('from',$from)->where('to',$to)->where('send_by','from')->first();
        
        
                $sim = 0;
                if(!$report){
                    $report = Reports::where('from','cmp')->where('to',$to)->where('send_by','from')->first();
                    $sim = 1;
            
                }
                if($report){
                    $msg = true;
                    $ass_user_id = $report->user_id;
                    $ass_user=User::find($ass_user_id);
                }else{
                    $ass_user_id = $user_id;
                    $ass_user = $user;
                }
                if($sim){
            
                    $c_report = Reports::where('from','cmp')->where('to',$to)->where('send_by','from')->whereNotNull('campaign_id')->first();
                    if($c_report){
                        $c_report->from = $from;
                        $c_report->save();
                    }
                }
                $c_report = Reports::where('from',$from)->where('to',$to)->where('send_by','from')->whereNotNull('campaign_id')->first();
                $c_ass_id = $ass_user_id;
                if($c_report){
                    $c_u = CampaignUser::where('campaign_id', $c_report->campaign_id)->orderBy('count', 'asc')->first();
                    if($c_u){
                        $c_ass_id = $c_u->user_id;
                        $c_box = ChatBox::where('from' , $from)->where('to' , $to)->first();
                        if($c_box){
                            $c_ass_id=$c_box->user_id;
                        }else{
                            $c_u->count = $c_u->count+1;
                            $c_u->save();
                        }
                    }
                }

                $chatBox = ChatBox::updateOrCreate(
                        [
                                'user_id' => $c_ass_id,
                                'from'    => $extra,
                                'to'      => $to,
                        ],
                        [
                                'notification'      => DB::raw('notification + 1'),
                                'reply_by_customer' => true,
                                'sending_server_id' => $sending_servers->id,
                        ]
                );

                if ($chatBox) {
                    Notifications::create([
                            'user_id'           => $c_ass_id,
                            'notification_for'  => 'customer',
                            'notification_type' => 'chatbox',
                            'message'           => 'New chat message arrived',
                    ]);

                    ChatBoxMessage::create([
                            'box_id'            => $chatBox->id,
                            'message'           => $message,
                            'media_url'         => $media_url,
                            'sms_type'          => $sms_type,
                            'send_by'           => 'to',
                            'sending_server_id' => $sending_servers->id,
                    ]);

                    event(new MessageReceived($ass_user, $message, $chatBox));
                    if($msg){
                        $cht = ChatBoxMessage::where('box_id',$chatBox->id)->where('send_by','from')->where('message',$report->message)->first();
                        if(!$cht){
                            ChatBoxMessage::create([
                                'box_id'            => $chatBox->id,
                                'message'           => $report->message,
                                'media_url'         => null,
                                'sms_type'          => 'plain',
                                'send_by'           => 'from',
                                'sending_server_id' => $sending_servers->id,
                                'created_at'        => now()->subMinutes(1),
                                'updated_at'        => now()->subMinutes(1),
                            ]);
                        }
                    }
                    // $user->notify(new \App\Notifications\MessageReceived($message, $to));

                } else {
                    $failed .= 'Failed to create chat message ';
                }

                Reports::create([
                        'user_id'           => $ass_user_id,
                        'from'              => $from,
                        'to'                => $to,
                        'message'           => $message,
                        'sms_type'          => $sms_type,
                        'status'            => "Delivered",
                        'send_by'           => "to",
                        'cost'              => $cost,
                        'sms_count'         => $sms_count,
                        'media_url'         => $media_url,
                        'sending_server_id' => $sending_servers->id,
                ]);
            }

        } else {
    
            $phone_number = PhoneNumbers::where('number', $from)
                                        ->where('status', 'assigned')
                                        ->first();

            if ( ! $phone_number) {
                $phone_number = PhoneNumbers::where('number', 'like', "%{$from}%")
                                            ->where('status', 'assigned')
                                            ->first();
            }
            if ($phone_number || $sending_sever = 'Ejoin') {
        
                if($phone_number){
                    $user_id = $phone_number->user_id;
                    $user   = User::find($user_id);   
                }
                
                $msg = false;
                $report = Reports::where('from',$from)->where('to',$to)->where('send_by','from')->first();
        
                $sim = 0;
        
        
                if(!$report){
                    $report = Reports::where('from','cmp')->where('to',$to)->where('send_by','from')->first();
                    $sim = 1;
            
                }
                Log::info('rec');
                Log::info($report);
                if($report){
                    $msg = true;
                    $ass_user_id = $report->user_id;
                    $ass_user=User::find($ass_user_id);
                    $user = $ass_user;
                }
                if($sim){
            
                    $c_report = Reports::where('from','cmp')->where('to',$to)->where('send_by','from')->whereNotNull('campaign_id')->first();
                    if($c_report){
                
                        $c_report->from = $from;
                        $c_report->save();
                        $ass_user_id = $c_report->user_id;
                        $ass_user=User::find($ass_user_id);
                        $user = $ass_user;
                    }
                }
                if ( ! $phone_number) {
                    $phone_number = PhoneNumbers::create([
                            'number'=>$from,
                            'user_id' => $ass_user_id,
                            'status'=> 'assigned',
                            'server'=> strtolower($sending_servers->name),
                            'price' =>1,
                            'capabilities'=> json_encode(["sms","mms"]),
                            'frequency_unit'=> 'month',
                            'currency_id '=> 1,
                            'frequency_amount'=> 0,
                            'billing_cycle'=> 'monthly',
                    ]);
                }

                if ($user->is_customer && $user->sms_unit != '-1') {
                    $coverage = CustomerBasedPricingPlan::where('user_id', $user->id)
                                                        ->whereHas('country', function ($query) use ($country_code) {
                                                            $query->where('country_code', $country_code)
                                                                  ->where('status', 1);
                                                        })
                                                        ->with('sendingServer')
                                                        ->first();

                    if ( ! $coverage) {
                        $coverage = PlansCoverageCountries::where(function ($query) use ($user, $country_code) {
                            $query->whereHas('country', function ($query) use ($country_code) {
                                $query->where('country_code', $country_code)
                                      ->where('status', 1);
                            })->where('plan_id', $user->customer->activeSubscription()->plan_id);
                        })
                                                          ->with('sendingServer')
                                                          ->first();
                    }

                    if ($coverage) {
                        $priceOption = json_decode($coverage->options, true);
                        $unit_price  = $priceOption['receive_plain_sms'];

                        $cost = $sms_count * $unit_price;

                        if ($cost > $user->sms_unit) {
                            if($sending_sever != 'Ejoin'){
                                return __('locale.campaigns.not_enough_balance', [
                                        'current_balance' => $user->sms_unit,
                                        'campaign_price'  => $cost,
                                ]);   
                            }
                        }

                        DB::transaction(function () use ($user, $cost) {
                            $remaining_balance = $user->sms_unit - $cost;
                            $user->update(['sms_unit' => $remaining_balance]);
                        });

                        $sending_servers = $coverage->sendingServer;

                        if ( ! $sending_servers) {
                            return __('locale.campaigns.sending_server_not_available');
                        }
                    } else {
                        $failed .= "Permission to send an SMS has not been enabled for the region indicated by the 'To' number: ".$to;
                    }
                }

                $c_report = Reports::where('from',$from)->where('to',$to)->where('send_by','from')->whereNotNull('campaign_id')->first();
                $c_ass_id = $ass_user_id;
                if($c_report){
                    $c_u = CampaignUser::where('campaign_id', $c_report->campaign_id)->orderBy('count', 'asc')->first();
                    if($c_u){
                        $c_ass_id = $c_u->user_id;
                        $c_box = ChatBox::where('from' , $from)->where('to' , $to)->first();
                        if($c_box){
                            $c_ass_id=$c_box->user_id;
                        }else{
                            $c_u->count = $c_u->count+1;
                            $c_u->save();
                        }
                    }
                }
                if($sending_sever = 'Ejoin'){
                    $s_id_s = $s_id;
                }else{
                    $s_id_s = $sending_servers->id;
                }
                $chatBox = ChatBox::updateOrCreate(
                        [
                                'user_id' => $c_ass_id,
                                'from'    => $from,
                                'to'      => $to,
                        ],
                        [
                                'notification'      => DB::raw('notification + 1'),
                                'reply_by_customer' => true,
                                'sending_server_id' => $s_id_s,
                        ]
                );
                if ($chatBox) {
                    Notifications::create([
                            'user_id'           => $c_ass_id,
                            'notification_for'  => 'customer',
                            'notification_type' => 'chatbox',
                            'message'           => 'New chat message arrived',
                    ]);
                    
                    if($msg){
                        $cht = ChatBoxMessage::where('box_id',$chatBox->id)->where('send_by','from')->where('message',$report->message)->first();
                        if(!$cht){
                            ChatBoxMessage::create([
                                'box_id'            => $chatBox->id,
                                'message'           => $report->message,
                                'media_url'         => null,
                                'sms_type'          => 'plain',
                                'send_by'           => 'from',
                                'sending_server_id' => $s_id_s,
                            ]);
                        }
                    }

                    ChatBoxMessage::create([
                            'box_id'            => $chatBox->id,
                            'message'           => $message,
                            'media_url'         => $media_url,
                            'sms_type'          => $sms_type,
                            'send_by'           => 'to',
                            'sending_server_id' => $s_id_s,
                    ]);

                    event(new MessageReceived($ass_user, $message, $chatBox));
                    //  $user->notify(new \App\Notifications\MessageReceived($message, $to));
                } else {
                    $failed .= 'Failed to create chat message ';
                }


                //check keywords
                $keyword = Keywords::where('user_id', $ass_user_id)
                                   ->select('*')
                                   ->selectRaw('lower(keyword_name) as keyword,keyword_name')
                                   ->where('keyword_name', strtolower($message))
                                   ->where('status', 'assigned')->first();
                $status  = 'Delivered';

                if ($keyword) {

                    $optInContacts = ContactGroups::with('optinKeywords')
                                                  ->whereHas('optinKeywords', function ($query) use ($message) {
                                                      $query->where('keyword', $message);
                                                  })
                                                  ->where('customer_id', $ass_user_id)
                                                  ->get();


                    $optOutContacts = ContactGroups::with('optoutKeywords')
                                                   ->whereHas('optoutKeywords', function ($query) use ($message) {
                                                       $query->where('keyword', $message);
                                                   })
                                                   ->where('customer_id', $ass_user_id)
                                                   ->get();


                    $blacklist = Blacklists::where('user_id', $ass_user_id)->where('number', $to)->first();

                    if ($optInContacts->count()) {
                        foreach ($optInContacts as $contact) {

                            $exist = Contacts::where('group_id', $contact->id)->where('phone', $to)->first();

                            $blacklist?->delete();

                            if ( ! $exist) {
                                $data = Contacts::create([
                                        'customer_id' => $ass_user_id,
                                        'group_id'    => $contact->id,
                                        'phone'       => $to,
                                        'status'      => 'subscribe',
                                        'first_name'  => null,
                                        'last_name'   => null,
                                ]);

                                if ($data) {

                                    $sendMessage = new EloquentCampaignRepository($campaign = new Campaigns());


                                    if ($contact->send_keyword_message) {
                                        if (isset($keyword->reply_text)) {

                                            $getStatus = $sendMessage->quickSend($campaign, [
                                                    'sender_id'    => $keyword->sender_id,
                                                    'sms_type'     => $sms_type,
                                                    'message'      => $keyword->reply_text,
                                                    'recipient'    => $phoneNumberObject->getNationalNumber(),
                                                    'user_id'      => $user_id,
                                                    'country_code' => $country_code,
                                            ]);

                                            if ($getStatus->getData()->status == 'error') {
                                                $status = $getStatus->getData()->message;
                                            }

                                        }
                                    } else {
                                        if ($contact->send_welcome_sms && $contact->welcome_sms) {

                                            $getStatus = $sendMessage->quickSend($campaign, [
                                                    'sender_id'    => $contact->sender_id,
                                                    'sms_type'     => $sms_type,
                                                    'message'      => $contact->welcome_sms,
                                                    'recipient'    => $phoneNumberObject->getNationalNumber(),
                                                    'user_id'      => $user_id,
                                                    'country_code' => $country_code,
                                            ]);

                                            if ($getStatus->getData()->status == 'error') {
                                                $status = $getStatus->getData()->message;
                                            }
                                        }
                                    }

                                    $contact->updateCache('SubscribersCount');
                                } else {
                                    $failed .= 'Failed to subscribe contact list';
                                }
                            } else {
                                $sendMessage = new EloquentCampaignRepository($campaign = new Campaigns());


                                $getStatus = $sendMessage->quickSend($campaign, [
                                        'sender_id'    => $keyword->sender_id,
                                        'sms_type'     => $sms_type,
                                        'message'      => __('locale.contacts.you_have_already_subscribed', ['contact_group' => $contact->name]),
                                        'recipient'    => $phoneNumberObject->getNationalNumber(),
                                        'user_id'      => $user_id,
                                        'country_code' => $country_code,
                                ]);

                                if ($getStatus->getData()->status == 'error') {
                                    $status = $getStatus->getData()->message;
                                }

                                $exist->update([
                                        'status' => 'subscribe',
                                ]);
                            }

                        }
                    } elseif ($optOutContacts->count()) {

                        foreach ($optOutContacts as $contact) {

                            if ( ! $blacklist) {
                                $exist = Contacts::where('group_id', $contact->id)->where('phone', $to)->first();
                                if ($exist) {

                                    $chatbox_messages = ChatBox::where('user_id', $user_id)->where('to', $to)->get();
                                    foreach ($chatbox_messages as $messages) {
                                        $check_delete = ChatBoxMessage::where('box_id', $messages->id)->delete();
                                        if ($check_delete) {
                                            $messages->delete();
                                        }
                                    }

                                    $sendMessage = new EloquentCampaignRepository($campaign = new Campaigns());

                                    if (isset($contact->send_keyword_message)) {
                                        if (isset($keyword->reply_text)) {

                                            $getStatus = $sendMessage->quickSend($campaign, [
                                                    'sender_id'    => $keyword->sender_id,
                                                    'sms_type'     => $sms_type,
                                                    'message'      => $keyword->reply_text,
                                                    'recipient'    => $phoneNumberObject->getNationalNumber(),
                                                    'user_id'      => $user_id,
                                                    'country_code' => $country_code,
                                            ]);

                                            if ($getStatus->getData()->status == 'error') {
                                                $status = $getStatus->getData()->message;
                                            }
                                        }
                                    } else {
                                        if ($contact->unsubscribe_notification && $contact->unsubscribe_sms) {

                                            $getStatus = $sendMessage->quickSend($campaign, [
                                                    'sender_id'    => $contact->sender_id,
                                                    'sms_type'     => $sms_type,
                                                    'message'      => $contact->unsubscribe_sms,
                                                    'recipient'    => $phoneNumberObject->getNationalNumber(),
                                                    'user_id'      => $user_id,
                                                    'country_code' => $country_code,
                                            ]);
                                            if ($getStatus->getData()->status == 'error') {
                                                $status = $getStatus->getData()->message;
                                            }
                                        }
                                    }

                                    $data = $exist->update([
                                            'status' => 'unsubscribe',
                                    ]);
                                    if ($data) {
                                        Blacklists::create([
                                                'user_id' => $user_id,
                                                'number'  => $to,
                                                'reason'  => "Optout by User",
                                        ]);
                                    }
                                }
                            }
                        }
                    } else {

                        if (isset($keyword->reply_text)) {
                            $sendMessage = new EloquentCampaignRepository($campaign = new Campaigns());
                            $getStatus   = $sendMessage->quickSend($campaign, [
                                    'sender_id'    => $keyword->sender_id,
                                    'sms_type'     => $sms_type,
                                    'message'      => $keyword->reply_text,
                                    'recipient'    => $phoneNumberObject->getNationalNumber(),
                                    'user_id'      => $user_id,
                                    'country_code' => $country_code,
                            ]);
                            if ($getStatus->getData()->status == 'error') {
                                $status = $getStatus->getData()->message;
                            }
                        } else {
                            $failed .= 'Related keyword reply message not found.';
                        }
                    }
                }

                Reports::create([
                        'user_id'           => $ass_user_id,
                        'from'              => $from,
                        'to'                => $to,
                        'message'           => $message,
                        'sms_type'          => $sms_type,
                        'status'            => $status,
                        'send_by'           => "to",
                        'cost'              => $cost,
                        'sms_count'         => $sms_count,
                        'media_url'         => $media_url,
                        'sending_server_id' => $sending_servers->id,
                ]);

            }
        }

        if (strtolower($message) == 'stop') {
            $blacklist = Blacklists::where('user_id', $user_id)
                                   ->where('number', $to)
                                   ->first();

            if ( ! $blacklist) {
                Blacklists::create([
                        'user_id' => $user_id,
                        'number'  => $to,
                        'reason'  => "Optout by User",
                ]);

                ChatBox::where('user_id', $user_id)
                       ->where('to', $to)
                       ->delete();

                ChatBoxMessage::whereHas('box', function ($query) use ($user_id, $to) {
                    $query->where('user_id', $user_id)
                          ->where('to', $to);
                })->delete();
            }
        }

        if ($failed == null) {
            return $success;
        }

        return $failed;
    }

    /**
     * twilio inbound sms
     *
     * @param  Request  $request
     *
     * @return Message|MessagingResponse
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundTwilio(Request $request): Message|MessagingResponse
    {
        $to      = $request->input('From');
        $from    = $request->input('To');
        $message = $request->input('Body');

        if ($message == 'NULL') {
            $message = null;
        }

        $response = new MessagingResponse();

        if ($to == null || $from == null) {
            $response->message('From and To value required');

            return $response;
        }

        $feedback = 'Success';

        $NumMedia = (int) $request->input('NumMedia');
        if ($NumMedia > 0) {
            $cost = 1;
            for ($i = 0; $i < $NumMedia; $i++) {
                $mediaUrl = $request->input("MediaUrl$i");
                $feedback = $this::inboundDLR($to, $message, 'Twilio', $cost, $from, $mediaUrl);
            }
        } else {
            $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
            $cost          = ceil($message_count);

            $feedback = $this::inboundDLR($to, $message, 'Twilio', $cost, $from);
        }


        if ($feedback == 'Success') {
            return $response;
        }

        return $response->message($feedback);
    }

    /**
     * TwilioCopilot inbound sms
     *
     * @param  Request  $request
     *
     * @return Message|MessagingResponse
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundTwilioCopilot(Request $request): Message|MessagingResponse
    {
        $to      = $request->input('From');
        $from    = $request->input('To');
        $message = $request->input('Body');
        $extra   = $request->input('MessagingServiceSid');

        if ($message == 'NULL') {
            $message = null;
        }

        $response = new MessagingResponse();

        if ($to == null || $from == null || $extra == null) {
            $response->message('From, To, and MessagingServiceSid value required');

            return $response;
        }

        $feedback = 'Success';

        $NumMedia = (int) $request->input('NumMedia');
        if ($NumMedia > 0) {
            $cost = 1;
            for ($i = 0; $i < $NumMedia; $i++) {
                $mediaUrl = $request->input("MediaUrl$i");
                $feedback = $this::inboundDLR($to, $message, 'TwilioCopilot', $cost, $from, $mediaUrl, $extra);
            }
        } else {
            $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
            $cost          = ceil($message_count);

            $feedback = $this::inboundDLR($to, $message, 'TwilioCopilot', $cost, $from, null, $extra);
        }


        if ($feedback == 'Success') {
            return $response;
        }

        return $response->message($feedback);
    }

    /**
     * text local inbound sms
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundTextLocal(Request $request): JsonResponse|string
    {
        $to      = $request->input('sender');
        $from    = $request->input('inNumber');
        $message = $request->input('content');

        if ($to == null || $from == null || $message == null) {
            return 'Sender, inNumber and content value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, 'TextLocal', $cost, $from);
    }


    /**
     * inbound plivo messages
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundPlivo(Request $request): JsonResponse|string
    {
        $to      = $request->input('From');
        $from    = $request->input('To');
        $message = $request->input('Text');

        if ($to == null || $message == null) {
            return 'Destination number and message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, 'Plivo', $cost, $from);
    }


    /**
     * inbound plivo powerpack messages
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundPlivoPowerPack(Request $request): JsonResponse|string
    {
        $to      = $request->input('From');
        $from    = $request->input('To');
        $message = $request->input('Text');

        if ($to == null || $message == null) {
            return 'Destination number and message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, 'PlivoPowerpack', $cost, $from);
    }


    /**
     * inbound bulk sms messages
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundBulkSMS(Request $request): JsonResponse|string
    {
        $to      = $request->input('msisdn');
        $from    = $request->input('sender');
        $message = $request->input('message');

        if ($to == null || $message == null) {
            return 'Destination number and message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, 'BulkSMS', $cost, $from);
    }

    /**
     * inbound Vonage messages
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundVonage(Request $request): JsonResponse|string
    {
        $to      = $request->input('msisdn');
        $from    = $request->input('to');
        $message = $request->input('text');

        if ($to == null || $message == null) {
            return 'Destination number, Source number and message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, 'Vonage', $cost, $from);
    }

    /**
     * inbound messagebird messages
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundMessagebird(Request $request): JsonResponse|string
    {

        $to      = $request->input('originator');
        $from    = $request->input('recipient');
        $message = $request->input('body');

        if ($to == null || $message == null) {
            return 'Destination number, Source number and message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, 'MessageBird', $cost, $from);
    }

    /**
     * inbound signalwire messages
     *
     * @param  Request  $request
     *
     * @return Message|MessagingResponse
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundSignalwire(Request $request): Message|MessagingResponse
    {

        $response = new MessagingResponse();

        $to      = $request->input('From');
        $from    = $request->input('To');
        $message = $request->input('Body');

        if ($to == null || $from == null || $message == null) {
            $response->message('From, To and Body value required');

            return $response;
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        $feedback = $this::inboundDLR($to, $message, 'SignalWire', $cost, $from);

        if ($feedback == 'Success') {
            return $response;
        }

        return $response->message($feedback);
    }


    /**
     * inbound telnyx messages
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundTelnyx(Request $request): JsonResponse|string
    {

        $get_data = $request->getContent();

        $get_data = json_decode($get_data, true);

        if (isset($get_data) && is_array($get_data) && array_key_exists('data', $get_data) && array_key_exists('payload', $get_data['data'])) {
            if ($get_data['data']['event_type'] == 'message.received') {
                $to      = $get_data['data']['payload']['from']['phone_number'];
                $from    = $get_data['data']['payload']['to'][0]['phone_number'];
                $message = $get_data['data']['payload']['text'];

                if ($to == '' || $message == '' || $from == '') {
                    return 'Destination or Sender number and message value required';
                }

                $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
                $cost          = ceil($message_count);

                return $this::inboundDLR($to, $message, 'Telnyx', $cost, $from);
            }
            if ($get_data['data']['event_type'] == 'message.finalized') {
                $message_id = $get_data['data']['payload']['id'];
                $status     = $get_data['data']['payload']['to'][0]['status'];

                if ($status == 'delivered' || $status == 'webhook_delivered') {
                    $status = 'Delivered';
                }

                $this::updateDLR($message_id, $status);
            }

            return 'Invalid request';
        }

        return 'Invalid request';
    }


    /**
     * inbound Teletopiasms messages
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundTeletopiasms(Request $request): JsonResponse|string
    {

        $to      = $request->input('sender');
        $from    = $request->input('recipient');
        $message = $request->input('text');

        if ($to == null || $message == null) {
            return 'Destination number, Source number and message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, 'Teletopiasms', $cost, $from);
    }


    /**
     * receive FlowRoute message
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundFlowRoute(Request $request): JsonResponse|string
    {

        $data = json_decode($request->getContent(), true);

        if (isset($data) && is_array($data) && array_key_exists('data', $data)) {

            $to      = $data['data']['attributes']['from'];
            $from    = $data['data']['attributes']['to'];
            $message = $data['data']['attributes']['body'];

            if ($from == '' || $message == '' || $to == '') {
                return 'From, To and Body value required';
            }

            $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
            $cost          = ceil($message_count);

            return $this::inboundDLR($to, $message, 'FlowRoute', $cost, $from);
        }

        return 'valid data not found';
    }

    /**
     * receive inboundEasySendSMS message
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundEasySendSMS(Request $request): JsonResponse|string
    {

        $to      = $request->input('From');
        $from    = null;
        $message = $request->input('message');

        if ($message == '' || $to == '') {
            return 'To and Message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, 'FlowRoute', $cost, $from);
    }


    /**
     * receive Skyetel message
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundSkyetel(Request $request): JsonResponse|string
    {

        $to      = $request->input('from');
        $from    = $request->input('to');
        $message = $request->input('text');

        if ($to == '' || $from == '') {
            return 'To and From value required';
        }


        if (isset($request->media) && is_array($request->media) && array_key_exists('1', $request->media)) {

            $mediaUrl = $request->media[1];

            return $this::inboundDLR($to, $message, 'Skyetel', 1, $from, $mediaUrl);
        } else {

            $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
            $cost          = ceil($message_count);

            return $this::inboundDLR($to, $message, 'Skyetel', $cost, $from);
        }

    }

    /**
     * receive chat-api message
     *
     * @return JsonResponse|bool|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundChatApi(): JsonResponse|bool|string
    {

        $data = json_decode(file_get_contents('php://input'), true);

        foreach ($data['messages'] as $message) {

            $to      = $message['author'];
            $from    = $message['senderName'];
            $message = $message['body'];

            if ($message == '' || $to == '' || $from == '') {
                return 'Author, Sender Name and Body value required';
            }

            $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
            $cost          = ceil($message_count);

            return $this::inboundDLR($to, $message, 'WhatsAppChatApi', $cost, $from);
        }

        return true;
    }

    /**
     * callr delivery reports
     *
     * @param  Request  $request
     *
     * @return string|void
     */
    public function dlrCallr(Request $request)
    {

        $get_data = json_decode($request->getContent(), true);

        $message_id = $get_data['data']['user_data'];
        $status     = $get_data['data']['status'];

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'RECEIVED' || $status == 'SENT') {
            $status = 'Delivered|'.$message_id;
        }

        $this::updateDLR($message_id, $status);
    }


    /**
     * receive callr message
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundCallr(Request $request): JsonResponse|string
    {

        $get_data = json_decode($request->getContent(), true);

        $to      = str_replace('+', '', $get_data['data']['from']);
        $from    = str_replace('+', '', $get_data['data']['to']);
        $message = $get_data['data']['text'];

        if ($message == '' || $to == '' || $from == '') {
            return 'From, To and Text value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, 'Callr', $cost, $from);
    }


    /**
     * cm com delivery reports
     *
     * @param  Request  $request
     *
     * @return mixed|string
     */
    public function dlrCM(Request $request)
    {

        $get_data = json_decode($request->getContent(), true);
        if (is_array($get_data) && array_key_exists('messages', $get_data)) {
            $message_id = $get_data['messages']['msg']['reference'];
            $status     = $get_data['messages']['msg']['status']['errorDescription'];

            if ( ! isset($message_id) && ! isset($status)) {
                return 'Message ID and status not found';
            }

            if ($status == 'Delivered') {
                $status = 'Delivered|'.$message_id;
            }

            return $this::updateDLR($message_id, $status);
        }

        return 'Null Value Return';
    }


    /**
     * receive cm com message
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundCM(Request $request): JsonResponse|string
    {

        $get_data = json_decode($request->getContent(), true);

        $to      = str_replace('+', '', $get_data['from']['number']);
        $from    = str_replace('+', '', $get_data['to']['number']);
        $message = $get_data['message']['text'];

        if ($message == '' || $to == '' || $from == '') {
            return 'From, To and Text value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, 'CMCOM', $cost, $from);
    }


    /**
     * receive bandwidth message
     *
     * @param  Request  $request
     *
     * @return bool|JsonResponse|string|null
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundBandwidth(Request $request): bool|JsonResponse|string|null
    {

        $data = $request->all();

        if (isset($data) && is_array($data) && count($data) > 0) {
            if ($data['0']['type'] == 'message-received') {
                if (isset($data[0]['message']) && is_array($data[0]['message'])) {
                    $to      = $data[0]['message']['from'];
                    $from    = $data[0]['to'];
                    $message = $data[0]['message']['text'];


                    if ($message == '' || $to == '' || $from == '') {
                        return 'From, To and Text value required';
                    }

                    $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
                    $cost          = ceil($message_count);

                    return $this::inboundDLR($to, $message, 'Bandwidth', $cost, $from);
                } else {
                    return $request->getContent();
                }
            } else {
                return $request->getContent();
            }
        } else {
            return $request->getContent();
        }

    }


    /**
     * receive Solucoesdigitais message
     *
     * @param  Request  $request
     *
     * @return bool|false
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundSolucoesdigitais(Request $request): bool
    {
        $data        = $request->all();
        $id_campanha = $data['id_campanha'];
        $report      = Reports::where('status', 'LIKE', "%$id_campanha%")->first();

        $message       = $data['sms_resposta'];
        $to            = $data['nro_telefone'];
        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        if ($report) {
            $from = $report->from;

            if ($message == '' || $to == '' || $from == '') {
                return 'From, To and Text value required';
            }

            return $this::inboundDLR($to, $message, 'Solucoesdigitais', $cost, $from, null, null, $report->user_id);
        }

        return $this::inboundDLR($to, $message, 'Solucoesdigitais', $cost);
    }


    /**
     * receive inboundGatewayApi message
     *
     * @param  Request  $request
     *
     * @return bool|false
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundGatewayApi(Request $request): bool
    {

        $to      = $request->input('msisdn');
        $from    = $request->input('receiver');
        $message = $request->input('message');

        if ($message == '' || $to == '') {
            return 'To and Message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, 'Gatewayapi', $cost, $from);
    }


    /**
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */

    public function inboundInteliquent(Request $request)
    {

        $to      = $request->input('to')[0];
        $from    = $request->input('from');
        $message = $request->input('text');

        if ($message == '' || $to == '' || $from == '') {
            return 'From, To and Message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, 'Inteliquent', $cost, $from);
    }


    /**
     * Version 3.5
     *
     * @param  Request  $request
     *
     * @return string
     */
    public function dlrD7networks(Request $request)
    {

        $message_id = $request->input('request_id');
        $status     = $request->input('status');

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'delivered' || $status == 'accepted') {
            $status = 'Delivered';
        }
        $this::updateDLR($message_id, $status);

        return $status;
    }


    /**
     * Inbound sms for Tele API
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */

    public function inboundTeleAPI(Request $request)
    {

        $to      = $request->input('destination');
        $from    = $request->input('source');
        $message = $request->input('message');

        if ($message == '' || $to == '' || $from == '') {
            return 'Source, Destination and Message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, SendingServer::TYPE_TELEAPI, $cost, $from);
    }

    /*Version 3.6*/

    public function dlrAmazonSNS(Request $request)
    {
        logger($request->all());
    }

    public function dlrViber(Request $request)
    {
        logger($request->all());
    }


    /**
     * dlrNimbuz delivery reports
     *
     * @param  Request  $request
     *
     * @return mixed
     */
    public function dlrNimbuz(Request $request)
    {
        $message_id = $request->input('requestid');
        $status     = $request->input('status');
        $phone      = str_replace(['(', ')', '+', '-', ' '], '', $request->input('mobile'));

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        $this::updateDLR($message_id, $status, $phone);

        return $status;

    }

    /**
     * dlrGatewaySa delivery reports
     *
     * @param  Request  $request
     *
     * @return mixed
     */
    public function dlrGatewaySa(Request $request)
    {
        $message_id = $request->input('messageId');
        $status     = $request->input('status');
        $phone      = str_replace(['(', ')', '+', '-', ' '], '', $request->input('mobile'));

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'DELIVRD') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status, $phone);

        return $status;

    }


    /**
     * receive inboundWhatsender message
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundWhatsender(Request $request)
    {
        $get_data = $request->getContent();

        if (empty($get_data)) {
            return 'Invalid request';
        }

        $get_data = json_decode($get_data, true);

        if (isset($get_data['event'], $get_data['data'], $get_data['device'])) {
            if ($get_data['event'] == 'message:in:new') {
                $deviceId = $get_data['device']['id'];
                $server   = SendingServer::where('settings', 'Whatsender')
                                         ->where('status', 1)
                                         ->where('device_id', $deviceId)
                                         ->first();

                if ( ! $server) {
                    return 'Sending server not found';
                }

                $from     = $get_data['data']['toNumber'];
                $to       = $get_data['data']['fromNumber'];
                $mediaUrl = '';

                switch ($get_data['data']['type']) {
                    case 'image':
                    case 'video':
                    case 'audio':
                        $message     = $get_data['data']['media']['caption'];
                        $media_url   = $get_data['data']['media']['links']['download'];
                        $file_name   = $get_data['data']['media']['filename'];
                        $gateway_url = "https://api.whatsender.io".$media_url;

                        if ($message == null) {
                            $message = $file_name;
                        }

                        $curl = curl_init();
                        curl_setopt_array($curl, [
                                CURLOPT_URL            => $gateway_url,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING       => "",
                                CURLOPT_MAXREDIRS      => 10,
                                CURLOPT_TIMEOUT        => 30,
                                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST  => "GET",
                                CURLOPT_HTTPHEADER     => [
                                        "Token: ".$server->api_token,
                                ],
                        ]);

                        $response = curl_exec($curl);

                        $path        = 'mms/';
                        $upload_path = public_path($path);

                        if ( ! file_exists($upload_path)) {
                            mkdir($upload_path, 0777, true);
                        }

                        $saveTo = $upload_path.$file_name;

                        file_put_contents($saveTo, $response);

                        $mediaUrl = asset('/mms').'/'.$file_name;
                        curl_close($curl);

                        break;

                    default:
                        $message = $get_data['data']['body'];
                        if ($message == null) {
                            return 'Message not found';
                        }
                        break;
                }

                if (empty($to) || empty($from)) {
                    return 'Destination or Sender number and message value required';
                }

                $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
                $cost          = ceil($message_count);

                return $this::inboundDLR($to, $message, 'Whatsender', $cost, $from, $mediaUrl);
            }
        }

        return 'Invalid request';
    }

    /**
     * inbound Cheapglobalsms messages
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundCheapglobalsms(Request $request): JsonResponse|string
    {
        $to      = $request->input('sender');
        $from    = $request->input('recipient');
        $message = $request->input('message');

        if ($to == null || $message == null) {
            return 'Destination number and message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, SendingServer::TYPE_CHEAPGLOBALSMS, $cost, $from);
    }

    /**
     * dlrSMSMode delivery reports
     *
     * @param  Request  $request
     *
     * @return mixed
     */
    public function dlrSMSMode(Request $request)
    {
        $message_id = $request->input('messageId');
        $status     = $request->input('status')['value'];
        $phone      = str_replace(['(', ')', '+', '-', ' '], '', $request->input('from'));

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        if ($status == 'DELIVERED') {
            $status = 'Delivered';
        }

        $this::updateDLR($message_id, $status, $phone);

        return $status;

    }

    /**
     * SMS Mode Inbound SMS
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundSMSMode(Request $request)
    {

        $to      = $request->input('from');
        $from    = $request->input('recipient')['to'];
        $message = $request->input('body')['text'];

        if ($to == null || $message == null) {
            return 'Destination number and message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, SendingServer::TYPE_SMSMODE, $cost, $from);
    }

    /**
     * Infobip Inbound SMS
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundInfobip(Request $request)
    {
        $to      = $request->input('results')['0']['from'];
        $from    = $request->input('results')['0']['to'];
        $message = $request->input('results')['0']['text'];

        if ($to == null || $message == null) {
            return 'Destination number and message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, SendingServer::TYPE_INFOBIP, $cost, $from);
    }


    /**
     * Voximplant Inbound SMS
     *
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundVoximplant(Request $request)
    {
        $to      = $request->input('source');
        $from    = $request->input('destination');
        $message = $request->input('sms_body');

        if ($to == null || $message == null) {
            return 'Destination number and message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, SendingServer::TYPE_VOXIMPLANT, $cost, $from);
    }

    /*Version 3.8*/
    public function dlrHutchLK(Request $request)
    {
        logger($request->all());
    }


    /**
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundClickSend(Request $request)
    {
        Log::info('Income From CS');
        $to      = $request->input('from');
        $from    = $request->input('to');
        $message = $request->input('body');

        if ($to == null || $message == null) {
            return 'Destination number and message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, SendingServer::TYPE_CLICKSEND, $cost, $from);
    }
    public function inboundEjoin(Request $request)
    {
        $to      = null;
        $from    = null;
        $message = null;
        if($request->type == "recv-sms"){
            $to    = $request->sms[0][3];
            $from  = $request->sms[0][4];
            $logMessage = base64_decode($request->sms[0][5]);
            $pattern = '/(?:[^\r\n]*[\r\n]){9}(.*)/s';
            if (preg_match($pattern, $logMessage, $matches)) {
                // Extracted message is in $matches[1]
                $extractedMessage = trim($matches[1]);
                $message = $extractedMessage;
            } 
            
        }
        $to = ltrim($to, '+1');
        // Add '1' at the beginning
        $to = '1' . $to;
        if ($to == null || $message == null) {
            return 'Destination number and message value required';
        }
        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);
        Log::info('in rec : '.$to .'--'.$message.'--'. $from);
        
        return $this::inboundDLR($to, $message, 'Ejoin', $cost, $from);
    }

    /**
     * @param  Request  $request
     *
     * @return string
     */
    public function dlrMoceanAPI(Request $request)
    {
        $message_id = $request->get('mocean-msgid');
        $status     = $request->get('mocean-dlr-status');
        $phone      = str_replace(['(', ')', '+', '-', ' '], '', $request->input('mocean-to'));

        if ( ! isset($message_id) && ! isset($status)) {
            return 'Message ID and status not found';
        }

        $status = match ($status) {
            '1' => 'Delivered',
            '2' => 'Failed',
            '3' => 'Expired',
        };

        $this::updateDLR($message_id, $status, $phone);

        return $status;
    }

    /**
     *airtelindia dlr
     *
     * @param  Request  $request
     */
    public function dlrAirtelIndia(Request $request)
    {
        logger($request->all());
    }


    /**
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundClickatell(Request $request)
    {
        $to      = $request->input('fromNumber');
        $from    = $request->input('toNumber');
        $message = $request->input('text');

        if ($to == null || $message == null) {
            return 'Destination number and message value required';
        }

        $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
        $cost          = ceil($message_count);

        return $this::inboundDLR($to, $message, SendingServer::TYPE_CLICKATELLTOUCH, $cost, $from);
    }


    /**
     * SimpleTexting delivery reports
     *
     * @param  Request  $request
     *
     * @return string
     */
    public function dlrSimpleTexting(Request $request)
    {
        logger($request->all());

        return 'Debugging';
    }


    /**
     * @param  Request  $request
     *
     * @return JsonResponse|string
     * @throws NumberParseException
     * @throws Throwable
     */
    public function inboundSimpleTexting(Request $request)
    {

        $type   = $request->input('type');
        $values = $request->input('values');
        if (isset($type) && $type == 'INCOMING_MESSAGE' && isset($values) && is_array($values)) {

            $from    = $values['accountPhone'];
            $to      = $values['contactPhone'];
            $message = $values['text'];

            if ($to == null || $message == null) {
                return 'Destination number and message value required';
            }

            $message_count = strlen(preg_replace('/\s+/', ' ', trim($message))) / 160;
            $cost          = ceil($message_count);

            return $this::inboundDLR($to, $message, SendingServer::TYPE_SIMPLETEXTING, $cost, $from);
        }

        return $type;
    }

    public function dlrDinstar(Request $request)
    {
        logger($request->all());
    }
}
