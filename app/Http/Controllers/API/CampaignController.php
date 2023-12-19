<?php

    namespace App\Http\Controllers\API;

    use App\Http\Controllers\Controller;
    use App\Http\Requests\Campaigns\SendAPICampaign;
    use App\Http\Requests\Campaigns\SendAPISMS;
    use App\Library\SMSCounter;
    use App\Models\Campaigns;
    use App\Models\CustomerBasedSendingServer;
    use App\Models\Reports;
    use App\Models\Traits\ApiResponser;
    use App\Repositories\Contracts\CampaignRepository;
    use Carbon\Carbon;
    use Illuminate\Http\JsonResponse;
    use libphonenumber\NumberParseException;
    use libphonenumber\PhoneNumberUtil;

    class CampaignController extends Controller
    {
        use ApiResponser;

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
         * sms sending
         *
         * @param Campaigns  $campaign
         * @param SendAPISMS $request
         * @param Carbon     $carbon
         *
         * @return JsonResponse
         */
        public function smsSend(Campaigns $campaign, SendAPISMS $request, Carbon $carbon): JsonResponse
        {
            if (config('app.stage') == 'demo') {
                return $this->error('Sorry! This option is not available in demo mode');
            }

            $user = request()->user();
            if ( ! $user) {
                return $this->error(__('locale.auth.user_not_exist'));
            }

            $sms_type     = $request->get('type', 'plain');
            $sms_counter  = new SMSCounter();
            $message_data = $sms_counter->count($request->input('message'));

            if ($message_data->encoding == 'UTF16') {
                $sms_type = 'unicode';
            }

            if ( ! in_array($sms_type, ['plain', 'unicode', 'voice', 'mms', 'whatsapp', 'viber', 'otp'])) {
                return $this->error(__('locale.exceptions.invalid_sms_type'));
            }

            if ($sms_type == 'voice' && ( ! $request->filled('gender') || ! $request->filled('language'))) {
                return $this->error('Language and gender parameters are required');
            }

            if ($sms_type == 'mms' && ! $request->filled('media_url')) {
                return $this->error('media_url parameter is required');
            }

            if ($sms_type == 'mms' && filter_var($request->input('media_url'), FILTER_VALIDATE_URL) === false) {
                return $this->error('Valid media url is required.');
            }


            try {

                $input          = $this->prepareInput($request, $user, $sms_type, $carbon);
                $sendingServers = CustomerBasedSendingServer::where('user_id', $user->id)->where('status', 1)->count();

                if ($sendingServers > 0 && isset($user->api_sending_server)) {
                    $input['sending_server'] = $user->api_sending_server;
                }

                $isBulkSms = substr_count($input['recipient'], ',') > 0;

                $data = $isBulkSms
                    ? $this->campaigns->sendApi($campaign, $input)
                    : $this->processSingleRecipient($campaign, $input);

                $status = optional($data->getData())->status;

                return $status === 'success'
                    ? $this->success($data->getData()->data ?? null, $data->getData()->message)
                    : $this->error($data->getData()->message ?? __('locale.exceptions.something_went_wrong'), 403);

            } catch (NumberParseException $exception) {
                return $this->error($exception->getMessage(), 403);
            }
        }

        /**
         * @param $campaign
         * @param $input
         *
         * @return JsonResponse|mixed
         * @throws NumberParseException
         */
        private function processSingleRecipient($campaign, &$input)
        {

            $phone             = str_replace(['+', '(', ')', '-', ' '], '', $input['recipient']);
            $phone             = ltrim($phone, '0');
            $phoneUtil         = PhoneNumberUtil::getInstance();
            $phoneNumberObject = $phoneUtil->parse('+' . $phone);
            if ( ! $phoneUtil->isPossibleNumber($phoneNumberObject)) {
                return $this->error(__('locale.customer.invalid_phone_number', ['phone' => $phone]));
            }

            if ($phoneNumberObject->isItalianLeadingZero()) {
                $input['recipient'] = '0' . $phoneNumberObject->getNationalNumber();
            } else {
                $input['recipient'] = $phoneNumberObject->getNationalNumber();
            }

            $input['country_code'] = $phoneNumberObject->getCountryCode();


            return $this->campaigns->quickSend($campaign, $input);
        }

        /**
         * @param $request
         * @param $user
         * @param $sms_type
         * @param $carbon
         *
         * @return array
         */

        private function prepareInput($request, $user, $sms_type, $carbon)
        {
            $input = [
                'sender_id' => $request->input('sender_id'),
                'sms_type'  => $sms_type,
                'api_key'   => $user->api_token,
                'user'      => $user,
                'recipient' => $request->input('recipient'),
                'delimiter' => ',',
                'message'   => $request->input('message'),
            ];

            switch ($sms_type) {
                case 'voice':
                    $input['language'] = $request->input('language');
                    $input['gender']   = $request->input('gender');
                    break;
                case 'mms':
                case 'whatsapp':
                case 'viber':
                    if ($request->filled('media_url')) {
                        $input['media_url'] = $request->input('media_url');
                    }
                    break;
            }


            if ($request->filled('schedule_time')) {
                $input['schedule']        = true;
                $input['schedule_date']   = $carbon->parse($request->input('schedule_time'))->toDateString();
                $input['schedule_time']   = $carbon->parse($request->input('schedule_time'))->setSeconds(0)->format('H:i');
                $input['timezone']        = $user->timezone;
                $input['frequency_cycle'] = 'onetime';
            }

            return $input;
        }


        /**
         * view single sms reports
         *
         * @param Reports $uid
         *
         * @return JsonResponse
         */
        public function viewSMS(Reports $uid): JsonResponse
        {

            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            if (request()->user()->tokenCan('view_reports')) {
                $reports = Reports::select('uid', 'to', 'from', 'message', 'status', 'cost')->find($uid->id);
                if ($reports) {
                    return $this->success($reports);
                }

                return $this->error('SMS Info not found');
            }

            return $this->error(__('locale.http.403.description'), 403);
        }


        /**
         * get all messages
         *
         * @return JsonResponse
         */
        public function viewAllSMS(): JsonResponse
        {

            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            if (request()->user()->tokenCan('view_reports')) {
                $reports = Reports::select('uid', 'to', 'from', 'message', 'status', 'cost')->orderBy('created_at', 'desc')->paginate(25);
                if ($reports) {
                    return $this->success($reports);
                }

                return $this->error('SMS Info not found');
            }

            return $this->error(__('locale.http.403.description'), 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Version 3.7
        |--------------------------------------------------------------------------
        |
        | Send Campaign Using API
        |
        */

        public function campaign(Campaigns $campaign, SendAPICampaign $request)
        {

            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $input = $request->all();
            $user  = request()->user();

            if ( ! $user) {
                return $this->error(__('locale.auth.user_not_exist'));
            }

            $sendingServers = CustomerBasedSendingServer::where('user_id', $user->id)->where('status', 1)->count();

            if ($sendingServers > 0 && isset($user->api_sending_server)) {
                $input['sending_server'] = $user->api_sending_server;
            }

            $sms_type     = $request->get('type', 'plain');
            $sms_counter  = new SMSCounter();
            $message_data = $sms_counter->count($request->input('message'));

            if ($message_data->encoding == 'UTF16') {
                $sms_type = 'unicode';
            }

            if ( ! in_array($sms_type, ['plain', 'unicode', 'voice', 'mms', 'whatsapp', 'viber', 'otp'])) {
                return $this->error(__('locale.exceptions.invalid_sms_type'));
            }

            if ($sms_type == 'voice' && ( ! $request->filled('gender') || ! $request->filled('language'))) {
                return $this->error('Language and gender parameters are required');
            }

            if ($sms_type == 'mms' && ! $request->filled('media_url')) {
                return $this->error('media_url parameter is required');
            }

            if ($sms_type == 'mms' && filter_var($request->input('media_url'), FILTER_VALIDATE_URL) === false) {
                return $this->error('Valid media url is required.');
            }
            $input['api_key']  = $user->api_token;
            $input['timezone'] = $user->timezone;
            $input['name']     = 'API_' . time();

            unset($input['sender_id']);

            if ($request->get('sender_id') !== null) {
                if (is_numeric($request->get('sender_id'))) {
                    $input['originator']   = 'phone_number';
                    $input['phone_number'] = [$request->get('sender_id')];
                } else {
                    $input['sender_id']  = [$request->get('sender_id')];
                    $input['originator'] = 'sender_id';
                }
            }


            if ($request->input('schedule_time') !== null) {
                $input['schedule']        = true;
                $input['schedule_date']   = Carbon::parse($request->input('schedule_time'))->toDateString();
                $input['schedule_time']   = Carbon::parse($request->input('schedule_time'))->setSeconds(0)->format('H:i');
                $input['frequency_cycle'] = 'onetime';
            }

            $data = $this->campaigns->apiCampaignBuilder($campaign, $input);

            if (isset($data->getData()->status)) {

                if ($data->getData()->status == 'success') {
                    return $this->success($data->getData()->data, $data->getData()->message);
                }

                return $this->error($data->getData()->message);

            }

            return $this->error(__('locale.exceptions.something_went_wrong'));

        }

    }
