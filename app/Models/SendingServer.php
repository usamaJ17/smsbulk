<?php

    namespace App\Models;

    use App\Library\QuotaManager;
    use App\Library\Tool;
    use App\Library\Traits\HasUid;
    use App\Models\Traits\HasQuota;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;
    use Illuminate\Database\Eloquent\Relations\BelongsToMany;
    use App\Library\Contracts\HasQuota as HasQuotaInterface;

    /**
     * @method static status(bool $true)
     * @method static where(string $string, string $uid)
     * @method static cursor()
     * @method static whereIn(string $string, array $sending_servers_ids)
     * @method static find(int|mixed|string $id)
     * @method static create(array $server)
     * @method static limit(mixed $limit)
     * @method static whereLike(string[] $array, mixed $search)
     * @method static count()
     * @property mixed quota_value
     * @property mixed quota_base
     * @property mixed quota_unit
     * @property mixed quotaTracker
     * @property mixed created_at
     * @property mixed uid
     * @property mixed name
     */
    class SendingServer extends Model implements HasQuotaInterface
    {

        use HasUid, HasQuota;

        // sending server type

        const TYPE_TWILIO             = 'Twilio';
        const TYPE_TWILIOCOPILOT      = 'TwilioCopilot';
        const TYPE_EASYSENDSMS        = 'EasySendSMS';
        const TYPE_CHEAPGLOBALSMS     = 'CheapGlobalSMS';
        const TYPE_SAFARICOM          = 'Safaricom';
        const TYPE_FACILITAMOVEL      = 'FacilitaMovel';
        const TYPE_SMSDELIVERER       = 'Smsdeliverer';
        const TYPE_ROUNDSMS           = 'RoundSMS';
        const TYPE_YOSMS              = 'YoSMS';
        const TYPE_DIGINTRA           = 'Digintra';
        const TYPE_ALLMYSMS           = 'AllMySMS';
        const TYPE_ESOLUTIONS         = 'ESolutions';
        const TYPE_GUPSHUPIO          = 'GupshupIO';
        const TYPE_SEMAPHORE          = 'SemaPhore';
        const TYPE_ESTORESMS          = 'EStoreSMS';
        const TYPE_GOIP               = 'GoIP';
        const TYPE_MAILJET            = 'Mailjet';
        const TYPE_ADVANCEMSGSYS      = 'AdvanceMSGSys';
        const TYPE_UIPAPP             = 'UIPApp';
        const TYPE_SMSFRL             = 'SMSFRL';
        const TYPE_IMARTGROUP         = 'IMartGroup';
        const TYPE_GOSMSFUN           = 'GoSMSFun';
        const TYPE_VIBER              = 'Viber';
        const TYPE_TEXT_CALIBUR       = 'TextCalibur';
        const TYPE_D7NETWORKS         = 'D7Networks';
        const TYPE_WHATSAPP           = 'WhatsApp';
        const TYPE_ARKESEL            = 'Arkesel';
        const TYPE_SAVEWEBHOSTNET     = 'SaveWebHostNet';
        const TYPE_FAST2SMS           = 'Fast2SMS';
        const TYPE_MSG91              = 'MSG91';
        const TYPE_TELEAPI            = 'TeleAPI';
        const TYPE_BUDGETSMS          = 'BudgetSMS';
        const TYPE_OZONEDESK          = 'OzoneDesk';
        const TYPE_SKEBBY             = 'Skebby';
        const TYPE_BULKREPLY          = 'BulkReply';
        const TYPE_BULkSMS4BTC        = 'Bulksms4btc';
        const TYPE_INFOBIP            = 'Infobip';
        const TYPE_CLICKATELLTOUCH    = 'ClickatellTouch';
        const TYPE_CLICKATELLCENTRAL  = 'ClickatellCentral';
        const TYPE_ROUTEMOBILE        = 'RouteMobile';
        const TYPE_TEXTLOCAL          = 'TextLocal';
        const TYPE_PLIVO              = 'Plivo';
        const TYPE_PLIVOPOWERPACK     = 'PlivoPowerpack';
        const TYPE_SMSGLOBAL          = 'SMSGlobal';
        const TYPE_BULKSMS            = 'BulkSMS';
        const TYPE_VONAGE             = 'Vonage';
        const TYPE_1S2U               = '1s2u';
        const TYPE_MESSAGEBIRD        = 'MessageBird';
        const TYPE_AMAZONSNS          = 'AmazonSNS';
        const TYPE_TYNTEC             = 'Tyntec';
        const TYPE_WHATSAPPCHATAPI    = 'WhatsAppChatApi';
        const TYPE_KARIXIO            = 'KarixIO';
        const TYPE_SIGNALWIRE         = 'SignalWire';
        const TYPE_TELNYX             = 'Telnyx';
        const TYPE_TELNYXNUMBERPOOL   = 'TelnyxNumberPool';
        const TYPE_BANDWIDTH          = 'Bandwidth';
        const TYPE_SMPP               = 'SMPP';
        const TYPE_ROUTEENET          = 'RouteeNet';
        const TYPE_HUTCHLK            = 'HutchLk';
        const TYPE_TELETOPIASMS       = 'Teletopiasms';
        const TYPE_BROADCASTERMOBILE  = 'BroadcasterMobile';
        const TYPE_SOLUTIONS4MOBILES  = 'Solutions4mobiles';
        const TYPE_BEEMAFRICA         = 'BeemAfrica';
        const TYPE_BULKSMSONLINE      = 'BulkSMSOnline';
        const TYPE_FLOWROUTE          = 'FlowRoute';
        const TYPE_WAAPI              = 'WaApi';
        const TYPE_ELITBUZZBD         = 'ElitBuzzBD';
        const TYPE_GREENWEBBD         = 'GreenWebBD';
        const TYPE_HABLAMEV2          = 'HablameV2';
        const TYPE_ZAMTELCOZM         = 'ZamtelCoZm';
        const TYPE_CELLCAST           = 'CellCast';
        const TYPE_AFRICASTALKING     = 'AfricasTalking';
        const TYPE_CAIHCOM            = 'CaihCom';
        const TYPE_KECCELSMS          = 'KeccelSMS';
        const TYPE_JOHNSONCONNECT     = 'JohnsonConnect';
        const TYPE_SPEEDAMOBILE       = 'SpeedaMobile';
        const TYPE_SMSALA             = 'SMSala';
        const TYPE_TEXT2WORLD         = 'Text2World';
        const TYPE_ENABLEX            = 'EnableX';
        const TYPE_SPOOFSEND          = 'SpoofSend';
        const TYPE_ALHAJSMS           = 'AlhajSms';
        const TYPE_SENDROIDULTIMATE   = 'SendroidUltimate';
        const TYPE_REALSMS            = 'RealSMS';
        const TYPE_CALLR              = 'Callr';
        const TYPE_SKYETEL            = 'Skyetel';
        const TYPE_NIMBUZ             = 'Nimbuz';
        const TYPE_MOBITECH           = 'Mobitech';
        const TYPE_TRUBLOQ            = 'TRUBLOQ';
        const TYPE_HOSTPINNACLE       = 'HostPinnacle';
        const TYPE_LANKABELL          = 'LankaBell';
        const TYPE_PICKYASSIST        = 'PickyAssist';
        const TYPE_ZORRA              = 'Zorra';
        const TYPE_HOTMOBILE          = 'HotMobile';
        const TYPE_YUPCHAT            = 'YupChat';
        const TYPE_8x8                = '8x8';
        const TYPE_FONOIP             = 'FonoIP';
        const TYPE_WAZONE             = 'WaZone';
        const TYPE_QOOLIZE            = 'Qoolize';
        const TYPE_EBULKSMS           = 'EBulkSMS';
        const TYPE_CLICKSEND          = 'ClickSend';
        const TYPE_WHATSAPPBYTEMPLATE = 'FBWhatsAppByTemplate';
        const TYPE_ALIBABACLOUDSMS    = 'AlibabaCloudSMS';
        const TYPE_SMSMODE            = 'SMSMode';
        const TYPE_TECHCORE           = 'TechCore';
        const TYPE_ORANGE             = 'Orange';
        const TYPE_MMSCONSOLE         = 'MMSConsole';
        const TYPE_BMSGLOBAL          = 'BMSGlobal';
        const TYPE_GBESTSMS           = 'GBestSMS';
        const TYPE_SILVERSTREET       = 'SilverStreet';
        const TYPE_GLINTSMS           = 'GlintSMS';
        const TYPE_DATAGIFTING        = 'DataGifting';
        const TYPE_SMSHTTPREVE        = 'SMSHTTPReve';
        const TYPE_BULKSMSPROVIDERNG  = 'BulkSMSProviderNG';
        const TYPE_OZONESMS           = 'OzoneSMS';
        const TYPE_NIGERIABULKSMS     = 'NigeriaBulkSMS';
        const TYPE_AIRTELINDIA        = 'AirtelIndia';
        const TYPE_SMSAPI             = 'SMSAPI';
        const TYPE_SMSAPIONLINE       = 'SMSAPIOnline';
        const TYPE_TERMII             = 'Termii';
        const TYPE_VOXIMPLANT         = 'Voximplant';
        const TYPE_CLIQSMS            = 'CliqSMS';
        const TYPE_SMSVEND            = 'SMSVend';
        const TYPE_PMCSMS             = 'PMCSMS';
        const TYPE_WA2SALES           = 'WA2Sales';
        const TYPE_INTERAKT           = 'Interakt';
        const TYPE_JUICYSIMS          = 'Juicysims';
        const TYPE_SMSAFRICANG        = 'SMSAfricaNg';
        const TYPE_MOBILESMSNG        = 'MobileSMSNg';
        const TYPE_BSGWORLD           = 'BSGWorld';
        const TYPE_SNAPISMS           = 'SnapiSMS';
        const TYPE_SMSEXPERIENCE      = 'SMSExperience';
        const TYPE_CHALLENGESMS       = 'ChallengeSMS';
        const TYPE_BLACKSMS           = 'BlackSMS';
        const TYPE_ULTIMATESMS        = 'UltimateSMS';
        const TYPE_MOCEANAPI          = 'MoceanAPI';
        const TYPE_MESSAGGIO          = 'Messaggio';
        const TYPE_SMSURWAY           = 'SMSurWay';
        const TYPE_SMARTSMSSOLUTIONS  = 'SmartSMSSolutions';
        const TYPE_VOICEANDTEXT       = 'VoiceAndText';
        const TYPE_ETROSS             = 'Etross';
        const TYPE_DINSTAR            = 'Dinstar';
        const TYPE_SIMPLETEXTING      = 'SimpleTexting';
        const TYPE_WAVIX              = 'Wavix';
        const TYPE_UIPSMS             = 'UipSMS';
        const TYPE_TXTRIA             = 'TxTria';
        const TYPE_WHATSENDER         = 'Whatsender';
        const TYPE_YOOAPI             = 'YooAPI';
        const TYPE_DIAFAAN            = 'Diafaan';
        const TYPE_LAFRICAMOBILE      = 'LAfricaMobile';
        const TYPE_GUPSHUPIOTEMPLATE  = 'GupshupIOTemplate';
        const TYPE_AUDIENCEONE        = 'AudienceOne';
        const TYPE_LTR                = 'LTR';
        const TYPE_BULKSMSPLANS       = 'Bulksmsplans';
        const TYPE_SINCH              = 'Sinch';
        const TYPE_CMCOM              = 'CMCOM';

        /**
         * The attributes that are mass assignable.
         *
         * @var array
         * @note important! consider updating the $fillable variable, it will affect some other methods
         */
        protected $fillable = [
            'name',
            'user_id',
            'settings',
            'api_link',
            'voice_api_link',
            'mms_api_link',
            'whatsapp_api_link',
            'viber_api_link',
            'otp_api_link',
            'port',
            'username',
            'password',
            'route',
            'sms_type',
            'account_sid',
            'auth_id',
            'auth_token',
            'access_key',
            'access_token',
            'secret_access',
            'api_key',
            'api_secret',
            'user_token',
            'project_id',
            'api_token',
            'auth_key',
            'device_id',
            'region',
            'application_id',
            'c1',
            'c2',
            'c3',
            'c4',
            'c5',
            'c6',
            'c7',
            'type',
            'sms_per_request',
            'quota_value',
            'quota_base',
            'quota_unit',
            'custom_order',
            'schedule',
            'custom',
            'status',
            'two_way',
            'plain',
            'mms',
            'voice',
            'whatsapp',
            'viber',
            'otp',
            'source_addr_ton',
            'source_addr_npi',
            'dest_addr_ton',
            'dest_addr_npi',
            'success_keyword',
        ];

        /**
         *  The attributes that should be cast.
         *
         * @var string[]
         */
        protected $casts = [
            'schedule'        => 'boolean',
            'custom'          => 'boolean',
            'status'          => 'boolean',
            'two_way'         => 'boolean',
            'plain'           => 'boolean',
            'mms'             => 'boolean',
            'voice'           => 'boolean',
            'whatsapp'        => 'boolean',
            'viber'           => 'boolean',
            'otp'             => 'boolean',
            'quota_value'     => 'integer',
            'quota_base'      => 'integer',
            'sms_per_request' => 'integer',
            'port'            => 'integer',
        ];


        /**
         * Plans
         *
         * @return BelongsToMany
         *
         */
        public function plans(): BelongsToMany
        {
            return $this->belongsToMany(Plan::class, 'plans_sending_servers');
        }

        /**
         * Customer
         *
         * @return BelongsTo
         */

        public function customer(): BelongsTo
        {
            return $this->belongsTo(Customer::class);
        }

        /**
         * Admin
         *
         * @return BelongsTo
         *
         */
        public function admin(): BelongsTo
        {
            return $this->belongsTo(Admin::class);
        }

        /**
         * get custom sending server
         *
         * @return BelongsTo
         *
         */
        public function customSendingServer(): BelongsTo
        {
            return $this->belongsTo(CustomSendingServer::class, 'id', 'server_id');
        }

        /**
         * Active status scope
         *
         * @param        $query
         * @param bool   $status
         *
         * @return mixed
         */
        public function scopeStatus($query, bool $status): mixed
        {
            return $query->where('status', $status);
        }


        /**
         * Get sending limit types.
         *
         * @return array
         */
        public static function sendingLimitValues(): array
        {
            return [
                'unlimited'      => [
                    'quota_value' => -1,
                    'quota_base'  => -1,
                    'quota_unit'  => 'day',
                ],
                '100_per_minute' => [
                    'quota_value' => 100,
                    'quota_base'  => 1,
                    'quota_unit'  => 'minute',
                ],
                '1000_per_hour'  => [
                    'quota_value' => 1000,
                    'quota_base'  => 1,
                    'quota_unit'  => 'hour',
                ],
                '10000_per_day'  => [
                    'quota_value' => 10000,
                    'quota_base'  => 1,
                    'quota_unit'  => 'day',
                ],
            ];
        }


        /**
         * Get sending server's quota.
         *
         * @return string
         */
        public function getSendingQuota(): string
        {
            return $this->quota_value;
        }


        /**
         * Quota display.
         *
         * @return string
         */
        public function displayQuota(): string
        {
            if ($this->quota_value == -1) {
                return __('locale.plans.unlimited');
            }

            return $this->quota_value . '/' . $this->quota_base . ' ' . __('locale.labels.' . Tool::getPluralParse($this->quota_unit, $this->quota_base));
        }

        /**
         * Quota display.
         *
         * @return string
         */
        public function displayQuotaHtml(): string
        {
            if ($this->quota_value == -1) {
                return __('locale.plans.unlimited');
            }

            return '<code>' . $this->quota_value . '</code>/<code>' . $this->quota_base . ' ' . __('locale.labels.' . Tool::getPluralParse($this->quota_unit, $this->quota_base)) . '</code>';
        }


        public function setQuotaSettings(int $value, string $periodUnit, int $periodBase): void
        {
            $this->quota_base  = $periodBase;
            $this->quota_unit  = $periodUnit;
            $this->quota_value = $value;
        }

        /*** IMPLEMENTATION OF HasQuotaInterface ***/
        public function getQuotaSettings($name): ?array
        {
            $quota = [];

            if ($this->quota_value != QuotaManager::QUOTA_UNLIMITED) {
                $quota[] = [
                    'name'         => "Server's sending limit of {$this->quota_value} per {$this->quota_base} {$this->quota_unit}",
                    'period_unit'  => $this->quota_unit,
                    'period_value' => $this->quota_base,
                    'limit'        => $this->quota_value,
                ];
            }

            return $quota;
        }

    }
