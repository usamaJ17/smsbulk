<?php

    namespace App\Http\Controllers\Customer;

    use App\Http\Controllers\Controller;
    use App\Http\Requests\ChatBox\SentRequest;
    use App\Models\Blacklists;
    use App\Models\Campaigns;
    use App\Models\ChatBox;
    use App\Models\Reports;
    use App\Models\ChatBoxMessage;
    use App\Models\Contacts;
    use App\Models\Country;
    use Carbon\Carbon;
    use App\Models\QuickResponse;
    use App\Models\User;
    use App\Models\CustomerBasedPricingPlan;
    use App\Models\CustomerBasedSendingServer;
    use App\Models\PhoneNumbers;
    use App\Models\PlansCoverageCountries;
    use App\Models\SendingServer;
    use App\Repositories\Contracts\CampaignRepository;
    use Illuminate\Auth\Access\AuthorizationException;
    use Illuminate\Contracts\Foundation\Application;
    use Illuminate\Contracts\View\Factory;
    use Illuminate\Contracts\View\View;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\RedirectResponse;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Auth;
    use libphonenumber\NumberParseException;
    use GuzzleHttp\Client;
    use libphonenumber\PhoneNumberUtil;

    class ChatBoxController extends Controller
    {

        protected CampaignRepository $campaigns;

        /**
         * ChatBoxController constructor.
         *
         * @param CampaignRepository $campaigns
         */
        public function __construct(CampaignRepository $campaigns)
        {
            $this->campaigns = $campaigns;
        }
        
        public function test()
        {
            dd($rep);
        }

        /**
         * get all chat box
         *
         * @return Application|Factory|View
         * @throws AuthorizationException
         */
public function index(): View|Factory|Application
{
    $this->authorize('chat_box');

    $pageConfigs = [
        'pageHeader'    => false,
        'contentLayout' => "content-left-sidebar",
        'pageClass'     => 'chat-application',
    ];

    $perPage = 25; // Number of items per page

    $chat_box = ChatBox::where('user_id', Auth::user()->id)
        ->select('uid', 'id', 'to', 'from','sending_server_id','updated_at', 'notification')
        ->where('reply_by_customer', true)
        ->orderBy('updated_at', 'desc')
        ->paginate($perPage);

    foreach ($chat_box as $box) {
        if($box->from == null){
            $sending_server = SendingServer::find($box->sending_server_id);
            $s_server = strtolower($sending_server->name);
            $phone = PhoneNumbers::where('server',$s_server)->where('status','assigned')->inRandomOrder()->first();
            $box->from = $phone->number;
            $box->save();
        }
        $msg = ChatBoxMessage::where('box_id', $box->id)
            ->orderBy('id', 'desc') // Order by id in descending order
            ->select('message', \DB::raw('SUBSTRING(message, 1, 15) as message'))
            ->first();
        if ($msg) {
            $box->preview_msg = $msg->message . '...';
        } else {
            $box->preview_msg = " ";
        }
        $con = Contacts::where('phone', $box->to)->first();
        if ($con) {
            $box->c_name = $con->first_name . ' ' . $con->last_name;
        } else {
            $box->c_name = " ";
        }
    }

    $quickReplies = QuickResponse::where('user_id', auth()->user()->id)->get();

    return view('customer.ChatBox.index', [
        'pageConfigs'   => $pageConfigs,
        'chat_box'      => $chat_box,
        'quickReplies'  => $quickReplies,
        'trash'         => false,
    ]);
}
        
        /**
         * get all trashed chat box
         *
         * @return Application|Factory|View
         * @throws AuthorizationException
         */
        public function trash(): View|Factory|Application
        {
            $this->authorize('chat_box');

            $pageConfigs = [
                'pageHeader'    => false,
                'contentLayout' => "content-left-sidebar",
                'pageClass'     => 'chat-application',
            ];

            $chat_box = ChatBox::onlyTrashed()->where('user_id', Auth::user()->id)
                ->select('uid', 'id', 'to', 'from', 'updated_at', 'notification')
                ->where('reply_by_customer', true)
                ->take(100)
                ->orderBy('updated_at', 'desc')
                ->get();
            foreach($chat_box as $box){
               $msg = ChatBoxMessage::onlyTrashed()->where('box_id', $box->id)
                ->orderBy('id', 'desc') // Order by id in descending order
                ->select('message', \DB::raw('SUBSTRING(message, 1, 15) as message'))
                ->first();
                if($msg){
                    $box->preview_msg = $msg->message.'...';
                }
                else{
                    $box->preview_msg =" ";
                }
                $con= Contacts::where('phone',$box->to)->first();
                if($con){
                    $box->c_name = $con->first_name . ' ' . $con->last_name;
                }else{
                    $box->c_name =" ";
                }
            }
            $quickReplies = QuickResponse::where('user_id', auth()->user()->id)->get();
            return view('customer.ChatBox.index', [
                'pageConfigs' => $pageConfigs,
                'chat_box'    => $chat_box,
                'quickReplies'    => $quickReplies,
                'trash' => true
            ]);
        }


        /**
         * start new conversation
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         *
         */
        public function new(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('chat_box');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('chat-box'), 'name' => __('locale.menu.Chat Box')],
                ['name' => __('locale.labels.new_conversion')],
            ];

            $phone_numbers = PhoneNumbers::where('user_id', Auth::user()->id)->where('status', 'assigned')->cursor();

            if ( ! Auth::user()->customer->activeSubscription()) {
                return redirect()->route('customer.chatbox.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan_id = Auth::user()->customer->activeSubscription()->plan_id;

            $coverage = CustomerBasedPricingPlan::where('user_id', Auth::user()->id)->where('status', true)->cursor();
            if ($coverage->count() < 1) {
                $coverage = PlansCoverageCountries::where('plan_id', $plan_id)->where('status', true)->cursor();
            }


            $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->get();


            return view('customer.ChatBox.new', compact('breadcrumbs', 'phone_numbers', 'coverage', 'sendingServers'));
        }
        
        public function search(Request $request): JsonResponse
        {
           $box_a = ChatBoxMessage::whereRaw('LOWER(message) LIKE ?', ['%' . strtolower($request->input) . '%'])
                    ->pluck('box_id')
                    ->toArray();
            $cb = ChatBox::whereIn('id',$box_a)->where('user_id',$request->authUserId)->pluck('id')->toArray();
            return response()->json([
                'status'  => 'success',
                'data' =>$cb
            ]);
        }

        /**
         * start new conversion
         *
         * @param Campaigns   $campaign
         * @param SentRequest $request
         *
         * @return RedirectResponse
         * @throws AuthorizationException|NumberParseException
         */
        public function sent(Campaigns $campaign, SentRequest $request): RedirectResponse
        {
            if (config('app.stage') === 'demo') {
                return redirect()->route('customer.chatbox.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.demo_mode_not_available'),
                ]);
            }

            $this->authorize('chat_box');

            $sendingServers = CustomerBasedSendingServer::where('user_id', Auth::user()->id)->where('status', 1)->count();

            if ($sendingServers && ! isset($request->sending_server)) {
                return redirect()->route('customer.chatbox.index')->with([
                    'status'  => 'error',
                    'message' => 'Please select your sending server',
                ]);
            }


            $input    = $request->except('_token');
            $senderId = $request->input('sender_id');
            $sms_type = $request->input('sms_type');

            $user    = Auth::user();
            $country = Country::find($request->input('country_code'));

            if ( ! $country) {
                return redirect()->route('customer.chatbox.index')->with([
                    'status'  => 'error',
                    'message' => "Permission to send an SMS has not been enabled for the region indicated by the 'To' number: " . $input['recipient'],
                ]);
            }

            $phoneNumberUtil   = PhoneNumberUtil::getInstance();
            $phoneNumberObject = $phoneNumberUtil->parse('+' . $country->country_code . $request->input('recipient'));
            $countryCode       = $phoneNumberObject->getCountryCode();

            if ($phoneNumberObject->isItalianLeadingZero()) {
                $phone = '0' . $phoneNumberObject->getNationalNumber();
            } else {
                $phone = $phoneNumberObject->getNationalNumber();
            }

            $input['country_code'] = $countryCode;
            $input['recipient']    = $phone;
            $input['user']         = Auth::user();

            $planId = $user->customer->activeSubscription()->plan_id;

            $coverage = CustomerBasedPricingPlan::where('user_id', $user->id)
                ->where('status', true)
                ->with('sendingServer')
                ->first();

            if ( ! $coverage) {
                $coverage = PlansCoverageCountries::where('plan_id', $planId)
                    ->where('status', true)
                    ->with('sendingServer')
                    ->first();
            }

            if ( ! $coverage) {
                return redirect()->route('customer.chatbox.index')->with([
                    'status'  => 'error',
                    'message' => 'Price Plan unavailable',
                ]);
            }

            $sendingServer = isset($$request->sending_server) ? SendingServer::find($request->sending_server) : $coverage->sendingServer;

            if ( ! $sendingServer) {
                return redirect()->route('customer.chatbox.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.campaigns.sending_server_not_available'),
                ]);
            }

            $db_sms_type = $sms_type == 'unicode' ? 'plain' : $sms_type;


            if ( ! $sendingServer->{$db_sms_type}) {
                return redirect()->route('customer.chatbox.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.sending_servers.sending_server_sms_capabilities', ['type' => strtoupper($db_sms_type)]),
                ]);
            }

            if ($sendingServer->settings === 'Whatsender' || $sendingServer->type === 'whatsapp') {
                $input['sms_type'] = 'whatsapp';
            }

            $db_sms_type       = ($sms_type === 'unicode') ? 'plain' : $sms_type;
            $capabilities_type = ($sms_type === 'plain' || $sms_type === 'unicode') ? 'sms' : $sms_type;

            if ($user->customer->getOption('sender_id_verification') === 'yes') {
                $number = PhoneNumbers::where('number', $senderId)
                    ->where('status', 'assigned')
                    ->first();

                if ( ! $number) {
                    return redirect()->route('customer.chatbox.index')->with([
                        'status'  => 'error',
                        'message' => __('locale.sender_id.sender_id_invalid', ['sender_id' => $senderId]),
                    ]);
                }

                $capabilities = str_contains($number->capabilities, $capabilities_type);

                if ( ! $capabilities) {
                    return redirect()->route('customer.chatbox.index')->with([
                        'status'  => 'error',
                        'message' => __('locale.sender_id.sender_id_sms_capabilities', ['sender_id' => $senderId, 'type' => $db_sms_type]),
                    ]);
                }

                $input['originator']   = 'phone_number';
                $input['phone_number'] = $senderId;
            }

            $input['reply_by_customer'] = true;

            $data = $this->campaigns->quickSend($campaign, $input);


            if (isset($data->getData()->status)) {
                return redirect()->route('customer.chatbox.index')->with([
                    'status'  => $data->getData()->status,
                    'message' => $data->getData()->message,
                ]);
            }

            return redirect()->route('customer.chatbox.index')->with([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);
        }


        /**
         * get chat messages
         *
         * @param ChatBox $box
         *
         * @return JsonResponse
         */
        public function messages(ChatBox $box): JsonResponse
        {
            $box->update([
                'notification' => 0,
            ]);

            $data = ChatBoxMessage::where('box_id', $box->id)
                ->orderBy('created_at')
                ->select('message', 'send_by', 'media_url', 'box_id')
                ->get(['message', 'send_by', 'media_url', 'box_id'])
                ->toJson();

            return response()->json([
                'status' => 'success',
                'data'   => $data,
            ]);

        }
         /**
         * get chat deleted messages
         *
         * @param $box
         *
         * @return JsonResponse
         */
        public function messages_trash($box): JsonResponse
        {
            $box = ChatBox::onlyTrashed()->where('uid',$box)->first();
            $box->update([
                'notification' => 0,
            ]);

            $data = ChatBoxMessage::onlyTrashed()->where('box_id', $box->id)
                ->orderBy('created_at')
                ->select('message', 'send_by', 'media_url', 'box_id')
                ->get(['message', 'send_by', 'media_url', 'box_id'])
                ->toJson();

            return response()->json([
                'status' => 'success',
                'data'   => $data,
            ]);

        }

        /**
         * get chat messages
         *
         * @param ChatBox $box
         *
         * @return JsonResponse
         */
        public function messagesWithNotification(ChatBox $box): JsonResponse
        {
            $data = ChatBoxMessage::where('box_id', $box->id)->select('message', 'send_by', 'media_url', 'box_id')->latest()->first()->toJson();

            return response()->json([
                'status' => 'success',
                'data'   => $data,
            ]);

        }
        public function getuser(){
            if(Auth::user()->is_customer && Auth::user()->is_reseller){
                return Auth::user();
            }else{
                return User::find(Auth::user()->admin_id);
            }  
        }

        /**
         * reply message
         *
         * @param ChatBox   $box
         * @param Campaigns $campaign
         * @param Request   $request
         *
         * @return JsonResponse
         * @throws AuthorizationException
         * @throws NumberParseException
         */
        public function reply(ChatBox $box, Campaigns $campaign, Request $request): JsonResponse
        {
            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }


            $this->authorize('chat_box');

            if (empty($request->message)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('locale.campaigns.insert_your_message'),
                ]);
            }
            $user = $this->getuser();
            $sending_server = SendingServer::find($box->sending_server_id);

            if ( ! $sending_server) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('locale.campaigns.sending_server_not_available'),
                ]);
            }
            if($sending_server->settings == 'Ejoin'){
                $client = new Client();
                $response = $client->request('GET', str_replace("goip_post_sms.html", "goip_get_status.html", $sending_server->api_link), [
                    'query' => [
                        'username' => $sending_server->username,
                        'password' => $sending_server->password,
                        'all_sims' => 1,
                        ],
                ]);
                $body = $response->getBody();
                $content = $body->getContents();
                $content = json_decode($content, true);
                $num=[];
                foreach($content['status'] as $num_ar){
                    if($num_ar['sn'] != ""){
                        $num[]=$num_ar['sn'];   
                    }
                }
                if (!in_array($box->from, $num)) {
                    if(isset($request->swap_num) && $request->swap_num == 1){
                        $r_num = $num[array_rand($num)];
                        $box->from = $r_num;
                        $box->save();
                        $phone_check = PhoneNumbers::where('number',$r_num)->where('status','assigned')->first();
                        if(!$phone_check){
                            $phone = new PhoneNumbers();
                            $phone->number = $r_num;
                            $phone->status = 'assigned';
                            $phone->server = strtolower($sending_server->name);
                            $phone->frequency_amount = 0;
                            $phone->price = 1;
                            $phone->user_id = $user->id;
                            $phone->capabilities = json_encode(['sms','mms']);
                            $phone->billing_cycle = 'monthly';
                            $phone->frequency_unit = 'month';
                            $phone->save();   
                        }
                    }else{
                        return response()->json([
                            'status'  => 'error',
                            'type_code'  => 'sim_emp',
                        ]);
                    }
                }
            }
            // if($box->from == null){
            //     if($box->sending_server_id == 6){
            //         $num = PhoneNumbers::where('status', 'assigned')->where('server','turbo s')->inRandomOrder()->first();   
            //     }else if(sending_server_id == 5){
            //         $num = PhoneNumbers::where('status', 'assigned')->where('server','turbo c')->inRandomOrder()->first();
            //     }
            //     $box->from = $num->number;
            //     $box->save();
            // }
            $sender_id = $box->from;
            if ($sending_server->settings == 'Whatsender' || $sending_server->type == 'whatsapp') {
                $sms_type          = 'whatsapp';
                $capabilities_type = $sms_type;
            } else {
                $sms_type          = 'plain';
                $capabilities_type = 'sms';
            }

            $input = [
                'sender_id'      => $sender_id,
                'sending_server' => $sending_server->id,
                'sms_type'       => $sms_type,
                'message'        => $request->message,
                'exist_c_code'   => 'yes',
                'user'           => $user,
            ];
            $input['org_customer'] = $user;
            $input['box_c'] = auth()->user();


            if ($user->customer->getOption('sender_id_verification') == 'yes') {

                $number = PhoneNumbers::where('number', $sender_id)->where('status', 'assigned')->first();

                if ( ! $number) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => __('locale.sender_id.sender_id_invalid', ['sender_id' => $sender_id]),
                    ]);
                }

                $capabilities = str_contains($number->capabilities, $capabilities_type);

                if ( ! $capabilities) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => __('locale.sender_id.sender_id_sms_capabilities', ['sender_id' => $sender_id, 'type' => $sms_type]),
                    ]);
                }

                $input['originator']   = 'phone_number';
                $input['phone_number'] = $sender_id;

            }

            try {

                $phoneUtil         = PhoneNumberUtil::getInstance();
                $phoneNumberObject = $phoneUtil->parse('+' . $box->to);

                if ($phoneUtil->isPossibleNumber($phoneNumberObject)) {
                    $input['country_code'] = $phoneNumberObject->getCountryCode();
                    $input['recipient']    = $phoneNumberObject->getNationalNumber();

                    $data = $this->campaigns->quickSend($campaign, $input);

                    if (isset($data->getData()->status)) {
                        if ($data->getData()->status == 'success') {
                            return response()->json([
                                'status'  => 'success',
                                'message' => __('locale.campaigns.message_successfully_delivered'),
                            ]);
                        }

                        return response()->json([
                            'status'  => $data->getData()->status,
                            'message' => $data->getData()->message,
                        ]);

                    }

                    return response()->json([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);
                }

                return response()->json([
                    'status'  => 'error',
                    'message' => __('locale.customer.invalid_phone_number', ['phone' => $box->to]),
                ]);

            } catch (NumberParseException $exception) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $exception->getMessage(),
                ]);
            }
        }
        
        public function swap_num(ChatBox $box){
            $sending_server = SendingServer::find($box->sending_server_id);
            if($sending_server->settings == 'Ejoin'){
                $client = new Client();
                $response = $client->request('GET', str_replace("goip_post_sms.html", "goip_get_status.html", $sending_server->api_link), [
                    'query' => [
                        'username' => $sending_server->username,
                        'password' => $sending_server->password,
                        'all_sims' => 1,
                        ],
                ]);
                $body = $response->getBody();
                $content = $body->getContents();
                $content = json_decode($content, true);
                $num=[];
                foreach($content['status'] as $num_ar){
                   if($num_ar['sn'] != ""){
                        $num[]=$num_ar['sn'];   
                    }
                }
                $r_num = $num[array_rand($num)];
                $box->from = $r_num;
                $box->save();
                $phone_check = PhoneNumbers::where('number',$r_num)->where('status','assigned')->first();
                if(!$phone_check){
                    $phone = new PhoneNumbers();
                    $phone->number = $r_num;
                    $phone->status = 'assigned';
                    $phone->server = strtolower($sending_server->name);
                    $phone->frequency_amount = 0;
                    $phone->price = 1;
                    $phone->user_id = $user->id;
                    $phone->capabilities = json_encode(['sms','mms']);
                    $phone->billing_cycle = 'monthly';
                    $phone->frequency_unit = 'month';
                    $phone->save();   
                    return response()->json([
                        'status'  => 'success',
                        'phone'  => $r_num,
                    ]);
                }else{
                    return response()->json([
                        'status'  => 'success',
                        'phone'  => $r_num,
                    ]);
                }
            }else{
                return response()->json([
                    'status'  => 'error',
                    'message' => "This Gateway doesnot supports Swaping",
                ]);
            }
        }

        /**
         * delete chatbox messages
         *
         * @param ChatBox $box
         *
         * @return JsonResponse
         */
        public function delete(ChatBox $box): JsonResponse
        {
            $messages = ChatBoxMessage::where('box_id', $box->id)->delete();
            if ($messages) {
                $box->delete();

                return response()->json([
                    'status'  => 'success',
                    'message' => __('locale.campaigns.sms_was_successfully_deleted'),
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);
        }
        /**
         * restore chatbox messages
         *
         * @param $box
         *
         * @return JsonResponse
         */
        public function restore($box): JsonResponse
        {
            $box = ChatBox::withTrashed()->where('uid',$box)->first();
            $box->restore();
            $messages = ChatBoxMessage::withTrashed()->where('box_id', $box->id)->get();
            if ($messages) {
                foreach($messages as $item){
                    $item->restore();
                }
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Chat Successfully restored',
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);
        }
        public function bulkDelete(Request $request): JsonResponse
        {
            try {
                foreach ($request->chat_box as $boxId) {
                    $box = ChatBox::find($boxId);
        
                    if (!$box) {
                        throw new \Exception("Chat box with ID $boxId not found.");
                    }
        
                    $messages = ChatBoxMessage::where('box_id', $box->id)->delete();
        
                    if ($messages !== false) {
                        $box->delete();
                    } else {
                        throw new \Exception("Error deleting messages for chat box with ID $boxId.");
                    }
                }
        
                return response()->json([
                    'status'  => 'success',
                    'message' => "All Chatboxes Deleted",
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
                    'error'   => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(), // Include the stack trace for debugging
                ]);
            }
        }


        /**
         * add to blacklist
         *
         * @param ChatBox $box
         *
         * @return JsonResponse
         */
        public function block(ChatBox $box): JsonResponse
        {
            $status = Blacklists::create([
                'user_id' => auth()->user()->id,
                'number'  => $box->to,
                'reason'  => 'Blacklisted by ' . auth()->user()->displayName(),
            ]);

            if ($status) {

                $contact = Contacts::where('phone', $box->to)->first();
                $contact?->update([
                    'status' => 'unsubscribe',
                ]);

                return response()->json([
                    'status'  => 'success',
                    'message' => __('locale.blacklist.blacklist_successfully_added'),
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);
        }

    }
