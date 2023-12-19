<?php

    namespace App\Repositories\Eloquent;

    use App\Exceptions\GeneralException;
    use App\Helpers\Helper;
    use App\Library\aamarPay;
    use App\Library\CoinPayments;
    use App\Library\Flutterwave;
    use App\Library\MPGS;
    use App\Library\OrangeMoney;
    use App\Library\PayHereLK;
    use App\Library\PayU;
    use App\Library\PayUMoney;
    use App\Library\TwoCheckout;
    use App\Models\Country;
    use App\Models\Keywords;
    use App\Models\PaymentMethods;
    use App\Models\PhoneNumbers;
    use App\Models\Senderid;
    use App\Models\User;
    use App\Notifications\KeywordPurchase;
    use App\Repositories\Contracts\KeywordRepository;
    use Braintree\Gateway;
    use Carbon\Carbon;
    use Exception;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Support\Arr;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Storage;
    use Mollie\Api\MollieApiClient;
    use Paynow\Http\ConnectionException;
    use Paynow\Payments\HashMismatchException;
    use Paynow\Payments\InvalidIntegrationException;
    use Paynow\Payments\Paynow;
    use PayPalCheckoutSdk\Core\PayPalHttpClient;
    use PayPalCheckoutSdk\Core\ProductionEnvironment;
    use PayPalCheckoutSdk\Core\SandboxEnvironment;
    use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
    use Psr\SimpleCache\InvalidArgumentException;
    use Razorpay\Api\Api;
    use Razorpay\Api\Errors\BadRequestError;
    use Illuminate\Support\Facades\Session;
    use Selcom\ApigwClient\Client;
    use SimpleXMLElement;
    use Stripe\Stripe;
    use Throwable;

    class EloquentKeywordRepository extends EloquentBaseRepository implements KeywordRepository
    {
        /**
         * EloquentKeywordRepository constructor.
         *
         * @param Keywords $keyword
         */
        public function __construct(Keywords $keyword)
        {
            parent::__construct($keyword);
        }


        /**
         * @param array $input
         * @param array $billingCycle
         *
         * @return JsonResponse|Keywords
         * @throws GeneralException
         */
        public function store(array $input, array $billingCycle): Keywords|JsonResponse
        {

            /** @var Keywords $keyword */
            $keyword = $this->make(Arr::only($input, [
                'title',
                'keyword_name',
                'reply_text',
                'reply_voice',
                'price',
                'billing_cycle',
                'frequency_amount',
                'frequency_unit',
                'currency_id',
                'status',
            ]));

            $sender_id = null;

            if (auth()->user()->is_admin != 1 && auth()->user()->is_customer == 1 && auth()->user()->customer->getOption('sender_id_verification') == 'yes') {
                if (isset($input['originator'])) {
                    if ($input['originator'] == 'sender_id' && isset($input['sender_id'])) {
                        $sender_id = $input['sender_id'];
                    } else if ($input['originator'] == 'phone_number' && isset($input['phone_number'])) {
                        $sender_id = $input['phone_number'];
                    }
                } else if (isset($input['sender_id'])) {
                    $sender_id = $input['sender_id'];
                }

                $check_sender_id = Senderid::where('user_id', auth()->user()->id)->where('sender_id', $sender_id)->where('status', 'active')->first();
                if ( ! $check_sender_id) {
                    $number = PhoneNumbers::where('user_id', auth()->user()->id)->where('number', $sender_id)->where('status', 'assigned')->first();

                    if ( ! $number) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => __('locale.sender_id.sender_id_invalid', ['sender_id' => $sender_id]),
                        ]);
                    }
                }
            } else if (isset($input['sender_id'])) {
                $sender_id = $input['sender_id'];
            }

            $keyword->sender_id = $sender_id;

            $media_url = null;

            if (isset($input['reply_mms'])) {
                $image      = $input['reply_mms'];
                $media_path = $image->store('mms_file', 'public');
                $media_url  = asset(Storage::url($media_path));
            }

            $keyword->reply_mms = $media_url;

            if (isset($input['billing_cycle']) && $input['billing_cycle'] != 'custom') {
                $limits                    = $billingCycle[$input['billing_cycle']];
                $keyword->frequency_amount = $limits['frequency_amount'];
                $keyword->frequency_unit   = $limits['frequency_unit'];
            }


            if ($input['user_id'] != 0) {
                $user = User::find($input['user_id'])->is_customer;
                if ($user) {
                    $input['status']  = 'assigned';
                    $keyword->status  = 'assigned';
                    $keyword->user_id = $input['user_id'];
                } else {
                    throw new GeneralException(__('locale.auth.user_not_exist'));
                }
            } else {
                $keyword->user_id = 1;
            }

            if ($input['status'] == 'assigned') {
                $current                = Carbon::now();
                $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
            }

            if ( ! $this->save($keyword)) {
                throw new GeneralException(__('locale.exceptions.something_went_wrong'));
            }

            $user = User::find($keyword->user_id);

            if (Helper::app_config('keyword_notification_email')) {
                $admin = User::find(1);
                $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
            }

            if ($user->customer->getNotifications()['keyword'] == 'yes') {
                $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
            }

            return $keyword;

        }

        /**
         * @param Keywords $keyword
         *
         * @return bool
         */
        private function save(Keywords $keyword): bool
        {
            if ( ! $keyword->save()) {
                return false;
            }

            return true;
        }

        /**
         * @param Keywords $keyword
         * @param array    $input
         *
         * @param array    $billingCycle
         *
         * @return Keywords
         * @throws GeneralException
         */
        public function update(Keywords $keyword, array $input, array $billingCycle): Keywords
        {
            if (isset($input['reply_mms'])) {
                $image      = $input['reply_mms'];
                $media_path = $image->store('mms_file', 'public');
                $media_url  = asset(Storage::url($media_path));
            } else {
                $media_url = $keyword->reply_mms;
            }

            $input['reply_mms'] = $media_url;

            if (isset($input['billing_cycle']) && $input['billing_cycle'] != 'custom') {
                $limits                    = $billingCycle[$input['billing_cycle']];
                $input['frequency_amount'] = $limits['frequency_amount'];
                $input['frequency_unit']   = $limits['frequency_unit'];
            }

            if ($input['user_id'] == 0) {
                $input['user_id'] = 1;
            }

            if ( ! $keyword->update($input)) {
                throw new GeneralException(__('locale.exceptions.something_went_wrong'));
            }

            return $keyword;
        }

        /**
         * @param Keywords $keyword
         *
         * @return bool
         * @throws GeneralException
         */
        public function destroy(Keywords $keyword): bool
        {
            if ( ! $keyword->delete()) {
                throw new GeneralException(__('locale.exceptions.something_went_wrong'));
            }

            return true;
        }

        /**
         * @param array $ids
         *
         * @return mixed
         * @throws Exception|Throwable
         *
         */
        public function batchDestroy(array $ids): bool
        {
            DB::transaction(function () use ($ids) {
                // This won't call eloquent events, change to destroy if needed
                if ($this->query()->whereIn('uid', $ids)->delete()) {
                    return true;
                }

                throw new GeneralException(__('locale.exceptions.something_went_wrong'));
            });

            return true;
        }

        /**
         * @param array $ids
         *
         * @return mixed
         * @throws Exception|Throwable
         *
         */
        public function batchAvailable(array $ids): bool
        {
            DB::transaction(function () use ($ids) {
                if ($this->query()->whereIn('uid', $ids)
                    ->update(['status' => 'available'])
                ) {
                    return true;
                }

                throw new GeneralException(__('locale.exceptions.something_went_wrong'));
            });

            return true;
        }

        /**
         * update keyword information by customer
         *
         * @param Keywords $keyword
         * @param array    $input
         *
         * @return Keywords
         * @throws GeneralException
         */
        public function updateByCustomer(Keywords $keyword, array $input): Keywords
        {
            if (isset($input['originator'])) {
                if ($input['originator'] == 'sender_id') {
                    if ( ! isset($input['sender_id'])) {
                        throw new GeneralException(__('locale.sender_id.sender_id_required'));
                    }

                    $sender_id = $input['sender_id'];
                } else {
                    if ( ! isset($input['phone_number'])) {
                        throw new GeneralException(__('locale.sender_id.phone_numbers_required'));
                    }
                    $sender_id = $input['phone_number'];
                }
                $input['sender_id'] = $sender_id;
            }

            if (isset($input['reply_mms'])) {
                $image      = $input['reply_mms'];
                $media_path = $image->store('mms_file', 'public');
                $media_url  = asset(Storage::url($media_path));
            } else {
                $media_url = $keyword->reply_mms;
            }

            $input['reply_mms'] = $media_url;

            unset($input['originator']);
            unset($input['phone_number']);

            if ( ! $keyword->update($input)) {
                throw new GeneralException(__('locale.exceptions.something_went_wrong'));
            }

            return $keyword;
        }


        /**
         * release number
         *
         * @param Keywords $keyword
         * @param string   $id
         *
         * @return bool
         * @throws GeneralException
         */
        public function release(Keywords $keyword, string $id): bool
        {
            $available = $keyword->where('user_id', Auth::user()->id)->where('uid', $id)->first();

            if ($available) {
                $available->user_id       = 1;
                $available->status        = 'available';
                $available->validity_date = null;
                if ( ! $available->save()) {
                    throw new GeneralException(__('locale.exceptions.something_went_wrong'));
                }

                return true;
            }

            throw new GeneralException(__('locale.exceptions.something_went_wrong'));

        }


        /**
         * pay the payment
         *
         * @param Keywords $keyword
         * @param array    $input
         *
         * @return JsonResponse
         * @throws Exception
         * @throws InvalidArgumentException
         */
        public function payPayment(Keywords $keyword, array $input): JsonResponse
        {

            $paymentMethod = PaymentMethods::where('status', true)->where('type', $input['payment_methods'])->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                $item_name = __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name;

                switch ($paymentMethod->type) {

                    case PaymentMethods::TYPE_PAYPAL:

                        if ($credentials->environment == 'sandbox') {
                            $environment = new SandboxEnvironment($credentials->client_id, $credentials->secret);
                        } else {
                            $environment = new ProductionEnvironment($credentials->client_id, $credentials->secret);
                        }

                        $client = new PayPalHttpClient($environment);

                        $request = new OrdersCreateRequest();
                        $request->prefer('return=representation');

                        $request->body = [
                            "intent"              => "CAPTURE",
                            "purchase_units"      => [[
                                "reference_id" => $keyword->user->id . '_' . $keyword->uid,
                                'description'  => $item_name,
                                "amount"       => [
                                    "value"         => $keyword->price,
                                    "currency_code" => $keyword->currency->code,
                                ],
                            ]],
                            "application_context" => [
                                'brand_name' => config('app.name'),
                                'locale'     => config('app.locale'),
                                "cancel_url" => route('customer.keywords.payment_cancel', $keyword->uid),
                                "return_url" => route('customer.keywords.payment_success', $keyword->uid),
                            ],
                        ];

                        try {
                            $response = $client->execute($request);

                            if (isset($response->result->links)) {
                                foreach ($response->result->links as $link) {
                                    if ($link->rel == 'approve') {
                                        $redirect_url = $link->href;
                                        break;
                                    }
                                }
                            }

                            if (isset($redirect_url)) {
                                if ( ! empty($response->result->id)) {
                                    Session::put('payment_method', $paymentMethod->type);
                                    Session::put('paypal_payment_id', $response->result->id);
                                }

                                return response()->json([
                                    'status'       => 'success',
                                    'redirect_url' => $redirect_url,
                                ]);
                            }

                            return response()->json([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        } catch (Exception $exception) {

                            $errorData    = json_decode($exception->getMessage(), true);
                            $errorMessage = $errorData['details'][0]['description'] ?? 'An error occurred while processing the payment.';

                            return response()->json([
                                'status'  => 'error',
                                'message' => $errorMessage,
                            ]);
                        }

                    case PaymentMethods::TYPE_BRAINTREE:

                        try {
                            $gateway = new Gateway([
                                'environment' => $credentials->environment,
                                'merchantId'  => $credentials->merchant_id,
                                'publicKey'   => $credentials->public_key,
                                'privateKey'  => $credentials->private_key,
                            ]);

                            $clientToken = $gateway->clientToken()->generate();

                            return response()->json([
                                'status' => 'success',
                                'token'  => $clientToken,
                            ]);
                        } catch (Exception $exception) {
                            return response()->json([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }

                    case PaymentMethods::TYPE_STRIPE:

                        $publishable_key = $credentials->publishable_key;
                        $secret_key      = $credentials->secret_key;

                        Stripe::setApiKey($secret_key);

                        try {
                            $checkout_session = \Stripe\Checkout\Session::create([
                                'payment_method_types' => ['card'],
                                'customer_email'       => $input['email'],
                                'line_items'           => [[
                                    'price_data' => [
                                        'currency'     => $keyword->currency->code,
                                        'unit_amount'  => $keyword->price * 100,
                                        'product_data' => [
                                            'name' => $item_name,
                                        ],
                                    ],
                                    'quantity'   => 1,
                                ]],
                                'mode'                 => 'payment',
                                'success_url'          => route('customer.keywords.payment_success', $keyword->uid),
                                'cancel_url'           => route('customer.keywords.payment_cancel', $keyword->uid),
                            ]);

                            if ( ! empty($checkout_session->id)) {
                                Session::put('payment_method', $paymentMethod->type);
                                Session::put('session_id', $checkout_session->id);
                            }

                            return response()->json([
                                'status'          => 'success',
                                'session_id'      => $checkout_session->id,
                                'publishable_key' => $publishable_key,
                            ]);

                        } catch (Exception $exception) {

                            return response()->json([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);

                        }

                    case PaymentMethods::TYPE_2CHECKOUT:

                        Session::put('payment_method', $paymentMethod->type);

                        $checkout = new TwoCheckout();

                        $checkout->param('sid', $credentials->merchant_code);
                        if ($credentials->environment == 'sandbox') {
                            $checkout->param('demo', 'Y');
                        }
                        $checkout->param('return_url', route('customer.keywords.payment_success', $keyword->uid));
                        $checkout->param('li_0_name', $item_name);
                        $checkout->param('li_0_price', $keyword->price);
                        $checkout->param('li_0_quantity', 1);
                        $checkout->param('card_holder_name', $input['first_name'] . ' ' . $input['last_name']);
                        $checkout->param('city', $input['city']);
                        $checkout->param('country', $input['country']);
                        $checkout->param('email', $input['email']);
                        $checkout->param('phone', $input['phone']);
                        $checkout->param('currency_code', $keyword->currency->code);
                        $checkout->gw_submit();
                        exit();

                    case PaymentMethods::TYPE_PAYSTACK:

                        $curl = curl_init();

                        curl_setopt_array($curl, [
                            CURLOPT_URL            => "https://api.paystack.co/transaction/initialize",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CUSTOMREQUEST  => "POST",
                            CURLOPT_POSTFIELDS     => json_encode([
                                'amount'   => round($keyword->price) * 100,
                                'email'    => $input['email'],
                                'metadata' => [
                                    'keyword_id'   => $keyword->uid,
                                    'request_type' => 'keyword_payment',
                                ],
                            ]),
                            CURLOPT_HTTPHEADER     => [
                                "authorization: Bearer " . $credentials->secret_key,
                                "content-type: application/json",
                                "cache-control: no-cache",
                            ],
                        ]);

                        $response = curl_exec($curl);
                        $err      = curl_error($curl);

                        curl_close($curl);

                        if ($response === false) {
                            return response()->json([
                                'status'  => 'error',
                                'message' => 'Php curl show false value. Please contact with your provider',
                            ]);
                        }

                        if ($err) {
                            return response()->json([
                                'status'  => 'error',
                                'message' => $err,
                            ]);
                        }

                        $result = json_decode($response);


                        if ($result->status != 1) {

                            return response()->json([
                                'status'  => 'error',
                                'message' => $result->message,
                            ]);
                        }


                        return response()->json([
                            'status'       => 'success',
                            'redirect_url' => $result->data->authorization_url,
                        ]);

                    case PaymentMethods::TYPE_PAYU:

                        Session::put('payment_method', $paymentMethod->type);

                        $signature = "$credentials->client_secret~$credentials->client_id~keyword$keyword->uid~$keyword->price~$keyword->currency->code";
                        $signature = md5($signature);

                        $payu = new PayU();

                        $payu->param('merchantId', $credentials->client_id);
                        $payu->param('ApiKey', $credentials->client_secret);
                        $payu->param('referenceCode', 'keyword' . $keyword->uid);
                        $payu->param('description', $item_name);
                        $payu->param('amount', $keyword->price);
                        $payu->param('currency', $keyword->currency->code);
                        $payu->param('buyerEmail', $input['email']);
                        $payu->param('signature', $signature);
                        $payu->param('confirmationUrl', route('customer.keywords.payment_success', $keyword->uid));
                        $payu->param('responseUrl', route('customer.keywords.payment_cancel', $keyword->uid));
                        $payu->gw_submit();

                        exit();

                    case PaymentMethods::TYPE_PAYNOW:

                        $paynow = new Paynow(
                            $credentials->integration_id,
                            $credentials->integration_key,
                            route('customer.callback.paynow'),
                            route('customer.keywords.payment_success', $keyword->uid)
                        );


                        $payment = $paynow->createPayment($keyword->uid, $input['email']);
                        $payment->add($item_name, $keyword->price);


                        try {
                            $response = $paynow->send($payment);

                            if ($response->success()) {

                                Session::put('payment_method', $paymentMethod->type);
                                Session::put('paynow_poll_url', $response->pollUrl());

                                return response()->json([
                                    'status'       => 'success',
                                    'redirect_url' => $response->redirectUrl(),
                                ]);
                            }

                            return response()->json([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        } catch (ConnectionException|HashMismatchException|InvalidIntegrationException|Exception $e) {
                            return response()->json([
                                'status'  => 'error',
                                'message' => $e->getMessage(),
                            ]);
                        }

                    case PaymentMethods::TYPE_COINPAYMENTS:

                        Session::put('payment_method', $paymentMethod->type);

                        $coinPayment = new CoinPayments();

                        $order = [
                            'merchant'    => $credentials->merchant_id,
                            'item_name'   => $item_name,
                            'amountf'     => $keyword->price,
                            'currency'    => $keyword->currency->code,
                            'success_url' => route('customer.keywords.payment_success', $keyword->uid),
                            'cancel_url'  => route('customer.keywords.payment_cancel', $keyword->uid),
                        ];

                        foreach ($order as $item => $value) {
                            $coinPayment->param($item, $value);
                        }

                        $coinPayment->gw_submit();

                        exit();

                    case PaymentMethods::TYPE_INSTAMOJO:

                        $name = $input['first_name'];
                        if (isset($input['last_name'])) {
                            $name .= ' ' . $input['last_name'];
                        }

                        $payload = [
                            'purpose'                 => $item_name,
                            'amount'                  => $keyword->price,
                            'phone'                   => $input['phone'],
                            'buyer_name'              => $name,
                            'redirect_url'            => route('customer.keywords.payment_success', $keyword->uid),
                            'send_email'              => true,
                            'email'                   => $input['email'],
                            'allow_repeated_payments' => false,
                        ];

                        $headers = [
                            "X-Api-Key:" . $credentials->api_key,
                            "X-Auth-Token:" . $credentials->auth_token,
                        ];

                        $ch = curl_init();

                        curl_setopt($ch, CURLOPT_URL, 'https://www.instamojo.com/api/1.1/payment-requests/');
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                        $response = curl_exec($ch);
                        curl_close($ch);

                        if (isset($response->success)) {
                            if ($response->success) {

                                Session::put('payment_method', $paymentMethod->type);
                                Session::put('payment_request_id', $response->payment_request->id);

                                return response()->json([
                                    'status'       => 'success',
                                    'redirect_url' => $response->payment_request->longurl,
                                ]);
                            }

                            return response()->json([
                                'status'  => 'error',
                                'message' => $response->message,
                            ]);

                        }

                        return response()->json([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    case PaymentMethods::TYPE_PAYUMONEY:

                        Session::put('payment_method', $paymentMethod->type);

                        $environment = $credentials->environment;
                        $txnid       = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
                        $pinfo       = $item_name;
                        $hash        = strtolower(hash('sha512', $credentials->merchant_key . '|' . $txnid . '|' . $keyword->price . '|' . $pinfo . '|' . $input['first_name'] . '|' . $input['email'] . '||||||||||||' . $credentials->merchant_salt));

                        $payumoney = new PayUMoney($environment);

                        $payumoney->param('key', $credentials->merchant_key);
                        $payumoney->param('amount', $keyword->price);
                        $payumoney->param('hash', $hash);
                        $payumoney->param('txnid', $txnid);
                        $payumoney->param('firstname', $input['first_name']);
                        $payumoney->param('email', $input['email']);
                        $payumoney->param('phone', $input['phone']);
                        $payumoney->param('productinfo', $pinfo);
                        $payumoney->param('surl', route('customer.keywords.payment_success', $keyword->uid));
                        $payumoney->param('furl', route('customer.keywords.payment_cancel', $keyword->uid));

                        if (isset($input['last_name'])) {
                            $payumoney->param('lastname', $input['last_name']);
                        }

                        if (isset($input['address'])) {
                            $payumoney->param('address1', $input['address']);
                        }

                        if (isset($input['city'])) {
                            $payumoney->param('city', $input['city']);
                        }
                        if (isset($input['country'])) {
                            $payumoney->param('country', $input['country']);
                        }

                        $payumoney->gw_submit();

                        exit();

                    case PaymentMethods::TYPE_RAZORPAY:

                        try {
                            $api = new Api($credentials->key_id, $credentials->key_secret);

                            $link = $api->invoice->create([
                                'type'        => 'link',
                                'amount'      => $keyword->price * 100,
                                'description' => $item_name,
                                'customer'    => [
                                    'email' => $input['email'],
                                ],
                            ]);


                            if (isset($link->id) && isset($link->short_url)) {

                                Session::put('razorpay_order_id', $link->order_id);

                                $keyword->update([
                                    'transaction_id' => $link->order_id,
                                ]);

                                return response()->json([
                                    'status'       => 'success',
                                    'redirect_url' => $link->short_url,
                                ]);
                            }

                            return response()->json([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        } catch (BadRequestError $exception) {
                            return response()->json([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }

                    case PaymentMethods::TYPE_SSLCOMMERZ:

                        $post_data                 = [];
                        $post_data['store_id']     = $credentials->store_id;
                        $post_data['store_passwd'] = $credentials->store_passwd;
                        $post_data['total_amount'] = $keyword->price;
                        $post_data['currency']     = $keyword->currency->code;
                        $post_data['tran_id']      = $keyword->uid;
                        $post_data['success_url']  = route('customer.callback.sslcommerz.keywords', $keyword->uid);
                        $post_data['fail_url']     = route('customer.callback.sslcommerz.keywords', $keyword->uid);
                        $post_data['cancel_url']   = route('customer.callback.sslcommerz.keywords', $keyword->uid);

                        $post_data['product_category'] = "keywords";
                        $post_data['emi_option']       = "0";

                        $post_data['cus_name']    = $input['first_name'];
                        $post_data['cus_email']   = $input['email'];
                        $post_data['cus_add1']    = $input['address'];
                        $post_data['cus_city']    = $input['city'];
                        $post_data['cus_country'] = $input['country'];
                        $post_data['cus_phone']   = $input["phone"];


                        if (isset($input['postcode'])) {
                            $post_data['cus_postcode'] = $input['postcode'];
                        }


                        $post_data['shipping_method'] = 'No';
                        $post_data['num_of_item']     = '1';


                        $post_data['cart']            = json_encode([
                            ["product" => $item_name, "amount" => $keyword->price],
                        ]);
                        $post_data['product_name']    = $item_name;
                        $post_data['product_profile'] = 'non-physical-goods';
                        $post_data['product_amount']  = $keyword->price;

                        if ($credentials->environment == 'sandbox') {
                            $direct_api_url = "https://sandbox.sslcommerz.com/gwprocess/v4/api.php";
                        } else {
                            $direct_api_url = "https://securepay.sslcommerz.com/gwprocess/v4/api.php";
                        }

                        $handle = curl_init();
                        curl_setopt($handle, CURLOPT_URL, $direct_api_url);
                        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
                        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
                        curl_setopt($handle, CURLOPT_POST, 1);
                        curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
                        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false); # KEEP IT FALSE IF YOU RUN FROM LOCAL PC

                        $content = curl_exec($handle);
                        $code    = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                        if ($code == 200 && ! (curl_errno($handle))) {
                            curl_close($handle);
                            $response = json_decode($content, true);

                            if (isset($response['GatewayPageURL']) && $response['GatewayPageURL'] != "") {

                                return response()->json([
                                    'status'       => 'success',
                                    'redirect_url' => $response['GatewayPageURL'],
                                ]);

                            } else {
                                return response()->json([
                                    'status'  => 'error',
                                    'message' => $response['failedreason'],
                                ]);
                            }
                        } else {
                            curl_close($handle);

                            return response()->json([
                                'status'  => 'error',
                                'message' => 'FAILED TO CONNECT WITH SSLCOMMERZ API',
                            ]);
                        }

                    case PaymentMethods::TYPE_AAMARPAY:

                        Session::put('payment_method', $paymentMethod->type);

                        $checkout = new aamarPay($credentials->environment);

                        $checkout->param('store_id', $credentials->store_id);
                        $checkout->param('signature_key', $credentials->signature_key);
                        $checkout->param('desc', $item_name);
                        $checkout->param('amount', $keyword->price);
                        $checkout->param('currency', $keyword->currency->code);
                        $checkout->param('tran_id', $keyword->uid);
                        $checkout->param('success_url', route('customer.callback.aamarpay.keywords', $keyword->uid));
                        $checkout->param('fail_url', route('customer.callback.aamarpay.keywords', $keyword->uid));
                        $checkout->param('cancel_url', route('customer.callback.aamarpay.keywords', $keyword->uid));

                        $checkout->param('cus_name', $input['first_name']);
                        $checkout->param('cus_email', $input['email']);
                        $checkout->param('cus_add1', $input['address']);
                        $checkout->param('cus_add2', $input['address']);
                        $checkout->param('cus_city', $input['city']);
                        $checkout->param('cus_country', $input['country']);
                        $checkout->param('cus_phone', $input['phone']);
                        if (isset($input['postcode'])) {
                            $checkout->param('cus_postcode', $input['postcode']);
                        }

                        $checkout->gw_submit();
                        exit();

                    case PaymentMethods::TYPE_FLUTTERWAVE:

                        $checkout = new Flutterwave();

                        $checkout->param('public_key', $credentials->public_key);
                        $checkout->param('amount', $keyword->price);
                        $checkout->param('currency', $keyword->currency->code);
                        $checkout->param('tx_ref', $keyword->uid);
                        $checkout->param('redirect_url', route('customer.callback.flutterwave.keywords'));
                        $checkout->param('customizations[title]', $item_name);
                        $checkout->param('customizations[description]', $item_name);
                        $checkout->param('customer[name]', $input['first_name'] . ' ' . $input['last_name']);
                        $checkout->param('customer[email]', $input['email']);
                        $checkout->param('customer[phone_number]', $input['phone']);
                        $checkout->param('meta[user_id]', auth()->user()->id);
                        $checkout->gw_submit();
                        exit();

                    case PaymentMethods::TYPE_DIRECTPAYONLINE:

                        if ($credentials->environment == 'production') {
                            $payment_url = 'https://secure.3gdirectpay.com';
                        } else {
                            $payment_url = 'https://secure1.sandbox.directpay.online';
                        }

                        $companyToken        = $credentials->company_token;
                        $accountType         = $credentials->account_type;
                        $paymentAmount       = $keyword->price;
                        $paymentCurrency     = $keyword->currency->code;
                        $reference           = uniqid();
                        $odate               = date('Y/m/d H:i');
                        $redirectURL         = route('customer.keywords.payment_success', $keyword->uid);
                        $backURL             = route('customer.keywords.payment_cancel', $keyword->uid);
                        $customer_email      = auth()->user()->email;
                        $customer_first_name = auth()->user()->first_name;
                        $customer_last_name  = auth()->user()->last_name;

                        $postXml = <<<POSTXML
<?xml version="1.0" encoding="utf-8"?>
        <API3G>
        <CompanyToken>$companyToken</CompanyToken>
        <Request>createToken</Request>
        <Transaction>
        <PaymentAmount>$paymentAmount</PaymentAmount>
        <PaymentCurrency>$paymentCurrency</PaymentCurrency>
        <CompanyRef>$reference</CompanyRef>
        <customerEmail>$customer_email</customerEmail>
        <customerFirstName>$customer_first_name</customerFirstName>
        <customerLastName>$customer_last_name</customerLastName>
        <RedirectURL>$redirectURL</RedirectURL>
        <BackURL>$backURL</BackURL>
        <TransactionSource>whmcs</TransactionSource>
        </Transaction>
        <Services>
        <Service>
        <ServiceType>$accountType</ServiceType>
        <ServiceDescription>$item_name</ServiceDescription>
        <ServiceDate>$odate</ServiceDate>
        </Service>
        </Services>
        </API3G>
POSTXML;


                        $curl = curl_init();
                        curl_setopt_array($curl, [
                            CURLOPT_URL            => $payment_url . "/API/v6/",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING       => "",
                            CURLOPT_MAXREDIRS      => 10,
                            CURLOPT_TIMEOUT        => 30,
                            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST  => "POST",
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                            CURLOPT_POSTFIELDS     => $postXml,
                            CURLOPT_HTTPHEADER     => [
                                "cache-control: no-cache",
                            ],
                        ]);

                        $response = curl_exec($curl);
                        $error    = curl_error($curl);

                        curl_close($curl);

                        if ($response != '') {
                            $xml = new SimpleXMLElement($response);

                            if ($xml->xpath('Result')[0] != '000') {
                                return response()->json([
                                    'status'  => 'error',
                                    'message' => ! empty($error) ? $error : 'Unknown error occurred in token creation',
                                ]);
                            }

                            $transToken = $xml->xpath('TransToken')[0]->__toString();

                            try {
                                $curl = curl_init();
                                curl_setopt_array($curl, [
                                    CURLOPT_URL            => $payment_url . "/API/v6/",
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING       => "",
                                    CURLOPT_MAXREDIRS      => 10,
                                    CURLOPT_TIMEOUT        => 30,
                                    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST  => "POST",
                                    CURLOPT_POSTFIELDS     => "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n<API3G>\r\n  <CompanyToken>" . $companyToken . "</CompanyToken>\r\n  <Request>verifyToken</Request>\r\n  <TransactionToken>" . $transToken . "</TransactionToken>\r\n</API3G>",
                                    CURLOPT_HTTPHEADER     => [
                                        "cache-control: no-cache",
                                    ],
                                ]);

                                $response = curl_exec($curl);
                                $err      = curl_error($curl);

                                curl_close($curl);

                                if (strlen($err) > 0) {

                                    return response()->json([
                                        'status'  => 'error',
                                        'message' => $err,
                                    ]);
                                }

                                $verify = new SimpleXMLElement($response);
                                if ($verify->Result->__toString() === '900') {

                                    Session::put('payment_method', $paymentMethod->type);

                                    return response()->json([
                                        'status'       => 'success',
                                        'redirect_url' => $payment_url . '/payv2.php?ID=' . $transToken,
                                    ]);
                                }
                            } catch (Exception $e) {

                                return response()->json([
                                    'status'  => 'error',
                                    'message' => $e->getMessage(),
                                ]);
                            }
                        }

                        return response()->json([
                            'status'  => 'error',
                            'message' => ! empty($error) ? $error : 'Unknown error occurred in token creation',
                        ]);

                    case PaymentMethods::TYPE_PAYGATEGLOBAL:

                        $order_id = str_random(10);

                        $parameters = [
                            'token'    => $credentials->api_key,
                            'amount'   => $keyword->price,
                            'identify' => $order_id,
                            'url'      => route('customer.keywords.payment_success', $keyword->uid),
                        ];
                        $parameters = http_build_query($parameters);

                        return response()->json([
                            'status'       => 'success',
                            'redirect_url' => 'https://paygateglobal.com/v1/page?' . $parameters,
                        ]);

                    case PaymentMethods::TYPE_ORANGEMONEY:

                        $payment = new OrangeMoney($credentials->auth_header, $credentials->merchant_key);

                        $data = [
                            "merchant_key" => $credentials->merchant_key,
                            "currency"     => $keyword->currency->code,
                            "order_id"     => str_random(10),
                            "amount"       => $keyword->price,
                            'payment_url'  => $credentials->payment_url,
                            "return_url"   => route('customer.keywords.payment_cancel', $keyword->uid),
                            "cancel_url"   => route('customer.keywords.payment_cancel', $keyword->uid),
                            "notif_url"    => route('customer.keywords.payment_success', $keyword->uid),
                            "lang"         => config('app.locale'),
                            "reference"    => $keyword->uid,
                        ];

                        $callback_data = $payment->getPaymentUrl($data);

                        if (array_key_exists('payment_url', $callback_data)) {

                            Session::put('payment_method', $paymentMethod->type);
                            Session::put('payment_request_id', $callback_data['notif_token']);

                            return response()->json([
                                'status'       => 'success',
                                'redirect_url' => $callback_data['payment_url'],
                            ]);
                        } else if (array_key_exists('error', $callback_data)) {
                            return response()->json([
                                'status'  => 'error',
                                'message' => $callback_data['error'],
                            ]);
                        } else {
                            return response()->json([
                                'status'  => 'error',
                                'message' => 'FAILED TO CONNECT WITH OrangeMoney API',
                            ]);
                        }

                    case PaymentMethods::TYPE_CINETPAY:

                        $transaction_id = str_random(10);

                        $payment_data = [
                            'apikey'                => $credentials->api_key,
                            'site_id'               => $credentials->site_id,
                            'transaction_id'        => $transaction_id,
                            'amount'                => $keyword->price,
                            'currency'              => $keyword->currency->code,
                            'description'           => $item_name,
                            'customer_name'         => $input['first_name'] . ' ' . $input['last_name'],
                            'customer_email'        => $input['email'],
                            'customer_phone_number' => $input['phone'],
                            'customer_address'      => $input['address'],
                            'customer_city'         => $input['city'],
                            'customer_country'      => Country::getIsoCode($input['country']),
                            "notify_url"            => route('customer.keywords.payment_cancel', $keyword->uid),
                            "return_url"            => route('customer.keywords.payment_success', $keyword->uid),
                            'channels'              => 'ALL',
                            'lang'                  => config('app.locale'),
                            'metadata'              => 'keyword_id_' . $keyword->uid,
                        ];

                        if (isset($input['postcode'])) {
                            $payment_data['customer_zip_code'] = $input['postcode'];
                        }


                        try {

                            $curl = curl_init();

                            curl_setopt_array($curl, [
                                CURLOPT_URL            => $credentials->payment_url,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_CUSTOMREQUEST  => "POST",
                                CURLOPT_POSTFIELDS     => json_encode($payment_data),
                                CURLOPT_HTTPHEADER     => [
                                    "content-type: application/json",
                                    "cache-control: no-cache",
                                ],
                            ]);

                            $response = curl_exec($curl);
                            $err      = curl_error($curl);

                            curl_close($curl);

                            if ($response === false) {
                                return response()->json([
                                    'status'  => 'error',
                                    'message' => 'Php curl show false value. Please contact with your provider',
                                ]);
                            }

                            if ($err) {
                                return response()->json([
                                    'status'  => 'error',
                                    'message' => $err,
                                ]);
                            }

                            $result = json_decode($response, true);


                            if (is_array($result) && array_key_exists('code', $result)) {
                                if ($result['code'] == '201') {

                                    Session::put('payment_method', $paymentMethod->type);
                                    Session::put('cinetPay_transaction_id', $transaction_id);
                                    Session::put('cinetPay_payment_token', $result['data']['payment_token']);

                                    return response()->json([
                                        'status'       => 'success',
                                        'redirect_url' => $result['data']['payment_url'],
                                    ]);
                                }

                                return response()->json([
                                    'status'  => 'error',
                                    'message' => $result['message'],
                                ]);
                            }

                            return response()->json([
                                'status'       => 'error',
                                'redirect_url' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        } catch (Exception $ex) {

                            return response()->json([
                                'status'       => 'error',
                                'redirect_url' => $ex->getMessage(),
                            ]);
                        }

                    case PaymentMethods::TYPE_AUTHORIZE_NET:
                    case PaymentMethods::TYPE_VODACOMMPESA:
                        return response()->json([
                            'status'      => 'success',
                            'credentials' => $credentials,
                        ]);

                    case PaymentMethods::TYPE_PAYHERELK:

                        Session::put('payment_method', $paymentMethod->type);
                        $order_id = str_random(10);

                        $checkout = new PayHereLK($credentials->environment);

                        $checkout->param('merchant_id', $credentials->merchant_id);
                        $checkout->param('items', $item_name);
                        $checkout->param('amount', $keyword->price);
                        $checkout->param('currency', $keyword->currency->code);
                        $checkout->param('order_id', $order_id);
                        $checkout->param('return_url', route('customer.keywords.payment_success', ['keyword' => $keyword->uid, 'payment_method' => $paymentMethod->type]));
                        $checkout->param('cancel_url', route('customer.keywords.payment_cancel', $keyword->uid));
                        $checkout->param('notify_url', route('customer.keywords.payment_cancel', $keyword->uid));
                        $checkout->param('first_name', $input['first_name']);

                        if (isset($input['last_name'])) {
                            $checkout->param('last_name', $input['last_name']);
                        } else {
                            $checkout->param('last_name', $input['first_name']);
                        }

                        $checkout->param('email', $input['email']);
                        $checkout->param('address', $input['address']);
                        $checkout->param('city', $input['city']);
                        $checkout->param('country', $input['country']);
                        $checkout->param('phone', $input['phone']);

                        $checkout->gw_submit();
                        exit();

                    case PaymentMethods::TYPE_MOLLIE:

                        $mollie = new MollieApiClient();
                        $mollie->setApiKey($credentials->api_key);
                        $payment = $mollie->payments->create([
                            "amount"      => [
                                "currency" => $keyword->currency->code,
                                "value"    => number_format((float) $keyword->price, 2, '.', ''),
                            ],
                            "description" => $item_name,
                            "redirectUrl" => route('customer.keywords.payment_success', $keyword->uid),
                            "metadata"    => [
                                'user'           => \auth()->user()->id,
                                'number'         => $keyword->uid,
                                'payment_method' => $paymentMethod->uid,
                            ],
                        ]);

                        Session::put('payment_method', $paymentMethod->type);
                        Session::put('payment_id', $payment->id);

                        return response()->json([
                            'status'       => 'success',
                            'redirect_url' => $payment->getCheckoutUrl(),
                        ]);

                    /*Version 3.6*/

                    case PaymentMethods::TYPE_EASYPAY:

                        $body = [
                            "type"    => ["single"],
                            "payment" => [
                                "methods"         => ['cc', 'mb', 'mbw', 'dd', 'vi', 'uf', 'sc'],
                                "type"            => "sale",
                                "capture"         => [
                                    'descriptive' => $item_name,
                                ],
                                "currency"        => $keyword->currency->code,
                                'expiration_time' => null,
                            ],
                            "order"   => [
                                "key"   => $keyword->uid,
                                "value" => floatval($keyword->price),
                                "items" => [
                                    [
                                        "key"         => $keyword->uid,
                                        "description" => $item_name,
                                        "value"       => floatval($keyword->price),
                                        "quantity"    => 1,
                                    ],
                                ],
                            ],
                        ];

                        $headers = [
                            "AccountId: " . $credentials->account_id,
                            "ApiKey: " . $credentials->api_key,
                            'Content-Type: application/json',
                        ];

                        $curlOpts = [
                            CURLOPT_URL            => $credentials->payment_url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POST           => 1,
                            CURLOPT_TIMEOUT        => 60,
                            CURLOPT_POSTFIELDS     => json_encode($body),
                            CURLOPT_HTTPHEADER     => $headers,
                        ];

                        $curl = curl_init();
                        curl_setopt_array($curl, $curlOpts);
                        $response = curl_exec($curl);
                        curl_close($curl);

                        return response()->json([
                            'status' => 'success',
                            'data'   => $response,
                        ]);

                    case PaymentMethods::TYPE_FEDAPAY:
                        return response()->json([
                            'status'     => 'success',
                            'public_key' => $credentials->public_key,
                            'keyword'    => $keyword,
                        ]);

                    /*Version 3.8*/

                    case PaymentMethods::TYPE_SELCOMMOBILE:
                        Session::put('payment_method', $paymentMethod->type);

                        $orderMinArray = [
                            "vendor"       => $credentials->vendor,
                            "order_id"     => $keyword->uid,
                            "buyer_email"  => $input['email'],
                            "buyer_name"   => $input['first_name'] . ' ' . $input['last_name'],
                            "buyer_phone"  => $input['phone'],
                            "amount"       => $keyword->price,
                            "currency"     => $keyword->currency->code,
                            "redirect_url" => base64_encode(route('customer.keywords.payment_success', $keyword->uid)),
                            "cancel_url"   => base64_encode(route('customer.keywords.payment_cancel', $keyword->uid)),
                            "webhook"      => base64_encode(route('customer.keywords.payment_cancel', $keyword->uid)),

                            "billing.firstname"         => $input['first_name'],
                            "billing.lastname"          => $input['last_name'],
                            "billing.address_1"         => $input['address'],
                            "billing.city"              => $input['city'],
                            "billing.state_or_region"   => $input['city'],
                            "billing.postcode_or_pobox" => $input['postcode'],
                            "billing.country"           => $input['country'],
                            "billing.phone"             => $input['phone'],
                            "buyer_remarks"             => $item_name,
                            "merchant_remarks"          => $item_name,
                            "payment_methods"           => "ALL",
                            "no_of_items"               => 1,
                        ];

                        $client = new Client($credentials->payment_url, $credentials->api_key, $credentials->api_secret);

                        // path relative to base url
                        $orderMinPath = "/checkout/create-order";

                        // create order minimal
                        try {
                            $response = $client->postFunc($orderMinPath, $orderMinArray);

                            if (isset($response) && is_array($response) && array_key_exists('data', $response) && array_key_exists('result', $response)) {
                                if ($response['result'] == 'SUCCESS') {
                                    return response()->json([
                                        'status'       => 'success',
                                        'message'      => $response['message'],
                                        'redirect_url' => base64_decode($response['data'][0]['payment_gateway_url']),
                                    ]);
                                } else {
                                    return response()->json([
                                        'status'  => 'error',
                                        'message' => $response['message'],
                                    ]);
                                }
                            }

                            return response()->json([
                                'status'  => 'error',
                                'message' => $response,
                            ]);

                        } catch (Exception $exception) {
                            return response()->json([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }


                    /*Version 3.9*/
                    case PaymentMethods::TYPE_MPGS:
                        $config = [
                            'payment_url'             => $credentials->payment_url,
                            'api_version'             => $credentials->api_version,
                            'merchant_id'             => $credentials->merchant_id,
                            'authentication_password' => $credentials->authentication_password,
                        ];

                        if (isset($credentials->merchant_name)) {
                            $config['merchant_name'] = $credentials->merchant_name;
                        }

                        if (isset($credentials->merchant_address)) {
                            $config['merchant_address'] = $credentials->merchant_address;
                        }

                        $order_id = uniqid();

                        $paymentData = [
                            'user_id'     => Auth::user()->id,
                            'order_id'    => $order_id,
                            'amount'      => $keyword->price,
                            'currency'    => $keyword->currency->code,
                            'first_name'  => $input['first_name'],
                            'last_name'   => $input['last_name'],
                            'phone'       => $input['phone'],
                            'email'       => $input['email'],
                            'address'     => $input['address'],
                            'city'        => $input['city'],
                            'country'     => $input['country'],
                            'post_code'   => $input['postcode'],
                            'description' => $item_name,
                            'return_url'  => route('customer.keywords.payment_success', $keyword->uid),
                            "cancel_url"  => route('customer.keywords.payment_cancel', [
                                'keyword'        => $keyword->uid,
                                'payment_method' => PaymentMethods::TYPE_MPGS,
                                'order_id'       => $order_id,
                            ]),
                        ];

                        $mpgs      = new MPGS($config, $paymentData);
                        $getResult = $mpgs->submit();

                        if (isset($getResult->getData()->status) && $getResult->getData()->status == 'error') {
                            return response()->json([
                                'status'  => 'error',
                                'message' => $getResult->getData()->message,
                            ]);
                        }

                        exit();


                    case PaymentMethods::TYPE_CASH:

                        return response()->json([
                            'status' => 'success',
                            'data'   => $credentials,
                        ]);

                }

                return response()->json([
                    'status'  => 'error',
                    'message' => __('locale.payment_gateways.not_found'),
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);

        }

    }
