<?php

    namespace App\Models;

    use App\Library\Traits\HasUid;
    use Illuminate\Database\Eloquent\Model;

    /**
     * @method static where(string $string, $uid)
     * @method static create(array $gateway)
     * @property mixed options
     * @property mixed name
     * @property mixed $api_key
     */
    class PaymentMethods extends Model
    {

        use HasUid;

        // PaymentMethod type
        const TYPE_CASH            = 'offline_payment';
        const TYPE_PAYPAL          = 'paypal';
        const TYPE_STRIPE          = 'stripe';
        const TYPE_BRAINTREE       = 'braintree';
        const TYPE_AUTHORIZE_NET   = 'authorize_net';
        const TYPE_2CHECKOUT       = '2checkout';
        const TYPE_PAYSTACK        = 'paystack';
        const TYPE_PAYU            = 'payu';
        const TYPE_SLYDEPAY        = 'slydepay';
        const TYPE_PAYNOW          = 'paynow';
        const TYPE_COINPAYMENTS    = 'coinpayments';
        const TYPE_INSTAMOJO       = 'instamojo';
        const TYPE_PAYUMONEY       = 'payumoney';
        const TYPE_RAZORPAY        = 'razorpay';
        const TYPE_SSLCOMMERZ      = 'sslcommerz';
        const TYPE_AAMARPAY        = 'aamarpay';
        const TYPE_FLUTTERWAVE     = 'flutterwave';
        const TYPE_DIRECTPAYONLINE = 'directpayonline';
        const TYPE_SMANAGER        = 'smanager';
        const TYPE_PAYGATEGLOBAL   = 'paygateglobal';
        const TYPE_ORANGEMONEY     = 'orangemoney';
        const TYPE_CINETPAY        = 'cinetpay';
        const TYPE_AZAMPAY         = 'azampay';
        const TYPE_VODACOMMPESA    = 'vodacommpesa';
        const TYPE_PAYHERELK       = 'payherelk';
        const TYPE_MOLLIE          = 'mollie';
        const TYPE_EASYPAY         = 'easypay';
        const TYPE_FEDAPAY         = 'fedapay';
        const TYPE_SELCOMMOBILE    = 'selcommobile';
        const TYPE_LIQPAY          = 'liqpay';
        const TYPE_PAYTECH         = 'paytech';
        const TYPE_MPGS            = 'mpgs';


        /**
         * The attributes that are mass assignable.
         *
         * @var array
         */
        protected $fillable = [
            'name', 'options', 'status', 'type',
        ];

        /**
         * @var array
         */
        protected $casts = [
            'status' => 'boolean',
        ];


        /**
         * Get options.
         *
         * @return array
         */
        public function getOptions(): array
        {
            return json_decode($this->options, true);
        }

        /**
         * Get option.
         *
         * @param $name
         *
         * @return string|null
         */
        public function getOption($name): ?string
        {
            $options = $this->getOptions();

            return $options[$name] ?? null;
        }

    }
