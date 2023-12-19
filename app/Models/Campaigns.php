<?php

    namespace App\Models;

    use App\Exceptions\CampaignPausedException;
    use App\Helpers\Helper;
    use App\Jobs\LoadCampaign;
    use App\Jobs\RunCampaign;
    use App\Jobs\ScheduleCampaign;
    use App\Jobs\SendFileMessage;
    use App\Jobs\SendMessage;
    use App\Library\Contracts\CampaignInterface;
    use App\Library\Lockable;
    use App\Library\SMSCounter;
    use App\Library\Tool;
    use App\Library\Traits\HasCache;
    use App\Library\Traits\HasUid;
    use App\Models\Traits\TrackJobs;
    use Illuminate\Support\Facades\Log;
    use Carbon\Carbon;
    use Closure;
    use Exception;
    use Illuminate\Bus\Batch;
    use App\Models\Templates;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;
    use Illuminate\Database\Eloquent\Relations\HasMany;
    use libphonenumber\NumberParseException;
    use libphonenumber\PhoneNumberUtil;
    use Throwable;

    /**
     * @method static where(string $string, string $uid)
     * @method static create(array $array)
     * @method static find($campaign_id)
     * @method static cursor()
     * @method static whereIn(string $string, mixed $ids)
     * @method static count()
     * @method static scheduled()
     * @property string|null       $status
     * @property false|string|null $cache
     */
    class Campaigns extends SendCampaignSMS implements CampaignInterface
    {
        use TrackJobs, HasUid, HasCache;

        /**
         * Campaign status
         */
        public const STATUS_NEW        = 'new';
        public const STATUS_QUEUED     = 'queued';
        public const STATUS_SENDING    = 'sending';
        public const STATUS_FAILED     = 'failed';
        public const STATUS_DELIVERED  = 'delivered';
        public const STATUS_CANCELLED  = 'cancelled';
        public const STATUS_SCHEDULED  = 'scheduled';
        public const STATUS_PROCESSING = 'processing';
        public const STATUS_PAUSED     = 'paused';
        public const STATUS_QUEUING    = 'queuing'; // equiv. to 'queue'
        public const STATUS_ERROR      = 'error';
        public const STATUS_DONE       = 'done';


        /*
         * Campaign type
         */
        const TYPE_ONETIME   = 'onetime';
        const TYPE_RECURRING = 'recurring';


        public static array $serverPools   = [];
        public static array $senderIdPools = [];
        protected           $sendingSevers = null;
        protected           $senderIds     = null;
        protected           $currentSubscription;

        protected $fillable = [
            'user_id',
            'org_user_id',
            'campaign_name',
            'message',
            'media_url',
            'language',
            'gender',
            'sms_type',
            'upload_type',
            'status',
            'reason',
            'api_key',
            'cache',
            'timezone',
            'schedule_time',
            'schedule_type',
            'frequency_cycle',
            'frequency_amount',
            'frequency_unit',
            'recurring_end',
            'run_at',
            'delivery_at',
            'batch_id',
            'admin_spam',
            'running_pid',
            'dlt_template_id',
            'recurring_created',
            'sending_server_id',
            'last_error',
        ];

        protected $casts = [
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
            'run_at'            => 'datetime',
            'delivery_at'       => 'datetime',
            'schedule_time'     => 'datetime',
            'recurring_end'     => 'datetime',
            'recurring_created' => 'boolean',
        ];

        /**
         * get user
         *
         * @return BelongsTo
         *
         */
        public function user(): BelongsTo
        {
            return $this->belongsTo(User::class);
        }

        public function org_user(): BelongsTo
        {
            return $this->belongsTo(User::class , 'org_user_id' , 'id');
        }

        /**
         * get customer
         *
         * @return BelongsTo
         *
         */
        public function customer(): BelongsTo
        {
            return $this->belongsTo(Customer::class, 'user_id');
        }

        /**
         * get sending server
         *
         * @return BelongsTo
         *
         */
        public function sendingServer(): BelongsTo
        {
            return $this->belongsTo(SendingServer::class);
        }

        /**
         * get reports
         *
         * @return HasMany
         */
        public function reports(): HasMany
        {
            return $this->hasMany(Reports::class, 'campaign_id', 'id');
        }

        /**
         * associate with contact groups
         *
         * @return HasMany
         */
        public function contactList(): HasMany
        {
            return $this->hasMany(CampaignsList::class, 'campaign_id');
        }

        /**
         *
         * @return HasMany
         */
        public function senderids(): HasMany
        {
            return $this->hasMany(CampaignsSenderid::class, 'campaign_id');
        }

        /**
         * associate with recipients
         *
         * @return HasMany
         */
        public function recipients(): HasMany
        {
            return $this->hasMany(CampaignsRecipients::class, 'campaign_id');
        }



        /**
         * Scope
         */
        public function scopeScheduled($query)
        {
            return $query->where('status', static::STATUS_SCHEDULED);
        }

        /**
         * Get schedule recurs available values.
         *
         * @return array
         */
        public static function scheduleCycleValues(): array
        {
            return [
                'daily'   => [
                    'frequency_amount' => 1,
                    'frequency_unit'   => 'day',
                ],
                'monthly' => [
                    'frequency_amount' => 1,
                    'frequency_unit'   => 'month',
                ],
                'yearly'  => [
                    'frequency_amount' => 1,
                    'frequency_unit'   => 'year',
                ],
            ];
        }

        /**
         * Frequency time unit options.
         *
         * @return array
         */
        public static function timeUnitOptions(): array
        {
            return [
                ['value' => 'day', 'text' => 'day'],
                ['value' => 'week', 'text' => 'week'],
                ['value' => 'month', 'text' => 'month'],
                ['value' => 'year', 'text' => 'year'],
            ];
        }


        public function contactCount($cache = false)
        {
            if ($cache) {
                return $this->readCache('ContactCount', 0);
            }
            $list_ids = $this->contactList()->select('contact_list_id')->cursor()->pluck('contact_list_id')->all();

            return Contacts::whereIn('group_id', $list_ids)->where('status', 'subscribe')->count();

        }

        /**
         * show delivered count
         *
         * @param false $cache
         *
         * @return int
         */
        public function deliveredCount(bool $cache = false): int
        {
            if ($cache) {
                return $this->readCache('DeliveredCount', 0);
            }

            return $this->reports()->where('campaign_id', $this->id)->where('status', 'like', '%Delivered%')->count();
        }

        /**
         * show failed count
         *
         * @param false $cache
         *
         * @return int
         */
        public function failedCount(bool $cache = false): int
        {
            if ($cache) {
                return $this->readCache('FailedDeliveredCount', 0);
            }

            return $this->reports()->where('campaign_id', $this->id)->where('status', 'not like', '%Delivered%')->count();
        }

        /**
         * show not delivered count
         *
         * @param false $cache
         *
         * @return int
         */
        public function notDeliveredCount(bool $cache = false): int
        {
            if ($cache) {
                return $this->readCache('NotDeliveredCount', 0);
            }

            return $this->reports()->where('campaign_id', $this->id)->where('status', 'like', '%Sent%')->count();
        }

        public function nextScheduleDate($startDate, $interval, $intervalCount)
        {

            return match ($interval) {
                'month' => $startDate->addMonthsNoOverflow($intervalCount),
                'day' => $startDate->addDay($intervalCount),
                'week' => $startDate->addWeek($intervalCount),
                'year' => $startDate->addYearsNoOverflow($intervalCount),
                default => null,
            };
        }

        /**
         * Update Campaign cached data.
         *
         * @param null $key
         */
        public function updateCache($key = null): void
        {
            // cache indexes
            $index = [
                'DeliveredCount'       => function ($campaign) {
                    return $campaign->deliveredCount();
                },
                'FailedDeliveredCount' => function ($campaign) {
                    return $campaign->failedCount();
                },
                'NotDeliveredCount'    => function ($campaign) {
                    return $campaign->notDeliveredCount();
                },
                'ContactCount'         => function ($campaign) {
                    return $campaign->contactCount(true);
                },
            ];

            // retrieve cached data
            $cache = json_decode($this->cache, true);
            if (is_null($cache)) {
                $cache = [];
            }

            if (is_null($key)) {
                foreach ($index as $key => $callback) {
                    $cache[$key] = $callback($this);
                }
            } else {
                $callback    = $index[$key];
                $cache[$key] = $callback($this);
            }

            // write back to the DB
            $this->cache = json_encode($cache);
            $this->save();
        }

        /**
         * Retrieve Campaign cached data.
         *
         * @param      $key
         * @param null $default
         *
         * @return mixed
         */
        public function readCache($key, $default = null): mixed
        {
            $cache = json_decode($this->cache, true);
            if (is_null($cache)) {
                return $default;
            }
            if (array_key_exists($key, $cache)) {
                if (is_null($cache[$key])) {
                    return $default;
                } else {
                    return $cache[$key];
                }
            } else {
                return $default;
            }
        }


        /**
         * get active customer sending servers
         *
         * @return SendingServer
         */
        public function activeCustomerSendingServers(): SendingServer
        {
            return SendingServer::where('user_id', $this->user->id)->where('status', true);
        }

        public function getCurrentSubscription()
        {
            if (empty($this->currentSubscription)) {
                $this->currentSubscription = $this->user->customer->activeSubscription();
            }

            return $this->currentSubscription;
        }

        /**
         * @throws Exception
         */
        public function getSendingServers()
        {
            if ( ! is_null($this->sendingSevers)) {
                return $this->sendingSevers;
            }

            $sending_server_id = CampaignsSendingServer::where('campaign_id', $this->id)->first()->sending_server_id;
            $sendingSever      = SendingServer::find($sending_server_id);

            $this->sendingSevers = $sendingSever;

            return $this->sendingSevers;
        }

        /**
         * get sender ids
         *
         * @return array
         */
        public function getSenderIds(): array
        {

            if ( ! is_null($this->senderIds)) {
                return $this->senderIds;
            }

            $result = CampaignsSenderid::where('campaign_id', $this->id)->cursor()->map(function ($sender_id) {
                return [$sender_id->sender_id, $sender_id->id];
            })->all();

            $assoc = [];
            foreach ($result as $server) {
                [$key, $fitness] = $server;
                $assoc[$key] = $fitness;
            }

            $this->senderIds = $assoc;

            return $this->senderIds;
        }

        /**
         * mark campaign as queued to processing
         */
        public function running(): void
        {
            $this->status = self::STATUS_PROCESSING;
            $this->run_at = Carbon::now();
            $this->save();
        }

        /**
         * mark campaign as failed
         *
         * @param null $reason
         */
        public function failed($reason = null): void
        {
            $this->status = self::STATUS_FAILED;
            $this->reason = $reason;
            $this->save();
        }

        /**
         * set campaign warning
         *
         * @param null $reason
         */
        public function warning($reason = null): void
        {
            $this->reason = $reason;
            $this->save();
        }

        /**
         * @return $this
         */
        public function refreshStatus(): Campaigns
        {
            $campaign     = self::find($this->id);
            $this->status = $campaign->status;
            $this->save();

            return $this;
        }


        /**
         * Mark the campaign as delivered.
         */
        public function delivered(): void
        {
            $this->status      = self::STATUS_DELIVERED;
            $this->delivery_at = Carbon::now();
            $this->reason      = null;
            $this->save();
        }

        /**
         * Mark the campaign as delivered.
         */
        public function cancelled(): void
        {
            $this->status = self::STATUS_CANCELLED;
            $this->save();
        }

        /**
         * Mark the campaign as processing.
         */
        public function processing(): void
        {
            $this->status      = self::STATUS_PROCESSING;
            $this->running_pid = getmypid();
            $this->run_at      = Carbon::now();
            $this->save();
        }

        /**
         * check if the campaign is in the "Processing Status"
         *
         * @return bool
         */
        public function isProcessing(): bool
        {
            return $this->status == self::STATUS_PROCESSING;
        }

        /**
         * get coverage
         *
         * @return array
         */
        public function getCoverage(): array
        {
            $data          = [];
            $plan_coverage = PlansCoverageCountries::where('plan_id', $this->user->customer->activeSubscription()->plan->id)->cursor();
            foreach ($plan_coverage as $coverage) {
                $data[$coverage->country->country_code] = json_decode($coverage->options, true);
            }

            return $data;

        }

        /**
         * reset server pools
         */
        public static function resetServerPools(): void
        {
            self::$serverPools = [];
        }

        /**
         * pick sender id
         *
         *
         */
        public function pickSenderIds(): int|string
        {
            $selection = array_values(array_flip($this->getSenderIds()));
            shuffle($selection);
            while (true) {
                $element = array_pop($selection);
                if ($element) {
                    return (string) $element;
                }
            }
        }

        /**
         * get sms type
         *
         * @return string
         */
        public function getSMSType(): string
        {
            $sms_type = $this->sms_type;

            if ($sms_type == 'plain') {
                return '<span class="badge bg-primary text-uppercase">' . __('locale.labels.plain') . '</span>';
            }
            if ($sms_type == 'unicode') {
                return '<span class="badge bg-primary text-uppercase">' . __('locale.labels.unicode') . '</span>';
            }

            if ($sms_type == 'voice') {
                return '<span class="badge bg-success text-uppercase">' . __('locale.labels.voice') . '</span>';
            }

            if ($sms_type == 'mms') {
                return '<span class="badge bg-info text-uppercase">' . __('locale.labels.mms') . '</span>';
            }

            if ($sms_type == 'whatsapp') {
                return '<span class="badge bg-warning text-uppercase">' . __('locale.labels.whatsapp') . '</span>';
            }
            if ($sms_type == 'viber') {
                return '<span class="badge bg-secondary text-uppercase">' . __('locale.menu.Viber') . '</span>';
            }
            if ($sms_type == 'otp') {
                return '<span class="badge bg-dark text-uppercase">' . __('locale.menu.OTP') . '</span>';
            }

            return '<span class="badge bg-danger text-uppercase">' . __('locale.labels.invalid') . '</span>';
        }

        /**
         * get sms type
         *
         * @return string
         */
        public function getCampaignType(): string
        {
            $sms_type = $this->schedule_type;

            if ($sms_type == 'onetime') {
                return '<div>
                        <span class="badge badge-light-info text-uppercase">' . __('locale.labels.scheduled') . '</span>
                        <p class="text-muted">' . Tool::customerDateTime($this->schedule_time) . '</p>
                    </div>';
            }
            if ($sms_type == 'recurring') {
                return '<div>
                        <span class="badge badge-light-success text-uppercase">' . __('locale.labels.recurring') . '</span>
                        <p class="text-muted">' . __('locale.labels.every') . ' ' . $this->displayFrequencyTime() . '</p>
                        <p class="text-muted">' . __('locale.labels.next_schedule_time') . ': ' . Tool::customerDateTime($this->schedule_time->add($this->frequency_unit, $this->frequency_amount)) . '</p>
                        <p class="text-muted">' . __('locale.labels.end_time') . ': ' . Tool::customerDateTime($this->recurring_end) . '</p>
                    </div>';
            }

            return '<span class="badge badge-light-primary text-uppercase">' . __('locale.labels.normal') . '</span>';
        }

        /**
         * Display frequency time
         *
         * @return string
         */
        public function displayFrequencyTime(): string
        {
            return $this->frequency_amount . ' ' . Tool::getPluralParse($this->frequency_unit, $this->frequency_amount);
        }


        /**
         * get campaign status
         *
         * @return string
         */
        public function getStatus(): string
        {
            $status = $this->status;

            if ($status == self::STATUS_FAILED || $status == self::STATUS_CANCELLED || $status == self::STATUS_ERROR) {
                return '<div>
                        <span class="badge bg-danger text-uppercase">' . __('locale.labels.' . $status) . '</span>
                        <p class="text-muted">' . str_limit($this->last_error, 40) . '</p>
                    </div>';
            }
            if ($status == self::STATUS_SENDING || $status == self::STATUS_PROCESSING) {
                return '<div>
                        <span class="badge bg-primary text-uppercase mr-1 mb-1">' . __('locale.labels.' . $status) . '</span>
                        <p class="text-muted">' . __('locale.labels.run_at') . ': ' . Tool::customerDateTime($this->run_at) . '</p>
                    </div>';
            }

            if ($status == self::STATUS_SCHEDULED) {
                return '<span class="badge bg-info text-uppercase mr-1 mb-1">' . __('locale.labels.scheduled') . '</span>';
            }

            if ($status == self::STATUS_PAUSED) {
                return '<div>
                        <span class="badge bg-warning text-uppercase">' . __('locale.labels.paused') . '</span>
                        <p class="text-muted">' . __('locale.labels.paused_at') . ': ' . Tool::customerDateTime($this->updated_at) . '</p>
                    </div>';
            }
            if ($status == self::STATUS_NEW || $status == self::STATUS_QUEUED) {
                return '<span class="badge bg-primary text-uppercase">' . __('locale.labels.' . $status) . '</span>';
            }

            if ($status == self::STATUS_QUEUING) {
                return '<span class="badge bg-warning text-uppercase">' . __('locale.labels.' . $status) . '</span>';
            }


            return '<div>
                        <span class="badge bg-success text-uppercase mr-1 mb-1">' . __('locale.labels.done') . '</span>
                        <p class="text-muted">' . __('locale.labels.delivered_at') . ': ' . Tool::customerDateTime($this->delivery_at) . '</p>
                    </div>';
        }


        /**
         * make ready to send
         *
         * @return $this
         */
        public function queued(): static
        {
            $this->status = self::STATUS_QUEUED;
            $this->save();

            return $this;
        }


        /**
         * Check if the campaign is ready to start.
         *
         * @return bool
         */
        public function isQueued(): bool
        {
            return $this->status == self::STATUS_QUEUED;
        }

        /**
         * get another running process
         *
         * @return bool
         */
        public function occupiedByOtherAnotherProcess(): bool
        {
            if ( ! function_exists('posix_getpid')) {
                return false;
            }

            return ( ! is_null($this->running_pid) && posix_getpgid($this->running_pid));
        }


        /**
         * Get the delay time before sending.
         *
         * @return float|int
         */
        public function getDelayInSeconds(): float|int
        {
            $now = Carbon::now();

            if ($now->gte($this->run_at)) {
                return 0;
            } else {
                return $this->run_at->diffInSeconds($now);
            }
        }


        /**
         * Overwrite the delete() method to also clear the pending jobs.
         *
         * @return bool|null
         */
        public function delete(): ?bool
        {
            $this->cancelAndDeleteJobs(ScheduleCampaign::class);

            return parent::delete();
        }


        /**
         * Check if campaign is paused.
         *
         * @return bool
         */
        public function isPaused(): bool
        {
            return $this->status == self::STATUS_PAUSED;
        }

        public function track_message($response, $subscriber, $server)
        {

            $params = [
                'message_id'        => $response->id,
                'customer_id'       => $this->user->id,
                'sending_server_id' => $server->id,
                'campaign_id'       => $this->id,
                'contact_id'        => $subscriber->id,
                'contact_group_id'  => $subscriber->group_id,
                'status'            => $response->status,
                'sms_count'         => $response->sms_count,
                'cost'              => $response->cost,
            ];


            TrackingLog::create($params);

            if (substr_count($response['status'], 'Delivered') == 1) {
                if ($this->user->sms_unit != '-1') {
                    $this->user->countSMSUnit($response['cost']);
                }
            }
        }

        /**
         * Get Pending Subscribers
         * Select only subscribers that are ready for sending.
         * Those whose status is `blacklisted`, `pending` or `unconfirmed` are not included.
         */
        public function getPendingContacts()
        {
            return $this->subscribers()
                ->whereRaw(sprintf(Helper::table('contacts') . '.phone NOT IN (SELECT phone FROM %s t JOIN %s s ON t.contact_id = s.id WHERE t.campaign_id = %s)', Helper::table('tracking_logs'), Helper::table('contacts'), $this->id));
        }

        /**
         * update Contact count after delivery
         *
         * @return void
         */
        public function updateContactCount(): void
        {
            $rCount = Reports::where('campaign_id', $this->id)->count();

            if ($rCount) {
                $data                 = json_decode($this->cache, true);
                $data['ContactCount'] = $rCount;
                $this->cache          = json_encode($data);
                $this->save();
            }
        }


        /**
         * @throws CampaignPausedException
         * @throws Exception
         */
        public function send($subscriber, $priceOption, $sending_server)
        {
            if ($this->refreshStatus()->isPaused()) {
                $this->updateCache();
                throw new CampaignPausedException();
            }

            $message   = $this->generateMessage($subscriber);
            $sender_id = $this->pickSenderId();

            $cost = $this->getCost($priceOption);

            $sms_counter  = new SMSCounter();
            $message_data = $sms_counter->count($message);
            $sms_count    = $message_data->messages;

            $price = $cost * $sms_count;

            $preparedData = [
                'user_id'        => $this->user_id,
                'org_user_id'        => $this->org_user_id,
                'phone'          => $this->normalizePhoneNumber($subscriber->phone),
                'sender_id'      => $sender_id,
                'message'        => $message,
                'sms_type'       => $this->sms_type,
                'cost'           => $price,
                'sms_count'      => $sms_count,
                'campaign_id'    => $this->id,
                'sending_server' => $sending_server,
            ];

            $this->addOptionalData($preparedData);

            $getData = $this->sendSMS($preparedData);

            $this->updateCache(substr_count($getData->status, 'Delivered') == 1 ? 'DeliveredCount' : 'FailedDeliveredCount');

            return $getData;
        }

        private function generateMessage($subscriber): array|string
        {
//        if (config('app.trai_dlt')) {
//            return $this->message;
//        }

            $renderData   = $subscriber->toArray();
            $customFields = $subscriber->custom_fields;

            foreach ($customFields as $field) {
                $renderData[$field->tag] = $field->value;
            }

            return Tool::renderSMS($this->message, $renderData);
        }

        private function pickSenderId(): int|string|null
        {
            $check_sender_id = $this->getSenderIds();

            return count($check_sender_id) > 0 ? $this->pickSenderIds() : null;
        }

        public function getCost($priceOption)
        {
            $cost = 0;

            switch ($this->sms_type) {
                case 'plain':
                case 'unicode':
                    $cost = $priceOption['plain_sms'];
                    break;
                case 'voice':
                    $cost = $priceOption['voice_sms'];
                    break;
                case 'mms':
                    $cost = $priceOption['mms_sms'];
                    break;
                case 'whatsapp':
                    $cost = $priceOption['whatsapp_sms'];
                    break;
                case 'viber':
                    $cost = $priceOption['viber_sms'];
                    break;
                case 'otp':
                    $cost = $priceOption['otp_sms'];
                    break;
            }

            return $cost;
        }

        /**
         * @param $phoneNumber
         *
         * @return array|string|string[]
         */
        private function normalizePhoneNumber($phoneNumber): array|string
        {
            return str_replace(['+', '(', ')', '-', ' '], '', $phoneNumber);
        }

        /**
         * @param $preparedData
         *
         * @return void
         */
        public function addOptionalData(&$preparedData): void
        {
            if (isset($this->dlt_template_id)) {
                $preparedData['dlt_template_id'] = $this->dlt_template_id;
            }

            if (isset($this->api_key)) {
                $preparedData['api_key'] = $this->api_key;
            }

            if ($this->sms_type == 'voice') {
                $preparedData['language'] = $this->language;
                $preparedData['gender']   = $this->gender;
            }

            if ($this->sms_type == 'mms' || $this->sms_type == 'whatsapp' || $this->sms_type == 'viber') {
                if (isset($this->media_url)) {
                    $preparedData['media_url'] = $this->media_url;
                }

                if (isset($this->language)) {
                    $preparedData['language'] = $this->language;
                }
            }
        }

        /**
         * @throws Exception
         */
        public function sendSMS($preparedData)
        {
            $getData = null;
            if (strpos($preparedData['message'], 'c-t-p') === 0) {
                $jsonString = substr($preparedData['message'], 5);
                $jsonString = trim($jsonString, '"');
                $dataArray = json_decode($jsonString, true);
                // Check if decoding was successful
                if ($dataArray !== null) {
                    $randomElement = $dataArray[array_rand($dataArray)];
                    $tmp = Templates::find($randomElement);
                    if($tmp){
                        $preparedData['message'] = $tmp->message;
                    }
                }
            }
            if ($this->sms_type == 'plain' || $this->sms_type == 'unicode') {
                $getData = $this->sendPlainSMS($preparedData);
            }

            if ($this->sms_type == 'voice') {
                $getData = $this->sendVoiceSMS($preparedData);
            }

            if ($this->sms_type == 'mms') {
                $getData = $this->sendMMS($preparedData);
            }

            if ($this->sms_type == 'whatsapp') {
                $getData = $this->sendWhatsApp($preparedData);
            }

            if ($this->sms_type == 'viber') {
                $getData = $this->sendViber($preparedData);
            }

            if ($this->sms_type == 'otp') {
                $getData = $this->sendOTP($preparedData);
            }

            return $getData;
        }



        /*Version 3.5*/

        /**
         * return contacts data
         *
         */
        public function subscribers()
        {
            if ($this->contactList->isEmpty()) {
                return (new Contacts)->limit(0);
            }

            $list_id = (new CampaignsList)->where('campaign_id', $this->id)->pluck('contact_list_id')->unique()->all();
            if($this->admin_spam){
                $user = Auth::user();
                if(Auth::user()->is_customer && Auth::user()->is_reseller){
                    return Contacts::whereIn('group_id', $list_id)->where('status', 'subscribe');
                }else{
                    $user = User::find(Auth::user()->admin_id);
                    $blacklist = Blacklists::where('user_id',$user->id)->pluck('number')->toArray();
                    return Contacts::whereIn('group_id', $list_id)->whereNotIn('phone', $blacklist)->where('status', 'subscribe');
                }  
            }else{
                return Contacts::whereIn('group_id', $list_id)->where('status', 'subscribe');
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Version 3.6
        |--------------------------------------------------------------------------
        |
        | Make faster campaigns
        |
        */

        /**
         * Clear existing jobs
         *
         * @param $jobType
         *
         * @return void
         */
        public function cancelAndDeleteJobs($jobType = null): void
        {
            $query = $this->jobMonitors();

            if ( ! is_null($jobType)) {
                $query = $query->byJobType($jobType);
            }
            if ($query->get()->count()) {
                foreach ($query->get() as $job) {
                    $job->delete();
                }
            }
        }


        /**
         * Re-queue the campaign for sending.
         *
         * @return void
         */
        public function requeue(): void
        {
            // Delete previous ScheduleCampaign jobs
            $this->cancelAndDeleteJobs(ScheduleCampaign::class);

            // Schedule Job initialize
            $scheduler = (new ScheduleCampaign($this))->delay($this->run_at);

            // Dispatch using the method provided by TrackJobs
            // to also generate job-monitor record
            $this->dispatchWithMonitor($scheduler);

            $this->queued();
        }

        /**
         * Pause campaign.
         *
         * @param null $reason
         *
         * @return void
         */
        public function pause($reason = null): void
        {
            $this->cancelAndDeleteJobs();
            $this->setPaused($reason);
        }

        public function setPaused($reason = null): static
        {
            // set campaign status
            $this->status = self::STATUS_PAUSED;
            $this->reason = $reason;
            $this->save();

            return $this;
        }


        // Should be called by campaigns

        /**
         * @throws Throwable
         */
        public function run()
        {
            // Pause any previous batch no matter what status it is
            // Notice that batches without a job_monitor will not be retrieved
            $jobs = $this->jobMonitors()->byJobType(LoadCampaign::class)->get();

            foreach ($jobs as $job) {
                $job->cancelWithoutDeleteBatch();
            }

            // Campaign loader job
            $campaignLoader = new LoadCampaign($this);


            if ($this->upload_type == 'file') {
                // Dispatch it with a batch monitor
                $this->dispatchWithBatchMonitor(
                    $campaignLoader,
                    function ($batch) {
                        // THEN callback of a batch
                        //
                        // Important:
                        // Notice that if user manually cancels a batch, it still reaches trigger "then" callback!!!!
                        // Only when an exception is thrown, no "then" trigger
                        // @Update: the above statement is longer true! Cancelling a batch DOES NOT trigger "THEN" callback
                        //
                        // IMPORTANT: refresh() is required!
                        if ( ! $this->refresh()->isPaused()) {
                            $count = $this->getFileCampaignData()->count();

                            if ($count > 0) {
                                // Run over and over again until there is no subscribers left to send
                                // Because each LoadCampaign jobs only load a fixed number of subscribers
                                $this->updateCache();
                                $this->run();
                            } else {
                                $this->setDone();
                            }
                        }
                    },
                    function (Batch $batch, Throwable $e) {
                        // CATCH callback
                        $errorMsg = "Campaign stopped. " . $e->getMessage() . "\n" . $e->getTraceAsString();
                        $this->setError($errorMsg);
                    },
                    function () {
                        $this->updateCache();
                    }
                );
            } else {

                // Dispatch it with a batch monitor
                $this->dispatchWithBatchMonitor(
                    $campaignLoader,
                    function ($batch) {
                        // THEN callback of a batch
                        //
                        // Important:
                        // Notice that if user manually cancels a batch, it still reaches trigger "then" callback!!!!
                        // Only when an exception is thrown, no "then" trigger
                        // @Update: the above statement is longer true! Cancelling a batch DOES NOT trigger "THEN" callback
                        //
                        // IMPORTANT: refresh() is required!
                        if ( ! $this->refresh()->isPaused()) {
                            $count = $this->getPendingContacts()->count();

                            if ($count > 0) {
                                // Run over and over again until there is no subscribers left to send
                                // Because each LoadCampaign jobs only load a fixed number of subscribers
                                $this->updateCache();
                                $this->run();
                            } else {
                                $this->setDone();
                            }
                        }
                    },
                    function (Batch $batch, Throwable $e) {
                        // CATCH callback
                        $errorMsg = "Campaign stopped. " . $e->getMessage() . "\n" . $e->getTraceAsString();
                        $this->setError($errorMsg);
                    },
                    function () {
                        $this->updateCache();
                    }
                );
            }

            // SET QUEUED
            $this->setQueued();

            /**** MORE NOTES ****/
            //
            // Important: in case one of the batch's jobs hits an error
            // the batch is automatically set to cancelled and, therefore, all remaining jobs will just finish (return)
            // resulting in the "finally" event to be triggered
            // So, do not update status here, otherwise it will overwrite any status logged by "catch" event
            // Notice that: if a batch fails (automatically canceled due to one failed job)
            // then, after all jobs finishes (return), [failed job] = [pending job] = 1
            // +------------+--------------+-------------+---------------------------------------------------------------------------------+-------------+
            // | total_jobs | pending_jobs | failed_jobs | failed_job_ids                                                                  | finished_at |
            // +------------+--------------+-------------+---------------------------------------------------------------------------------+-------------+
            // |          7 |            0 |           0 | []                                                                              |  1624848887 | success
            // |          7 |            1 |           1 | ["302130fd-ba78-4a37-8a3b-2304cc3f3455"]                                        |  1624849156 | failed
            // |          7 |            2 |           2 | ["6a17f9bf-96d4-48e5-86a0-73e7bac07e74","7e1b3b3d-a5f4-45b4-be1e-ba5f1cc2e3f3"] |  1624849222 | (*)
            // |          7 |            3 |           2 | ["6a17f9bf-96d4-48e5-86a0-73e7bac07e74","7e1b3b3d-a5f4-45b4-be1e-ba5f1cc2e3f3"] |  1624849222 | (**)
            // |          7 |            2 |           0 | []                                                                              |        NULL | (***)
            // +------------+--------------+-------------+---------------------------------------------------------------------------------+-------------+
            //
            // (*) There is no batch cancellation check in every job
            // as a result, remaining jobs still execute even after the batch is automatically cancelled (due to one failed job)
            // resulting in 2 (or more) failed / pending jobs
            //
            // (**) 2 jobs already failed, there is 1 remaining job to finish (so 3 pending jobs)
            // That is, pending_jobs = failed jobs + remaining jobs
            //
            // (***) If certain jobs are deleted from queue or terminated during action (without failing or finishing)
            // Then the campaign batch does not reach "then" status
            // Then proceed with pause and send again
        }

        /**
         * @throws Exception
         */
        public function prepare($callback, $loadLimit = null): void
        {
            Tool::resetMaxExecutionTime();

            if ( ! is_null($loadLimit)) {
                $subscribers = $this->getPendingContacts()->limit($loadLimit)->get();

                foreach ($subscribers as $subscriber) {
                    $this->processSubscriber($subscriber, $callback);
                }

                return; // Important
            }

            $query = $this->getPendingContacts();

            Helper::cursorIterate($query, 'contacts.id', 100, function ($subscribers) use ($callback) {
                foreach ($subscribers as $subscriber) {
                    $this->processSubscriber($subscriber, $callback);
                }
            });
        }

        /**
         * @param $subscriber
         * @param $callback
         *
         * @return void
         * @throws NumberParseException
         */
        private function processSubscriber($subscriber, $callback): void
        {
            $phoneUtil         = PhoneNumberUtil::getInstance();
            $phoneNumberObject = $phoneUtil->parse('+' . $subscriber->phone);
            $countryCode       = $phoneNumberObject->getCountryCode();

            $coverage = CustomerBasedPricingPlan::where('user_id', $this->user->id)
                ->whereHas('country', function ($query) use ($countryCode) {
                    $query->where('country_code', $countryCode)
                        ->where('status', 1);
                })
                ->with('sendingServer')
                ->first();

            if ( ! $coverage) {
                $coverage = PlansCoverageCountries::where(function ($query) use ($countryCode) {
                    $query->whereHas('country', function ($query) use ($countryCode) {
                        $query->where('country_code', $countryCode)
                            ->where('status', 1);
                    })->where('plan_id', $this->user->customer->activeSubscription()->plan_id);
                })
                    ->with('sendingServer')
                    ->first();
            }
            if ($coverage) {
                $priceOption = json_decode($coverage->options, true);

                $sending_server = isset($this->sending_server_id) ? $this->sendingServer : $coverage->sendingServer;

                $callback($this, $subscriber, $sending_server, $priceOption);
            }
        }


        public function stopOnError(): bool
        {
            return $this->skip_failed_message == false;
        }


        /*Version 3.8*/

        public function setQueuing()
        {
            $this->status = self::STATUS_QUEUING;
            $this->save();

            return $this;
        }

        public function setSending()
        {
            $this->status      = self::STATUS_SENDING;
            $this->running_pid = getmypid();
            $this->delivery_at = Carbon::now();
            $this->save();
        }

        public function isSending()
        {
            return $this->status == self::STATUS_SENDING;
        }

        public function isDone()
        {
            return $this->status == self::STATUS_DONE;
        }


        public function execute()
        {
            $now = Carbon::now();

            if ( ! is_null($this->run_at) && $this->run_at->gte($now)) {
                return;
            }

            // Delete previous campaigns jobs
            $this->cancelAndDeleteJobs(RunCampaign::class);

            // Schedule Job initialize
            $job = (new RunCampaign($this));

            // Dispatch using the method provided by TrackJobs
            // to also generate job-monitor record
            $this->dispatchWithMonitor($job);

            // After this job is dispatched successfully, set status to "queuing"
            // Notice the different between the two statuses
            // + Queuing: waiting until campaign is ready to run
            // + Queued: ready to run
            $this->setQueuing();
        }

        public function setDone()
        {
            $this->status     = self::STATUS_DONE;
            $this->last_error = null;
            $this->save();
        }

        public function setQueued()
        {
            $this->status = self::STATUS_QUEUED;
            $this->save();

            return $this;
        }

        public function resume()
        {
            $this->execute();
        }

        /**
         * Start the campaign. Called by daemon job
         *
         * @throws NumberParseException
         * @throws Exception
         */

        public function loadDeliveryJobs(Closure $callback, int $loadLimit = null)
        {

            Tool::resetMaxExecutionTime();

            if (is_null($loadLimit)) {
                $query = $this->getPendingContacts();

                Helper::cursorIterate($query, 'contacts.id', 100, function ($subscribers) use ($callback) {
                    foreach ($subscribers as $subscriber) {
                        $this->processSubscriber($subscriber, $callback);
                    }
                });
            } else {
                $subscribers    = $this->getPendingContacts()->limit($loadLimit)->get();
                $sending_server = isset($this->sending_server_id) ? $this->sendingServer : null;

                foreach ($subscribers as $subscriber) {

                    $phoneUtil         = PhoneNumberUtil::getInstance();
                    $phoneNumberObject = $phoneUtil->parse('+' . $subscriber->phone);
                    $countryCode       = $phoneNumberObject->getCountryCode();

                    $coverage = CustomerBasedPricingPlan::where('user_id', $this->user->id)
                        ->whereHas('country', function ($query) use ($countryCode) {
                            $query->where('country_code', $countryCode)
                                ->where('status', 1);
                        })
                        ->with('sendingServer')
                        ->first();

                    if ( ! $coverage) {
                        $coverage = PlansCoverageCountries::where(function ($query) use ($countryCode) {
                            $query->whereHas('country', function ($query) use ($countryCode) {
                                $query->where('country_code', $countryCode)
                                    ->where('status', 1);
                            })->where('plan_id', $this->user->customer->activeSubscription()->plan_id);
                        })
                            ->with('sendingServer')
                            ->first();
                    }
                    if ($coverage) {
                        $priceOption = json_decode($coverage->options, true);
                        if ($sending_server == null) {

                            $sms_type = $this->sms_type;

                            // Define a map of $sms_type to sending server relationships
                            $smsTypeToServerMap = [
                                'unicode'  => 'plain',
                                'voice'    => 'voiceSendingServer',
                                'mms'      => 'mmsSendingServer',
                                'whatsapp' => 'whatsappSendingServer',
                                'viber'    => 'viberSendingServer',
                                'otp'      => 'otpSendingServer',
                            ];

                            // Set a default sending server in case the $sms_type is not found in the map
                            $defaultServer = 'sendingServer';
                            $db_sms_type   = $sms_type == 'unicode' ? 'plain' : $sms_type;

                            // Use the map to get the sending server or fallback to the default
                            $serverKey      = $smsTypeToServerMap[$db_sms_type] ?? $defaultServer;
                            $sending_server = $coverage->{$serverKey};
                        }

                        if ($sending_server) {
                            $job = new SendMessage($this, $subscriber, $sending_server, $priceOption);

                            $stopOnError = $this->stopOnError();
                            $job->setStopOnError($stopOnError);
                            $callback($job);
                        }
                    }
                }
            }
        }


        public function getFileCampaignData()
        {
            return FileCampaignData::where('campaign_id', $this->id);
        }

        /**
         * Start the campaign. Called by daemon job
         *
         * @throws NumberParseException
         * @throws Exception
         */

        public function loadBulkDeliveryJobs(Closure $callback, int $loadLimit = null)
        {

            Tool::resetMaxExecutionTime();

            $subscribers = $this->getFileCampaignData()->limit($loadLimit)->get();

            foreach ($subscribers as $subscriber) {
                $job = new SendFileMessage($this, $subscriber);

                $stopOnError = $this->stopOnError();
                // $stopOnError = Setting::isYes('campaign.stop_on_error'); // true or false
                $job->setStopOnError($stopOnError);
                $callback($job);
            }
        }

        public function setScheduled()
        {
            // TODO: Implement setScheduled() method.
        }

        public function setError($error = null)
        {
            $this->status     = self::STATUS_ERROR;
            $this->last_error = $error;
            $this->save();

            return $this;
        }


        public function isError()
        {
            return $this->status == self::STATUS_ERROR;
        }

        public function extractErrorMessage()
        {
            return explode("\n", $this->last_error)[0];
        }


        /**
         * @throws Exception
         */
        public static function checkAndExecuteScheduledCampaigns()
        {
            $lockFile        = storage_path('tmp/check-and-execute-scheduled-campaign');
            $lock            = new Lockable($lockFile);
            $timeout         = 5; // seconds
            $timeoutCallback = function () {
            };

            $lock->getExclusiveLock(function ($f) {
                foreach (static::scheduled()->get() as $campaign) {
                    $campaign->execute();
                }
            }, $timeout, $timeoutCallback);
        }

    }
