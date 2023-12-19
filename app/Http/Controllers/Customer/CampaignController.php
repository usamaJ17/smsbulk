<?php

    namespace App\Http\Controllers\Customer;

    use App\Http\Requests\Campaigns\CampaignBuilderRequest;
    use App\Http\Requests\Campaigns\ImportRequest;
    use App\Http\Requests\Campaigns\ImportVoiceRequest;
    use App\Http\Requests\Campaigns\MMSCampaignBuilderRequest;
    use App\Http\Requests\Campaigns\MMSImportRequest;
    use App\Http\Requests\Campaigns\MMSQuickSendRequest;
    use App\Http\Requests\Campaigns\OTPCampaignBuilderRequest;
    use App\Http\Requests\Campaigns\OTPQuickSendRequest;
    use App\Http\Requests\Campaigns\QuickSendRequest;
    use App\Http\Requests\Campaigns\ViberCampaignBuilderRequest;
    use App\Http\Requests\Campaigns\ViberQuickSendRequest;
    use App\Http\Requests\Campaigns\VoiceCampaignBuilderRequest;
    use App\Http\Requests\Campaigns\VoiceQuickSendRequest;
    use App\Http\Requests\Campaigns\WhatsAppCampaignBuilderRequest;
    use App\Http\Requests\Campaigns\WhatsAppQuickSendRequest;
    use App\Library\Tool;
    use App\Models\Campaigns;
    use App\Models\ContactGroups;
    use App\Models\Country;
    use App\Models\CsvData;
    use App\Models\CustomerBasedPricingPlan;
    use App\Models\CustomerBasedSendingServer;
    use App\Models\PhoneNumbers;
    use GuzzleHttp\Client;
    use App\Models\SendingServer;
    use App\Models\Plan;
    use App\Models\PlansCoverageCountries;
    use App\Models\Senderid;
    use App\Models\Templates;
    use App\Models\TemplateTags;
    use App\Models\User;
    use App\Repositories\Contracts\CampaignRepository;
    use Illuminate\Auth\Access\AuthorizationException;
    use Illuminate\Contracts\Foundation\Application;
    use Illuminate\Contracts\View\Factory;
    use Illuminate\Contracts\View\View;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\RedirectResponse;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Str;
    use libphonenumber\NumberParseException;
    use libphonenumber\PhoneNumberUtil;
    use Maatwebsite\Excel\Facades\Excel;
    use stdClass;

    class CampaignController extends CustomerBaseController
    {
        protected CampaignRepository $campaigns;

        /**
         * CampaignController constructor.
         *
         * @param CampaignRepository $campaigns
         */
        public function __construct(CampaignRepository $campaigns)
        {
            $this->campaigns = $campaigns;
        }

        /**
         * quick send message
         *
         *
         * @param Request $request
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function quickSend(Request $request): View|Factory|RedirectResponse|Application
        {
            $this->authorize('sms_quick_send');
            $user = $this->getuser();
            $recipient   = $request->input('recipient');
            $countryCode = null;

            if ($recipient) {
                $phone = str_replace(['(', ')', '+', '-', ' '], '', $recipient);

                try {
                    $phoneUtil         = PhoneNumberUtil::getInstance();
                    $phoneNumberObject = $phoneUtil->parse('+' . $phone);

                    if ( ! $phoneUtil->isPossibleNumber($phoneNumberObject)) {
                        return redirect()->route('customer.subscriptions.index')->with([
                            'status'  => 'error',
                            'message' => __('locale.customer.invalid_phone_number'),
                        ]);
                    }

                    $countryCode = $phoneNumberObject->getCountryCode();
                    $recipient   = $phoneNumberObject->isItalianLeadingZero()
                        ? '0' . $phoneNumberObject->getNationalNumber()
                        : $phoneNumberObject->getNationalNumber();

                } catch (NumberParseException $e) {
                    return redirect()->route('customer.subscriptions.index')->with([
                        'status'  => 'error',
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.SMS')],
                ['name' => __('locale.menu.Quick Send')],
            ];              
            $sender_ids = $user->customer->getOption('sender_id_verification') === 'yes'
                ? Senderid::where('user_id', $user->id)->where('status', 'active')->get()
                : null;

            $phone_numbers = PhoneNumbers::where('user_id', $user->id)->where('server', 'not like', 'turbo s%')->where('status', 'assigned')->get();

            $activeSubscription = $user->customer->activeSubscription();
            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan_id = $activeSubscription->plan_id;

            $coverage = CustomerBasedPricingPlan::where('plan_id', $plan_id)
                ->where('status', true)
                ->where('user_id', $user->id)
                ->get();

            if ($coverage->count() < 1) {
                $coverage = PlansCoverageCountries::where('plan_id', $plan_id)
                    ->where('status', true)
                    ->get();
            }

            $templates = Templates::where('user_id', auth()->user()->id)->where('status', 1)->get();

            $sendingServers = CustomerBasedSendingServer::where('user_id', $user->id)->where('status', 1)->get();

            return view('customer.Campaigns.quickSend', compact(
                'breadcrumbs',
                'sender_ids',
                'phone_numbers',
                'recipient',
                'coverage',
                'templates',
                'countryCode',
                'sendingServers'
            ));
        }
        public function getuser(){
            if(Auth::user()->is_customer && Auth::user()->is_reseller){
                return Auth::user();
            }else{
                return User::find(Auth::user()->admin_id);
            }  
        }

        /**
         * quick send message
         *
         * @param Campaigns        $campaign
         * @param QuickSendRequest $request
         *
         * @return RedirectResponse
         */
        public function postQuickSend(Campaigns $campaign, QuickSendRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.sms.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            if (config('app.trai_dlt') && $request->input('dlt_template_id') == null) {
                return redirect()->route('customer.sms.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'DLT Template id is required',
                ]);
            }
            $phone_number = '';
            $user = $this->getuser();
            $sending_server = SendingServer::find($request->sending_server);
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
                    if(isset($num_ar['sn']) && $num_ar['sn'] != ""){
                        $num[]=$num_ar['sn'];   
                    }
                }
                $phone_number = $num[array_rand($num)];
                $phone_check = PhoneNumbers::where('number',$phone_number)->where('status','assigned')->first();
                if(!$phone_check){
                    $phone = new PhoneNumbers();
                    $phone->number = $phone_number;
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
            }
                
            $request->merge([
                'phone_number' => null
            ]);
            $request->merge([
                'phone_number' => $phone_number
            ]);
            $activeSubscription = $user->customer->activeSubscription();
            if ($activeSubscription) {
                $plan = Plan::where('status', true)->find($activeSubscription->plan_id);
                if ( ! $plan) {
                    return redirect()->route('customer.sms.quick_send')->with([
                        'status'  => 'error',
                        'message' => 'Purchased plan is not active. Please contact support team.',
                    ]);
                }
            }

            $recipients = $this->getRecipients($request);

            if ($recipients->count() < 1) {
                return redirect()->route('customer.sms.quick_send')->with([
                    'status'  => 'error',
                    'message' => __('locale.campaigns.at_least_one_number'),
                ]);
            }

            if ($recipients->count() > 100) {
                return redirect()->route('customer.sms.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'You cannot send more than 100 SMS in a single request.',
                ]);
            }

            $sendData = $request->except('_token', 'recipients', 'delimiter');

            $errors  = [];
            $success = [];

            $sendingServers = CustomerBasedSendingServer::where('user_id', $user->id)->where('status', 1)->count();

            if ($sendingServers && ! isset($request->sending_server)) {
                return redirect()->route('customer.sms.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Please select your sending server',
                ]);
            }

            $validateData = $this->campaigns->checkQuickSendValidation($sendData);

            if ($validateData->getData()->status == 'error') {
                return redirect()->route('customer.sms.quick_send')->with([
                    'status'  => 'error',
                    'message' => $validateData->getData()->message,
                ]);
            }

            $sendData['sender_id'] = $validateData->getData()->sender_id;
            $sendData['sms_type']  = $validateData->getData()->sms_type;
            $sendData['user']      = User::find($validateData->getData()->user_id);

            foreach ($recipients as $recipient) {
                $recipient = str_replace(['(', ')', '+', '-', ' '], '', $recipient);

                $phone = $this->getPhoneNumber($recipient, $request->input('country_code'));

                $sendData['country_code'] = $phone['country_code'];
                $sendData['recipient']    = Str::startsWith($recipient, $phone['country_code'])
                    ? Str::replaceFirst($phone['country_code'], '', $recipient)
                    : $recipient;
                $sendData['org_customer'] = auth()->user();
                $data = $this->campaigns->quickSend($campaign, $sendData);

                if ($data->getData()->status === 'error') {
                    $errors[] = $data->getData()->message;
                } else if ($data->getData()->status === 'info' || $data->getData()->status === 'success') {
                    $success[] = $data->getData()->message;
                }
            }

            if ( ! empty($errors)) {
                $errorMessage = implode('<br>', $errors);

                return redirect()->route('customer.sms.quick_send')->with([
                    'status'  => 'error',
                    'message' => $errorMessage,
                ]);
            }

            $successMessage = implode('<br>', $success);

            return redirect()->route('customer.reports.sent')->with([
                'status'  => 'info',
                'message' => $successMessage,
            ]);
        }

        private function getRecipients($request)
        {
            $delimiter  = $request->input('delimiter');
            $recipients = $request->input('recipients');

            $recipientsArray = match ($delimiter) {
                ',', ';', '|' => collect(explode($delimiter, $recipients)),
                'tab' => collect(explode(' ', $recipients)),
                'new_line' => collect(explode("\n", $recipients)),
                default => collect([$recipients]),
            };

            return $recipientsArray->map(function ($item) {
                return trim($item);
            })->filter(function ($item) {
                return ! empty($item);
            })->unique();

        }

        private function getPhoneNumber($recipient, $countryCodeInput)
        {
            try {
                $countryCode = null;
                if ($countryCodeInput != 0) {
                    $country = Country::find($countryCodeInput);
                    if ($country) {
                        $countryCode = $country->country_code;
                    }
                }

                $phoneUtil         = PhoneNumberUtil::getInstance();
                $phoneNumberObject = $phoneUtil->parse('+' . $countryCode . $recipient);
                $countryCode       = $phoneNumberObject->getCountryCode();

                $nationalNumber = $phoneNumberObject->isItalianLeadingZero()
                    ? '0' . $phoneNumberObject->getNationalNumber()
                    : $phoneNumberObject->getNationalNumber();

                return [
                    'country_code' => $countryCode,
                    'recipient'    => $nationalNumber,
                ];
            } catch (NumberParseException $exception) {
                return $exception->getMessage();
            }
        }


        /**
         * campaign builder
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function campaignBuilder(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('sms_campaign_builder');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.SMS')],
                ['name' => __('locale.menu.Campaign Builder')],
            ];

            $compactData                = $this->getCampaignBuilderData();
            $compactData['breadcrumbs'] = $breadcrumbs;


            return view('customer.Campaigns.campaignBuilder', $compactData);
        }

        /**
         * template info isn't found
         *
         * @param Templates $template
         * @param           $id
         *
         * @return JsonResponse
         */
        public function templateData(Templates $template, $id): JsonResponse
        {
            $data = $template->where('user_id', auth()->user()->id)->find($id);
            if ($data) {
                return response()->json([
                    'status'          => 'success',
                    'dlt_template_id' => $data->dlt_template_id,
                    'message'         => $data->message,
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => __('locale.templates.template_info_not_found'),
            ]);
        }


        /**
         * store campaign
         *
         *
         * @param Campaigns              $campaign
         * @param CampaignBuilderRequest $request
         *
         * @return RedirectResponse
         */
        public function storeCampaign(Campaigns $campaign, CampaignBuilderRequest $request): RedirectResponse
        {
            if (!empty($request->template)) {
                $request->merge([
                    'message' => "c-t-p".json_encode($request->template),
                ]);
            }
            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.sms.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            if (config('app.trai_dlt') && $request->input('dlt_template_id') == null) {
                return redirect()->route('customer.sms.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => 'DLT Template id is required',
                ]);
            }
            $user = $this->getuser();
            if (isset($request->turbo_s_sel) && $request->turbo_s_sel == 1) {
                $num_arr = [];
            
                // Check if 'phone_number' is already set in the request
                if (isset($request->phone_number)) {
                    $request->merge([
                        'phone_number' => null,
                    ]);
                } else {
                    // Retrieve phone numbers and set 'phone_number' in the request
                    $p = PhoneNumbers::where('server', $request->turbo_s_name)
                        ->pluck('number')
                        ->toArray();
                    if (empty($p)) {
                        $p=['1'];
                    }
                    $request->merge([
                        'phone_number' => $p,
                    ]);
                }
            }
            $customer           = $user->customer;
            $activeSubscription = $customer->activeSubscription();

            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan = Plan::where('status', true)->find($activeSubscription->plan_id);

            if ( ! $plan) {
                return redirect()->route('customer.sms.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => 'Purchased plan is not active. Please contact support team.',
                ]);
            }
            $request->merge(['org_user_id' => $user]);
            $data = $this->campaigns->campaignBuilder($campaign, $request->except('_token'));

            if (isset($data->getData()->status)) {

                if ($data->getData()->status == 'success') {
                    return redirect()->route('customer.reports.campaigns')->with([
                        'status'  => 'success',
                        'message' => $data->getData()->message,
                    ]);
                }

                return redirect()->route('customer.sms.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => $data->getData()->message,
                ]);
            }

            return redirect()->route('customer.sms.campaign_builder')->with([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);
        }

        /**
         * send a message using file
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function import(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('sms_bulk_messages');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.SMS')],
                ['name' => __('locale.menu.Send Using File')],
            ];

            $compactData                = $this->getCampaignBuilderData();
            $compactData['breadcrumbs'] = $breadcrumbs;

            return view('customer.Campaigns.import', $compactData);
        }


        /**
         * send a message using file
         *
         * @param ImportRequest $request
         *
         * @return Application|Factory|View|RedirectResponse
         */
        public function importCampaign(ImportRequest $request): View|Factory|RedirectResponse|Application
        {
            if ($request->file('import_file')->isValid()) {

                $breadcrumbs = [
                    ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                    ['link' => url('dashboard'), 'name' => __('locale.menu.SMS')],
                    ['name' => __('locale.menu.Send Using File')],
                ];

                $form_data = $request->except('_token', 'import_file');
                $file      = $request->file('import_file');
                $ref_id    = uniqid();
                $data      = Excel::toArray(new stdClass(), $request->file('import_file'))[0];

                if ( ! is_array($data) && count($data) > 0) {
                    return redirect()->route('customer.sms.import')->with([
                        'status'  => 'error',
                        'message' => __('locale.settings.invalid_file'),
                    ]);
                }

                $csv_data    = array_slice($data, 0, 2);
                $path        = 'app/bulk_sms/';
                $upload_path = storage_path($path);

                if ( ! file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                $filename = 'sms-' . $ref_id . '.' . $file->getClientOriginalExtension();

                // save to server
                $file->move($upload_path, $filename);

                $csv_data_file = CsvData::create([
                    'user_id'      => Auth::user()->id,
                    'ref_id'       => $ref_id,
                    'ref_type'     => CsvData::TYPE_CAMPAIGN,
                    'csv_filename' => $filename,
                    'csv_header'   => $request->has('header'),
                    'csv_data'     => $path . $filename,
                ]);


                return view('customer.Campaigns.import_fields', compact('csv_data', 'csv_data_file', 'breadcrumbs', 'form_data'));
            }

            return redirect()->route('customer.sms.import')->with([
                'status'  => 'error',
                'message' => __('locale.settings.invalid_file'),
            ]);
        }

        /**
         * import processed file
         *
         * @param Campaigns $campaign
         * @param Request   $request
         *
         * @return RedirectResponse
         */
        public function importProcess(Campaigns $campaign, Request $request): RedirectResponse
        {

            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.sms.import')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $customer           = Auth::user()->customer;
            $activeSubscription = $customer->activeSubscription();

            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan = Plan::where('status', true)->find($activeSubscription->plan_id);

            if ( ! $plan) {
                return redirect()->route('customer.sms.import')->with([
                    'status'  => 'error',
                    'message' => 'Purchased plan is not active. Please contact support team.',
                ]);
            }

            $form_data = json_decode($request->input('form_data'), true);

            $data = $this->campaigns->sendUsingFile($campaign, $request->except('_token'));

            $sms_type = $form_data['sms_type'] == 'plain' ? 'sms' : $form_data['sms_type'];
            $status   = isset($data->getData()->status) ? $data->getData()->status : 'error';
            $message  = isset($data->getData()->message) ? $data->getData()->message : __('locale.exceptions.something_went_wrong');

            if ($status == 'error') {
                return redirect()->route('customer.' . $sms_type . '.import')->with([
                    'status'  => $status,
                    'message' => $message,
                ]);
            }

            return redirect()->route('customer.reports.campaigns')->with([
                'status'  => $status,
                'message' => $message,
            ]);

        }


        /*
        |--------------------------------------------------------------------------
        | voice module
        |--------------------------------------------------------------------------
        |
        |
        |
        */

        /**
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function voiceQuickSend(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('voice_quick_send');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.Voice')],
                ['name' => __('locale.menu.Quick Send')],
            ];

            $sender_ids = Auth::user()->customer->getOption('sender_id_verification') === 'yes'
                ? Senderid::where('user_id', auth()->user()->id)->where('status', 'active')->get()
                : null;

            $phone_numbers = PhoneNumbers::where('user_id', auth()->user()->id)->where('status', 'assigned')->get();

            $activeSubscription = Auth::user()->customer->activeSubscription();
            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan_id = $activeSubscription->plan_id;

            $coverage = CustomerBasedPricingPlan::where('status', true)
                ->where('user_id', Auth::user()->id)
                ->get();

            if ($coverage->count() < 1) {
                $coverage = PlansCoverageCountries::where('plan_id', $plan_id)
                    ->where('status', true)
                    ->get();
            }

            $templates      = Templates::where('user_id', auth()->user()->id)->where('status', 1)->get();
            $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->get();

            return view('customer.Campaigns.voiceQuickSend', compact('breadcrumbs', 'sender_ids', 'phone_numbers', 'coverage', 'templates', 'sendingServers'));
        }

        /**
         * quick send message
         *
         * @param Campaigns             $campaign
         * @param VoiceQuickSendRequest $request
         *
         * @return RedirectResponse
         */
        public function postVoiceQuickSend(Campaigns $campaign, VoiceQuickSendRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.voice.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $activeSubscription = Auth::user()->customer->activeSubscription();
            if ($activeSubscription) {
                $plan = Plan::where('status', true)->find($activeSubscription->plan_id);
                if ( ! $plan) {
                    return redirect()->route('customer.voice.quick_send')->with([
                        'status'  => 'error',
                        'message' => 'Purchased plan is not active. Please contact support team.',
                    ]);
                }
            }

            $recipients = $this->getRecipients($request);

            if ($recipients->count() < 1) {
                return redirect()->route('customer.voice.quick_send')->with([
                    'status'  => 'error',
                    'message' => __('locale.campaigns.at_least_one_number'),
                ]);
            }

            if ($recipients->count() > 100) {
                return redirect()->route('customer.voice.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'You cannot send more than 100 SMS in a single request.',
                ]);
            }

            $sendData = $request->except('_token', 'recipients', 'delimiter');

            $errors  = [];
            $success = [];

            $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->count();

            if ($sendingServers && ! isset($request->sending_server)) {
                return redirect()->route('customer.voice.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Please select your sending server',
                ]);
            }

            $validateData = $this->campaigns->checkQuickSendValidation($sendData);

            if ($validateData->getData()->status == 'error') {
                return redirect()->route('customer.voice.quick_send')->with([
                    'status'  => 'error',
                    'message' => $validateData->getData()->message,
                ]);
            }

            $sendData['sender_id'] = $validateData->getData()->sender_id;
            $sendData['sms_type']  = $validateData->getData()->sms_type;
            $sendData['user']      = User::find($validateData->getData()->user_id);

            foreach ($recipients as $recipient) {

                $phone                    = $this->getPhoneNumber($recipient, $request->input('country_code'));
                $sendData['country_code'] = $phone['country_code'];
                $sendData['recipient']    = $phone['recipient'];

                $data = $this->campaigns->quickSend($campaign, $sendData);


                if ($data->getData()->status === 'error') {
                    $errors[] = $data->getData()->message;
                } else if ($data->getData()->status === 'info') {
                    $success[] = $data->getData()->message;
                } else if ($data->getData()->status === 'success') {
                    $success[] = $data->getData()->message;
                }
            }

            if ( ! empty($errors)) {
                $errorMessage = implode(' ', $errors);

                return redirect()->route('customer.voice.quick_send')->with([
                    'status'  => 'error',
                    'message' => $errorMessage,
                ]);
            }

            $successMessage = implode(' ', $success);

            return redirect()->route('customer.reports.sent')->with([
                'status'  => 'info',
                'message' => $successMessage,
            ]);

        }


        /**
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function voiceCampaignBuilder(): View|Factory|RedirectResponse|Application
        {

            $this->authorize('voice_campaign_builder');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.Voice')],
                ['name' => __('locale.menu.Campaign Builder')],
            ];

            $compactData                = $this->getCampaignBuilderData();
            $compactData['breadcrumbs'] = $breadcrumbs;

            return view('customer.Campaigns.voiceCampaignBuilder', $compactData);
        }

        /**
         * store campaign
         *
         *
         * @param Campaigns                   $campaign
         * @param VoiceCampaignBuilderRequest $request
         *
         * @return RedirectResponse
         */
        public function storeVoiceCampaign(Campaigns $campaign, VoiceCampaignBuilderRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.voice.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }


            $customer           = Auth::user()->customer;
            $activeSubscription = $customer->activeSubscription();

            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan = Plan::where('status', true)->find($activeSubscription->plan_id);

            if ( ! $plan) {
                return redirect()->route('customer.voice.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => 'Purchased plan is not active. Please contact support team.',
                ]);
            }

            $data = $this->campaigns->campaignBuilder($campaign, $request->except('_token'));

            if (isset($data->getData()->status)) {

                if ($data->getData()->status == 'success') {
                    return redirect()->route('customer.reports.campaigns')->with([
                        'status'  => 'success',
                        'message' => $data->getData()->message,
                    ]);
                }

                return redirect()->route('customer.voice.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => $data->getData()->message,
                ]);
            }

            return redirect()->route('customer.voice.campaign_builder')->with([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);

        }


        /**
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function voiceImport(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('voice_bulk_messages');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.Voice')],
                ['name' => __('locale.menu.Send Using File')],
            ];

            $compactData                = $this->getCampaignBuilderData();
            $compactData['breadcrumbs'] = $breadcrumbs;

            return view('customer.Campaigns.voiceImport', $compactData);
        }


        /**
         * send a message using file
         *
         * @param ImportVoiceRequest $request
         *
         * @return Application|Factory|View|RedirectResponse
         */
        public function importVoiceCampaign(ImportVoiceRequest $request): View|Factory|RedirectResponse|Application
        {

            if ($request->file('import_file')->isValid()) {

                $breadcrumbs = [
                    ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                    ['link' => url('dashboard'), 'name' => __('locale.menu.Voice')],
                    ['name' => __('locale.menu.Send Using File')],
                ];

                $form_data = $request->except('_token', 'import_file');
                $file      = $request->file('import_file');
                $ref_id    = uniqid();
                $data      = Excel::toArray(new stdClass(), $request->file('import_file'))[0];

                if ( ! is_array($data) && count($data) > 0) {
                    return redirect()->route('customer.voice.import')->with([
                        'status'  => 'error',
                        'message' => __('locale.settings.invalid_file'),
                    ]);
                }

                $csv_data    = array_slice($data, 0, 2);
                $path        = 'app/bulk_sms/';
                $upload_path = storage_path($path);

                if ( ! file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                $filename = 'voice-' . $ref_id . '.' . $file->getClientOriginalExtension();

                // save to server
                $file->move($upload_path, $filename);

                $csv_data_file = CsvData::create([
                    'user_id'      => Auth::user()->id,
                    'ref_id'       => $ref_id,
                    'ref_type'     => CsvData::TYPE_CAMPAIGN,
                    'csv_filename' => $filename,
                    'csv_header'   => $request->has('header'),
                    'csv_data'     => $path . $filename,
                ]);


                return view('customer.Campaigns.import_fields', compact('csv_data', 'csv_data_file', 'breadcrumbs', 'form_data'));
            }

            return redirect()->route('customer.voice.import')->with([
                'status'  => 'error',
                'message' => __('locale.settings.invalid_file'),
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | MMS module
        |--------------------------------------------------------------------------
        |
        |
        |
        */


        /**
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function mmsQuickSend(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('mms_quick_send');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.MMS')],
                ['name' => __('locale.menu.Quick Send')],
            ];

            $sender_ids = Auth::user()->customer->getOption('sender_id_verification') === 'yes'
                ? Senderid::where('user_id', auth()->user()->id)->where('status', 'active')->get()
                : null;

            $phone_numbers = PhoneNumbers::where('user_id', auth()->user()->id)->where('status', 'assigned')->get();

            $activeSubscription = Auth::user()->customer->activeSubscription();
            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan_id = $activeSubscription->plan_id;

            $coverage = CustomerBasedPricingPlan::where('status', true)
                ->where('user_id', Auth::user()->id)
                ->get();

            if ($coverage->count() < 1) {
                $coverage = PlansCoverageCountries::where('plan_id', $plan_id)
                    ->where('status', true)
                    ->get();
            }

            $templates      = Templates::where('user_id', auth()->user()->id)->where('status', 1)->get();
            $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->get();

            return view('customer.Campaigns.mmsQuickSend', compact('breadcrumbs', 'sender_ids', 'phone_numbers', 'coverage', 'templates', 'sendingServers'));
        }

        /**
         * quick send message
         *
         * @param Campaigns           $campaign
         * @param MMSQuickSendRequest $request
         *
         * @return RedirectResponse
         */
        public function postMMSQuickSend(Campaigns $campaign, MMSQuickSendRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.mms.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $activeSubscription = Auth::user()->customer->activeSubscription();
            if ($activeSubscription) {
                $plan = Plan::where('status', true)->find($activeSubscription->plan_id);
                if ( ! $plan) {
                    return redirect()->route('customer.mms.quick_send')->with([
                        'status'  => 'error',
                        'message' => 'Purchased plan is not active. Please contact support team.',
                    ]);
                }
            }

            $recipients = $this->getRecipients($request);

            if ($recipients->count() < 1) {
                return redirect()->route('customer.mms.quick_send')->with([
                    'status'  => 'error',
                    'message' => __('locale.campaigns.at_least_one_number'),
                ]);
            }

            if ($recipients->count() > 100) {
                return redirect()->route('customer.mms.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'You cannot send more than 100 SMS in a single request.',
                ]);
            }

            $sendData              = $request->except('_token', 'recipients', 'delimiter');
            $sendData['media_url'] = Tool::uploadImage($request->file('mms_file'));

            $errors  = [];
            $success = [];


            $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->count();

            if ($sendingServers && ! isset($request->sending_server)) {
                return redirect()->route('customer.mms.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Please select your sending server',
                ]);
            }

            $validateData = $this->campaigns->checkQuickSendValidation($sendData);

            if ($validateData->getData()->status == 'error') {
                return redirect()->route('customer.mms.quick_send')->with([
                    'status'  => 'error',
                    'message' => $validateData->getData()->message,
                ]);
            }

            $sendData['sender_id'] = $validateData->getData()->sender_id;
            $sendData['sms_type']  = $validateData->getData()->sms_type;
            $sendData['user']      = User::find($validateData->getData()->user_id);

            foreach ($recipients as $recipient) {

                $phone                    = $this->getPhoneNumber($recipient, $request->input('country_code'));
                $sendData['country_code'] = $phone['country_code'];
                $sendData['recipient']    = $phone['recipient'];

                $data = $this->campaigns->quickSend($campaign, $sendData);

                if ($data->getData()->status === 'error') {
                    $errors[] = $data->getData()->message;
                } else if ($data->getData()->status === 'info') {
                    $success[] = $data->getData()->message;
                }
            }

            if ( ! empty($errors)) {
                $errorMessage = implode(' ', $errors);

                return redirect()->route('customer.mms.quick_send')->with([
                    'status'  => 'error',
                    'message' => $errorMessage,
                ]);
            }

            $successMessage = implode(' ', $success);

            return redirect()->route('customer.reports.sent')->with([
                'status'  => 'info',
                'message' => $successMessage,
            ]);
        }

        /**
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function mmsCampaignBuilder(): View|Factory|RedirectResponse|Application
        {

            $this->authorize('mms_campaign_builder');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.MMS')],
                ['name' => __('locale.menu.Campaign Builder')],
            ];

            $compactData                = $this->getCampaignBuilderData();
            $compactData['breadcrumbs'] = $breadcrumbs;

            return view('customer.Campaigns.mmsCampaignBuilder', $compactData);
        }


        /**
         * store campaign
         *
         *
         * @param Campaigns                 $campaign
         * @param MMSCampaignBuilderRequest $request
         *
         * @return RedirectResponse
         */
        public function storeMMSCampaign(Campaigns $campaign, MMSCampaignBuilderRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.mms.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $customer           = Auth::user()->customer;
            $activeSubscription = $customer->activeSubscription();

            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan = Plan::where('status', true)->find($activeSubscription->plan_id);

            if ( ! $plan) {
                return redirect()->route('customer.mms.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => 'Purchased plan is not active. Please contact support team.',
                ]);
            }


            $data = $this->campaigns->campaignBuilder($campaign, $request->except('_token'));

            if (isset($data->getData()->status)) {

                if ($data->getData()->status == 'success') {
                    return redirect()->route('customer.reports.campaigns')->with([
                        'status'  => 'success',
                        'message' => $data->getData()->message,
                    ]);
                }

                return redirect()->route('customer.mms.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => $data->getData()->message,
                ]);
            }

            return redirect()->route('customer.mms.campaign_builder')->with([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);

        }

        /**
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function mmsImport(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('mms_bulk_messages');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.MMS')],
                ['name' => __('locale.menu.Send Using File')],
            ];


            $compactData                = $this->getCampaignBuilderData();
            $compactData['breadcrumbs'] = $breadcrumbs;

            return view('customer.Campaigns.mmsImport', $compactData);
        }


        /**
         * send a message using file
         *
         * @param MMSImportRequest $request
         *
         * @return Application|Factory|View|RedirectResponse
         */
        public function importMMSCampaign(MMSImportRequest $request): View|Factory|RedirectResponse|Application
        {

            if ($request->file('import_file')->isValid()) {

                $breadcrumbs = [
                    ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                    ['link' => url('dashboard'), 'name' => __('locale.menu.MMS')],
                    ['name' => __('locale.menu.Send Using File')],
                ];

                $media_url              = Tool::uploadImage($request->file('mms_file'));
                $form_data              = $request->except('_token', 'import_file', 'mms_file');
                $form_data['media_url'] = $media_url;
                $file                   = $request->file('import_file');

                $ref_id = uniqid();
                $data   = Excel::toArray(new stdClass(), $request->file('import_file'))[0];

                if ( ! is_array($data) && count($data) > 0) {
                    return redirect()->route('customer.mms.import')->with([
                        'status'  => 'error',
                        'message' => __('locale.settings.invalid_file'),
                    ]);
                }

                $csv_data    = array_slice($data, 0, 2);
                $path        = 'app/bulk_sms/';
                $upload_path = storage_path($path);

                if ( ! file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                $filename = 'mms-' . $ref_id . '.' . $file->getClientOriginalExtension();

                // save to server
                $file->move($upload_path, $filename);

                $csv_data_file = CsvData::create([
                    'user_id'      => Auth::user()->id,
                    'ref_id'       => $ref_id,
                    'ref_type'     => CsvData::TYPE_CAMPAIGN,
                    'csv_filename' => $filename,
                    'csv_header'   => $request->has('header'),
                    'csv_data'     => $path . $filename,
                ]);

                return view('customer.Campaigns.import_fields', compact('csv_data', 'csv_data_file', 'breadcrumbs', 'form_data'));
            }

            return redirect()->route('customer.mms.import')->with([
                'status'  => 'error',
                'message' => __('locale.settings.invalid_file'),
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | whatsapp module
        |--------------------------------------------------------------------------
        |
        |
        |
        */


        /**
         * whatsapp quick send
         *
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function whatsAppQuickSend(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('whatsapp_quick_send');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.WhatsApp')],
                ['name' => __('locale.menu.Quick Send')],
            ];

            $sender_ids = Auth::user()->customer->getOption('sender_id_verification') === 'yes'
                ? Senderid::where('user_id', auth()->user()->id)->where('status', 'active')->get()
                : null;

            $phone_numbers = PhoneNumbers::where('user_id', auth()->user()->id)->where('status', 'assigned')->get();

            $activeSubscription = Auth::user()->customer->activeSubscription();
            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan_id = $activeSubscription->plan_id;

            $coverage = CustomerBasedPricingPlan::where('status', true)
                ->where('user_id', Auth::user()->id)
                ->get();

            if ($coverage->count() < 1) {
                $coverage = PlansCoverageCountries::where('plan_id', $plan_id)
                    ->where('status', true)
                    ->get();
            }

            $templates      = Templates::where('user_id', auth()->user()->id)->where('status', 1)->get();
            $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->get();

            return view('customer.Campaigns.whatsAppQuickSend', compact('breadcrumbs', 'sender_ids', 'phone_numbers', 'coverage', 'templates', 'sendingServers'));
        }

        /**
         * quick send message
         *
         * @param Campaigns                $campaign
         * @param WhatsAppQuickSendRequest $request
         *
         * @return RedirectResponse
         */
        public function postWhatsAppQuickSend(Campaigns $campaign, WhatsAppQuickSendRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.whatsapp.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $activeSubscription = Auth::user()->customer->activeSubscription();
            if ($activeSubscription) {
                $plan = Plan::where('status', true)->find($activeSubscription->plan_id);
                if ( ! $plan) {
                    return redirect()->route('customer.whatsapp.quick_send')->with([
                        'status'  => 'error',
                        'message' => 'Purchased plan is not active. Please contact support team.',
                    ]);
                }
            }

            $recipients = $this->getRecipients($request);

            if ($recipients->count() < 1) {
                return redirect()->route('customer.whatsapp.quick_send')->with([
                    'status'  => 'error',
                    'message' => __('locale.campaigns.at_least_one_number'),
                ]);
            }

            if ($recipients->count() > 100) {
                return redirect()->route('customer.whatsapp.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'You cannot send more than 100 SMS in a single request.',
                ]);
            }

            $sendData = $request->except('_token', 'recipients', 'delimiter', 'mms_file', 'language');

            if (isset($request->language) && $request->language != '0') {
                $sendData['language'] = $request->language;
            }

            if (isset($request->mms_file)) {
                $sendData['media_url'] = Tool::uploadImage($request->file('mms_file'));
            }

            $errors  = [];
            $success = [];


            $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->count();

            if ($sendingServers && ! isset($request->sending_server)) {
                return redirect()->route('customer.whatsapp.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Please select your sending server',
                ]);
            }

            $validateData = $this->campaigns->checkQuickSendValidation($sendData);

            if ($validateData->getData()->status == 'error') {
                return redirect()->route('customer.whatsapp.quick_send')->with([
                    'status'  => 'error',
                    'message' => $validateData->getData()->message,
                ]);
            }

            $sendData['sender_id'] = $validateData->getData()->sender_id;
            $sendData['sms_type']  = $validateData->getData()->sms_type;
            $sendData['user']      = User::find($validateData->getData()->user_id);


            foreach ($recipients as $recipient) {

                $phone                    = $this->getPhoneNumber($recipient, $request->input('country_code'));
                $sendData['country_code'] = $phone['country_code'];
                $sendData['recipient']    = $phone['recipient'];

                $data = $this->campaigns->quickSend($campaign, $sendData);

                if ($data->getData()->status === 'error') {
                    $errors[] = $data->getData()->message;
                } else if ($data->getData()->status === 'info') {
                    $success[] = $data->getData()->message;
                }
            }

            if ( ! empty($errors)) {
                $errorMessage = implode(' ', $errors);

                return redirect()->route('customer.whatsapp.quick_send')->with([
                    'status'  => 'error',
                    'message' => $errorMessage,
                ]);
            }

            $successMessage = implode(' ', $success);

            return redirect()->route('customer.reports.sent')->with([
                'status'  => 'info',
                'message' => $successMessage,
            ]);
        }

        /**
         * whatsapp campaign builder
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function whatsappCampaignBuilder(): View|Factory|RedirectResponse|Application
        {

            $this->authorize('whatsapp_campaign_builder');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.WhatsApp')],
                ['name' => __('locale.menu.Campaign Builder')],
            ];

            $compactData                = $this->getCampaignBuilderData();
            $compactData['breadcrumbs'] = $breadcrumbs;


            return view('customer.Campaigns.whatsAppCampaignBuilder', $compactData);
        }


        /**
         * store campaign
         *
         *
         * @param Campaigns                      $campaign
         * @param WhatsAppCampaignBuilderRequest $request
         *
         * @return RedirectResponse
         */
        public function storeWhatsAppCampaign(Campaigns $campaign, WhatsAppCampaignBuilderRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.whatsapp.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $customer           = Auth::user()->customer;
            $activeSubscription = $customer->activeSubscription();

            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan = Plan::where('status', true)->find($activeSubscription->plan_id);

            if ( ! $plan) {
                return redirect()->route('customer.whatsapp.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => 'Purchased plan is not active. Please contact support team.',
                ]);
            }

            $data = $this->campaigns->campaignBuilder($campaign, $request->except('_token'));

            if (isset($data->getData()->status)) {

                if ($data->getData()->status == 'success') {
                    return redirect()->route('customer.reports.campaigns')->with([
                        'status'  => 'success',
                        'message' => $data->getData()->message,
                    ]);
                }

                return redirect()->route('customer.whatsapp.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => $data->getData()->message,
                ]);
            }

            return redirect()->route('customer.whatsapp.campaign_builder')->with([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);

        }

        /**
         * whatsapp send a message using file
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function whatsappImport(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('whatsapp_bulk_messages');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.WhatsApp')],
                ['name' => __('locale.menu.Send Using File')],
            ];

            $compactData                = $this->getCampaignBuilderData();
            $compactData['breadcrumbs'] = $breadcrumbs;

            return view('customer.Campaigns.whatsAppImport', $compactData);
        }


        /**
         * send a message using file
         *
         * @param ImportRequest $request
         *
         * @return Application|Factory|View|RedirectResponse
         */
        public function importWhatsAppCampaign(ImportRequest $request): View|Factory|RedirectResponse|Application
        {
            if ($request->hasFile('import_file') && $request->file('import_file')->isValid()) {

                $breadcrumbs = [
                    ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                    ['link' => url('dashboard'), 'name' => __('locale.menu.WhatsApp')],
                    ['name' => __('locale.menu.Send Using File')],
                ];

                $form_data = $request->except('_token', 'import_file', 'mms_file');

                if ($request->hasFile('mms_file') && $request->file('mms_file')->isValid()) {
                    $media_url              = Tool::uploadImage($request->file('mms_file'));
                    $form_data['media_url'] = $media_url;
                }

                if ($request->input('language') !== null) {
                    $form_data['language'] = $request->input('language');
                }

                $file   = $request->file('import_file');
                $ref_id = uniqid();
                $data   = Excel::toArray(new stdClass(), $request->file('import_file'))[0];

                if ( ! is_array($data) && count($data) > 0) {
                    return redirect()->route('customer.whatsapp.import')->with([
                        'status'  => 'error',
                        'message' => __('locale.settings.invalid_file'),
                    ]);
                }

                $csv_data    = array_slice($data, 0, 2);
                $path        = 'app/bulk_sms/';
                $upload_path = storage_path($path);

                if ( ! file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                $filename = 'whatsapp-' . $ref_id . '.' . $file->getClientOriginalExtension();

                // save to server
                $file->move($upload_path, $filename);

                $csv_data_file = CsvData::create([
                    'user_id'      => Auth::user()->id,
                    'ref_id'       => $ref_id,
                    'ref_type'     => CsvData::TYPE_CAMPAIGN,
                    'csv_filename' => $filename,
                    'csv_header'   => $request->has('header'),
                    'csv_data'     => $path . $filename,
                ]);


                return view('customer.Campaigns.import_fields', compact('csv_data', 'csv_data_file', 'breadcrumbs', 'form_data'));
            }

            return redirect()->route('customer.whatsapp.import')->with([
                'status'  => 'error',
                'message' => __('locale.settings.invalid_file'),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Version 3.5
        |--------------------------------------------------------------------------
        |
        | Campaign pause, restart, resend
        |
        */


        /**
         * Pause the Campaign
         *
         * @param Campaigns $campaign
         *
         * @return JsonResponse
         */
        public function campaignPause(Campaigns $campaign)
        {
            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $data = $this->campaigns->pause($campaign);

            if (isset($data->getData()->status)) {

                if ($data->getData()->status == 'success') {
                    return response()->json([
                        'status'  => 'success',
                        'message' => $data->getData()->message,
                    ]);
                }

                return response()->json([
                    'status'  => 'error',
                    'message' => $data->getData()->message,
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);
        }


        /**
         * Restart the Campaign
         *
         * @param Campaigns $campaign
         *
         * @return JsonResponse
         */
        public function campaignRestart(Campaigns $campaign)
        {
            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $data = $this->campaigns->restart($campaign);

            if (isset($data->getData()->status)) {

                if ($data->getData()->status == 'success') {
                    return response()->json([
                        'status'  => 'success',
                        'message' => $data->getData()->message,
                    ]);
                }

                return response()->json([
                    'status'  => 'error',
                    'message' => $data->getData()->message,
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);
        }

        /**
         * Resend the Campaign
         *
         * @param Campaigns $campaign
         *
         * @return JsonResponse
         */
        public function campaignResend(Campaigns $campaign)
        {
            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $data = $this->campaigns->resend($campaign);

            if (isset($data->getData()->status)) {

                if ($data->getData()->status == 'success') {
                    return response()->json([
                        'status'  => 'success',
                        'message' => $data->getData()->message,
                    ]);
                }

                return response()->json([
                    'status'  => 'error',
                    'message' => $data->getData()->message,
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);
        }


        /**
         * @return array|RedirectResponse
         */
        private function getCampaignBuilderData()
        {
            $user = $this->getuser();
            $customer = $user->customer;

            if ( ! $customer->activeSubscription()) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $sender_ids = $customer->getOption('sender_id_verification') === 'yes'
                ? Senderid::where('user_id', $user->id)->where('status', 'active')->get()
                : null;

            $phone_numbers  = PhoneNumbers::where('user_id', $user->id)->where('status', 'assigned')->where('server', 'not like', 'turbo s%')->get();
            $template_tags  = TemplateTags::get();
            $contact_groups = ContactGroups::where('status', 1)->where('customer_id', auth()->user()->id)->get();
            $templates      = Templates::where('user_id', auth()->user()->id)->where('status', 1)->get();
            $sendingServers = CustomerBasedSendingServer::where('user_id', $user->id)->where('status', 1)->get();
            if(auth()->user()->is_reseller && !auth()->user()->is_admin){
                $camp_users = User::where('admin_id', auth()->user()->id)->orWhere('id', auth()->user()->id)->get();
            }elseif(auth()->user()->is_customer && !auth()->user()->is_admin){
                $camp_users = User::where('id', auth()->user()->id)->get();
            }else{
                $camp_users = User::all();
            }


            $plan_id = $customer->activeSubscription()->plan_id;

            return [
                'sender_ids'     => $sender_ids,
                'phone_numbers'  => $phone_numbers,
                'template_tags'  => $template_tags,
                'contact_groups' => $contact_groups,
                'templates'      => $templates,
                'plan_id'        => $plan_id,
                'sendingServers' => $sendingServers,
                'camp_users'     => $camp_users,
            ];

        }



        /*
        |--------------------------------------------------------------------------
        | Viber module
        |--------------------------------------------------------------------------
        |
        |
        |
        */


        /**
         * Viber quickly sends
         *
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function viberQuickSend(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('viber_quick_send');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.Viber')],
                ['name' => __('locale.menu.Quick Send')],
            ];

            $sender_ids = Auth::user()->customer->getOption('sender_id_verification') === 'yes'
                ? Senderid::where('user_id', auth()->user()->id)->where('status', 'active')->get()
                : null;

            $phone_numbers = PhoneNumbers::where('user_id', auth()->user()->id)->where('status', 'assigned')->get();

            $activeSubscription = Auth::user()->customer->activeSubscription();
            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan_id = $activeSubscription->plan_id;

            $coverage = CustomerBasedPricingPlan::where('status', true)
                ->where('user_id', Auth::user()->id)
                ->get();

            if ($coverage->count() < 1) {
                $coverage = PlansCoverageCountries::where('plan_id', $plan_id)
                    ->where('status', true)
                    ->get();
            }

            $templates      = Templates::where('user_id', auth()->user()->id)->where('status', 1)->get();
            $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->get();

            return view('customer.Campaigns.viberQuickSend', compact('breadcrumbs', 'sender_ids', 'phone_numbers', 'coverage', 'templates', 'sendingServers'));
        }

        /**
         * quick send message
         *
         * @param Campaigns             $campaign
         * @param ViberQuickSendRequest $request
         *
         * @return RedirectResponse
         */
        public function postViberQuickSend(Campaigns $campaign, ViberQuickSendRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.viber.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $activeSubscription = Auth::user()->customer->activeSubscription();
            if ($activeSubscription) {
                $plan = Plan::where('status', true)->find($activeSubscription->plan_id);
                if ( ! $plan) {
                    return redirect()->route('customer.viber.quick_send')->with([
                        'status'  => 'error',
                        'message' => 'Purchased plan is not active. Please contact support team.',
                    ]);
                }
            }

            $recipients = $this->getRecipients($request);

            if ($recipients->count() < 1) {
                return redirect()->route('customer.viber.quick_send')->with([
                    'status'  => 'error',
                    'message' => __('locale.campaigns.at_least_one_number'),
                ]);
            }

            if ($recipients->count() > 100) {
                return redirect()->route('customer.viber.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'You cannot send more than 100 SMS in a single request.',
                ]);
            }

            $sendData = $request->except('_token', 'recipients', 'delimiter', 'mms_file');

            if (isset($request->mms_file)) {
                $sendData['media_url'] = Tool::uploadImage($request->file('mms_file'));
            }

            $errors  = [];
            $success = [];


            $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->count();

            if ($sendingServers && ! isset($request->sending_server)) {
                return redirect()->route('customer.viber.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Please select your sending server',
                ]);
            }

            $validateData = $this->campaigns->checkQuickSendValidation($sendData);

            if ($validateData->getData()->status == 'error') {
                return redirect()->route('customer.viber.quick_send')->with([
                    'status'  => 'error',
                    'message' => $validateData->getData()->message,
                ]);
            }

            $sendData['sender_id'] = $validateData->getData()->sender_id;
            $sendData['sms_type']  = $validateData->getData()->sms_type;
            $sendData['user']      = User::find($validateData->getData()->user_id);

            foreach ($recipients as $recipient) {

                $phone                    = $this->getPhoneNumber($recipient, $request->input('country_code'));
                $sendData['country_code'] = $phone['country_code'];
                $sendData['recipient']    = $phone['recipient'];

                $data = $this->campaigns->quickSend($campaign, $sendData);

                if ($data->getData()->status === 'error') {
                    $errors[] = $data->getData()->message;
                } else if ($data->getData()->status === 'success') {
                    $success[] = $data->getData()->message;
                }
            }

            if ( ! empty($errors)) {
                $errorMessage = implode(' ', $errors);

                return redirect()->route('customer.viber.quick_send')->with([
                    'status'  => 'error',
                    'message' => $errorMessage,
                ]);
            }

            $successMessage = implode(' ', $success);

            return redirect()->route('customer.reports.sent')->with([
                'status'  => 'info',
                'message' => $successMessage,
            ]);
        }

        /**
         * whatsapp campaign builder
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function viberCampaignBuilder(): View|Factory|RedirectResponse|Application
        {

            $this->authorize('viber_campaign_builder');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.Viber')],
                ['name' => __('locale.menu.Campaign Builder')],
            ];

            $compactData                = $this->getCampaignBuilderData();
            $compactData['breadcrumbs'] = $breadcrumbs;


            return view('customer.Campaigns.viberCampaignBuilder', $compactData);
        }


        /**
         * store campaign
         *
         *
         * @param Campaigns                   $campaign
         * @param ViberCampaignBuilderRequest $request
         *
         * @return RedirectResponse
         */
        public function storeViberCampaign(Campaigns $campaign, ViberCampaignBuilderRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.viber.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $customer           = Auth::user()->customer;
            $activeSubscription = $customer->activeSubscription();

            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan = Plan::where('status', true)->find($activeSubscription->plan_id);

            if ( ! $plan) {
                return redirect()->route('customer.viber.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => 'Purchased plan is not active. Please contact support team.',
                ]);
            }

            $data = $this->campaigns->campaignBuilder($campaign, $request->except('_token'));

            if (isset($data->getData()->status)) {

                if ($data->getData()->status == 'success') {
                    return redirect()->route('customer.reports.campaigns')->with([
                        'status'  => 'success',
                        'message' => $data->getData()->message,
                    ]);
                }

                return redirect()->route('customer.viber.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => $data->getData()->message,
                ]);
            }

            return redirect()->route('customer.viber.campaign_builder')->with([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);

        }


        /**
         * viber send a message using file
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function viberImport(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('viber_bulk_messages');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.Viber')],
                ['name' => __('locale.menu.Send Using File')],
            ];

            $compactData                = $this->getCampaignBuilderData();
            $compactData['breadcrumbs'] = $breadcrumbs;

            return view('customer.Campaigns.viberImport', $compactData);
        }


        /**
         * send a message using file
         *
         * @param ImportRequest $request
         *
         * @return Application|Factory|View|RedirectResponse
         */
        public function importViberCampaign(ImportRequest $request): View|Factory|RedirectResponse|Application
        {
            if ($request->hasFile('import_file') && $request->file('import_file')->isValid()) {

                $breadcrumbs = [
                    ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                    ['link' => url('dashboard'), 'name' => __('locale.menu.Viber')],
                    ['name' => __('locale.menu.Send Using File')],
                ];

                $form_data = $request->except('_token', 'import_file', 'mms_file');

                if ($request->hasFile('mms_file') && $request->file('mms_file')->isValid()) {
                    $media_url              = Tool::uploadImage($request->file('mms_file'));
                    $form_data['media_url'] = $media_url;
                }

                $file   = $request->file('import_file');
                $ref_id = uniqid();
                $data   = Excel::toArray(new stdClass(), $request->file('import_file'))[0];

                if ( ! is_array($data) && count($data) > 0) {
                    return redirect()->route('customer.viber.import')->with([
                        'status'  => 'error',
                        'message' => __('locale.settings.invalid_file'),
                    ]);
                }

                $csv_data    = array_slice($data, 0, 2);
                $path        = 'app/bulk_sms/';
                $upload_path = storage_path($path);

                if ( ! file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                $filename = 'viber-' . $ref_id . '.' . $file->getClientOriginalExtension();

                // save to server
                $file->move($upload_path, $filename);

                $csv_data_file = CsvData::create([
                    'user_id'      => Auth::user()->id,
                    'ref_id'       => $ref_id,
                    'ref_type'     => CsvData::TYPE_CAMPAIGN,
                    'csv_filename' => $filename,
                    'csv_header'   => $request->has('header'),
                    'csv_data'     => $path . $filename,
                ]);


                return view('customer.Campaigns.import_fields', compact('csv_data', 'csv_data_file', 'breadcrumbs', 'form_data'));
            }

            return redirect()->route('customer.viber.import')->with([
                'status'  => 'error',
                'message' => __('locale.settings.invalid_file'),
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | OTP Module
        |--------------------------------------------------------------------------
        |
        |
        |
        */


        /**
         * OTP quick send
         *
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function otpQuickSend(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('otp_quick_send');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.OTP')],
                ['name' => __('locale.menu.Quick Send')],
            ];

            $sender_ids = Auth::user()->customer->getOption('sender_id_verification') === 'yes'
                ? Senderid::where('user_id', auth()->user()->id)->where('status', 'active')->get()
                : null;

            $phone_numbers = PhoneNumbers::where('user_id', auth()->user()->id)->where('status', 'assigned')->get();

            $activeSubscription = Auth::user()->customer->activeSubscription();
            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan_id = $activeSubscription->plan_id;

            $coverage = CustomerBasedPricingPlan::where('status', true)
                ->where('user_id', Auth::user()->id)
                ->get();

            if ($coverage->count() < 1) {
                $coverage = PlansCoverageCountries::where('plan_id', $plan_id)
                    ->where('status', true)
                    ->get();
            }

            $templates      = Templates::where('user_id', auth()->user()->id)->where('status', 1)->get();
            $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->get();


            return view('customer.Campaigns.otpQuickSend', compact('breadcrumbs', 'sender_ids', 'phone_numbers', 'coverage', 'templates', 'sendingServers'));
        }

        /**
         * quick send message
         *
         * @param Campaigns           $campaign
         * @param OTPQuickSendRequest $request
         *
         * @return RedirectResponse
         */
        public function postOTPQuickSend(Campaigns $campaign, OTPQuickSendRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.otp.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $activeSubscription = Auth::user()->customer->activeSubscription();
            if ($activeSubscription) {
                $plan = Plan::where('status', true)->find($activeSubscription->plan_id);
                if ( ! $plan) {
                    return redirect()->route('customer.otp.quick_send')->with([
                        'status'  => 'error',
                        'message' => 'Purchased plan is not active. Please contact support team.',
                    ]);
                }
            }

            $recipients = $this->getRecipients($request);

            if ($recipients->count() < 1) {
                return redirect()->route('customer.otp.quick_send')->with([
                    'status'  => 'error',
                    'message' => __('locale.campaigns.at_least_one_number'),
                ]);
            }

            if ($recipients->count() > 100) {
                return redirect()->route('customer.otp.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'You cannot send more than 100 SMS in a single request.',
                ]);
            }

            $sendData = $request->except('_token', 'recipients', 'delimiter');


            $errors  = [];
            $success = [];


            $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->count();

            if ($sendingServers && ! isset($request->sending_server)) {
                return redirect()->route('customer.otp.quick_send')->with([
                    'status'  => 'error',
                    'message' => 'Please select your sending server',
                ]);
            }

            $validateData = $this->campaigns->checkQuickSendValidation($sendData);

            if ($validateData->getData()->status == 'error') {
                return redirect()->route('customer.otp.quick_send')->with([
                    'status'  => 'error',
                    'message' => $validateData->getData()->message,
                ]);
            }

            $sendData['sender_id'] = $validateData->getData()->sender_id;
            $sendData['sms_type']  = $validateData->getData()->sms_type;
            $sendData['user']      = User::find($validateData->getData()->user_id);


            foreach ($recipients as $recipient) {

                $phone                    = $this->getPhoneNumber($recipient, $request->input('country_code'));
                $sendData['country_code'] = $phone['country_code'];
                $sendData['recipient']    = $phone['recipient'];

                $data = $this->campaigns->quickSend($campaign, $sendData);

                if ($data->getData()->status === 'error') {
                    $errors[] = $data->getData()->message;
                } else if ($data->getData()->status === 'success' || $data->getData()->status === 'info') {
                    $success[] = $data->getData()->message;
                }
            }

            if ( ! empty($errors)) {
                $errorMessage = implode(' ', $errors);

                return redirect()->route('customer.otp.quick_send')->with([
                    'status'  => 'error',
                    'message' => $errorMessage,
                ]);
            }

            $successMessage = implode(' ', $success);

            return redirect()->route('customer.reports.sent')->with([
                'status'  => 'info',
                'message' => $successMessage,
            ]);
        }

        /**
         * OTP campaign builder
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function otpCampaignBuilder(): View|Factory|RedirectResponse|Application
        {

            $this->authorize('otp_campaign_builder');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.OTP')],
                ['name' => __('locale.menu.Campaign Builder')],
            ];

            $compactData                = $this->getCampaignBuilderData();
            $compactData['breadcrumbs'] = $breadcrumbs;


            return view('customer.Campaigns.otpCampaignBuilder', $compactData);
        }


        /**
         * store campaign
         *
         *
         * @param Campaigns                 $campaign
         * @param OTPCampaignBuilderRequest $request
         *
         * @return RedirectResponse
         */
        public function storeOTPCampaign(Campaigns $campaign, OTPCampaignBuilderRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('customer.otp.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $customer           = Auth::user()->customer;
            $activeSubscription = $customer->activeSubscription();

            if ( ! $activeSubscription) {
                return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
                ]);
            }

            $plan = Plan::where('status', true)->find($activeSubscription->plan_id);

            if ( ! $plan) {
                return redirect()->route('customer.otp.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => 'Purchased plan is not active. Please contact support team.',
                ]);
            }

            $data = $this->campaigns->campaignBuilder($campaign, $request->except('_token'));

            if (isset($data->getData()->status)) {

                if ($data->getData()->status == 'success') {
                    return redirect()->route('customer.reports.campaigns')->with([
                        'status'  => 'success',
                        'message' => $data->getData()->message,
                    ]);
                }

                return redirect()->route('customer.otp.campaign_builder')->with([
                    'status'  => 'error',
                    'message' => $data->getData()->message,
                ]);
            }

            return redirect()->route('customer.otp.campaign_builder')->with([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);

        }


        /**
         * send an otp message using file
         *
         * @return Application|Factory|View|RedirectResponse
         * @throws AuthorizationException
         */
        public function otpImport(): View|Factory|RedirectResponse|Application
        {
            $this->authorize('otp_bulk_messages');

            $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.OTP')],
                ['name' => __('locale.menu.Send Using File')],
            ];

            $compactData                = $this->getCampaignBuilderData();
            $compactData['breadcrumbs'] = $breadcrumbs;

            return view('customer.Campaigns.otpImport', $compactData);
        }


        /**
         * send a message using file
         *
         * @param ImportRequest $request
         *
         * @return Application|Factory|View|RedirectResponse
         */
        public function importOTPCampaign(ImportRequest $request): View|Factory|RedirectResponse|Application
        {
            if ($request->file('import_file')->isValid()) {

                $breadcrumbs = [
                    ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                    ['link' => url('dashboard'), 'name' => __('locale.menu.OTP')],
                    ['name' => __('locale.menu.Send Using File')],
                ];

                $form_data = $request->except('_token', 'import_file');
                $file      = $request->file('import_file');
                $ref_id    = uniqid();
                $data      = Excel::toArray(new stdClass(), $request->file('import_file'))[0];

                if ( ! is_array($data) && count($data) > 0) {
                    return redirect()->route('customer.otp.import')->with([
                        'status'  => 'error',
                        'message' => __('locale.settings.invalid_file'),
                    ]);
                }

                $csv_data    = array_slice($data, 0, 2);
                $path        = 'app/bulk_sms/';
                $upload_path = storage_path($path);

                if ( ! file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                $filename = 'otp-' . $ref_id . '.' . $file->getClientOriginalExtension();

                // save to server
                $file->move($upload_path, $filename);

                $csv_data_file = CsvData::create([
                    'user_id'      => Auth::user()->id,
                    'ref_id'       => $ref_id,
                    'ref_type'     => CsvData::TYPE_CAMPAIGN,
                    'csv_filename' => $filename,
                    'csv_header'   => $request->has('header'),
                    'csv_data'     => $path . $filename,
                ]);


                return view('customer.Campaigns.import_fields', compact('csv_data', 'csv_data_file', 'breadcrumbs', 'form_data'));
            }

            return redirect()->route('customer.otp.import')->with([
                'status'  => 'error',
                'message' => __('locale.settings.invalid_file'),
            ]);
        }


    }
