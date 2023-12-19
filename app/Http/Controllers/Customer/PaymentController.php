<?php

    namespace App\Http\Controllers\Customer;

    use App\Helpers\Helper;
    use App\Http\Controllers\Controller;
    use App\Library\MPesa;
    use App\Library\MPGS;
    use App\Models\Invoices;
    use App\Models\Keywords;
    use App\Models\Notifications;
    use App\Models\PaymentMethods;
    use App\Models\PhoneNumbers;
    use App\Models\Plan;
    use App\Models\Senderid;
    use App\Models\Subscription;
    use App\Models\SubscriptionLog;
    use App\Models\SubscriptionTransaction;
    use App\Models\User;
    use App\Notifications\KeywordPurchase;
    use App\Notifications\NumberPurchase;
    use App\Notifications\SubscriptionPurchase;
    use Braintree\Gateway;
    use Carbon\Carbon;
    use Exception;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\RedirectResponse;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Auth;
    use Mollie\Api\MollieApiClient;
    use net\authorize\api\constants\ANetEnvironment;
    use Paynow\Payments\Paynow;
    use PayPalCheckoutSdk\Core\PayPalHttpClient;
    use PayPalCheckoutSdk\Core\ProductionEnvironment;
    use PayPalCheckoutSdk\Core\SandboxEnvironment;
    use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
    use Razorpay\Api\Api;
    use Razorpay\Api\Errors\SignatureVerificationError;
    use Illuminate\Support\Facades\Session;
    use Selcom\ApigwClient\Client;
    use SimpleXMLElement;
    use Stripe\Exception\ApiErrorException;
    use Stripe\StripeClient;
    use net\authorize\api\contract\v1 as AnetAPI;
    use net\authorize\api\controller as AnetController;
    use Illuminate\Support\Facades\Validator;

    class PaymentController extends Controller
    {

        /**
         * store notification
         *
         * @param $type
         * @param $name
         * @param $user_name
         *
         * @return void
         */
        public function createNotification($type = null, $name = null, $user_name = null): void
        {
            Notifications::create([
                'user_id'           => 1,
                'notification_for'  => 'admin',
                'notification_type' => $type,
                'message'           => $name . ' Purchased By ' . $user_name,
            ]);
        }

        /**
         * successful sender id purchase using PayPal
         *
         * @param Senderid $senderid
         * @param Request  $request
         *
         * @return RedirectResponse
         * @throws Exception
         */
        public function successfulSenderIDPayment(Senderid $senderid, Request $request): RedirectResponse
        {
            $payment_method = Session::get('payment_method');
            if ($payment_method == null) {
                $payment_method = $request->get('payment_method');
            }

            switch ($payment_method) {
                case PaymentMethods::TYPE_PAYPAL:
                    $token = Session::get('paypal_payment_id');
                    if ($request->get('token') == $token) {
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYPAL)->first();

                        if ($paymentMethod) {
                            $credentials = json_decode($paymentMethod->options);

                            if ($credentials->environment == 'sandbox') {
                                $environment = new SandboxEnvironment($credentials->client_id, $credentials->secret);
                            } else {
                                $environment = new ProductionEnvironment($credentials->client_id, $credentials->secret);
                            }

                            $client = new PayPalHttpClient($environment);

                            $request = new OrdersCaptureRequest($token);
                            $request->prefer('return=representation');

                            try {
                                // Call API with your client and get a response for your call
                                $response = $client->execute($request);

                                if ($response->statusCode == '201' && $response->result->status == 'COMPLETED' && isset($response->id)) {
                                    $invoice = Invoices::create([
                                        'user_id'        => $senderid->user_id,
                                        'currency_id'    => $senderid->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $senderid->price,
                                        'type'           => Invoices::TYPE_SENDERID,
                                        'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                        'transaction_id' => $response->id,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                   = Carbon::now();
                                        $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                        $senderid->status          = 'active';
                                        $senderid->payment_claimed = true;
                                        $senderid->save();

                                        $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                        return redirect()->route('customer.senderid.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                            } catch (Exception $ex) {
                                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                    'status'  => 'error',
                                    'message' => $ex->getMessage(),
                                ]);
                            }


                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'info',
                                'message' => __('locale.sender_id.payment_cancelled'),
                            ]);
                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);
                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                case PaymentMethods::TYPE_CINETPAY:

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_CINETPAY)->first();

                    if ( ! $paymentMethod) {
                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);
                    }

                    $credentials = json_decode($paymentMethod->options);

                    $transaction_id = $request->get('transaction_id');


                    $payment_data = [
                        'apikey'         => $credentials->api_key,
                        'site_id'        => $credentials->site_id,
                        'transaction_id' => $transaction_id,
                    ];


                    try {

                        $curl = curl_init();

                        curl_setopt_array($curl, [
                            CURLOPT_URL            => $credentials->payment_url . '/check',
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
                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => 'Php curl show false value. Please contact with your provider',
                            ]);

                        }

                        if ($err) {
                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => $err,
                            ]);
                        }

                        $result = json_decode($response, true);


                        if (is_array($result) && array_key_exists('code', $result) && array_key_exists('message', $result)) {
                            if ($result['code'] == '00') {

                                $invoice = Invoices::create([
                                    'user_id'        => $senderid->user_id,
                                    'currency_id'    => $senderid->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $senderid->price,
                                    'type'           => Invoices::TYPE_SENDERID,
                                    'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                    'transaction_id' => $transaction_id,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    $current                   = Carbon::now();
                                    $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                    $senderid->status          = 'active';
                                    $senderid->payment_claimed = true;
                                    $senderid->save();

                                    $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                    return redirect()->route('customer.senderid.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => $result['message'],
                            ]);
                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'       => 'error',
                            'redirect_url' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    } catch (Exception $ex) {

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'       => 'error',
                            'redirect_url' => $ex->getMessage(),
                        ]);
                    }

                case PaymentMethods::TYPE_STRIPE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_STRIPE)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);
                        $secret_key  = $credentials->secret_key;
                        $session_id  = Session::get('session_id');

                        $stripe = new StripeClient($secret_key);

                        try {
                            $response = $stripe->checkout->sessions->retrieve($session_id);

                            if ($response->payment_status == 'paid') {

                                $invoice = Invoices::create([
                                    'user_id'        => $senderid->user_id,
                                    'currency_id'    => $senderid->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $senderid->price,
                                    'type'           => Invoices::TYPE_SENDERID,
                                    'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                    'transaction_id' => $response->payment_intent,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    $current                   = Carbon::now();
                                    $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                    $senderid->status          = 'active';
                                    $senderid->payment_claimed = true;
                                    $senderid->save();

                                    $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                    return redirect()->route('customer.senderid.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                        } catch (ApiErrorException $e) {
                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => $e->getMessage(),
                            ]);
                        }

                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);

                case PaymentMethods::TYPE_2CHECKOUT:
                case PaymentMethods::TYPE_PAYU:
                case PaymentMethods::TYPE_COINPAYMENTS:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', $payment_method)->first();

                    $invoice = Invoices::create([
                        'user_id'        => $senderid->user_id,
                        'currency_id'    => $senderid->currency_id,
                        'payment_method' => $paymentMethod->id,
                        'amount'         => $senderid->price,
                        'type'           => Invoices::TYPE_SENDERID,
                        'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                        'transaction_id' => $senderid->uid,
                        'status'         => Invoices::STATUS_PAID,
                    ]);

                    if ($invoice) {
                        $current                   = Carbon::now();
                        $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                        $senderid->status          = 'active';
                        $senderid->payment_claimed = true;
                        $senderid->save();

                        $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                        return redirect()->route('customer.senderid.index')->with([
                            'status'  => 'success',
                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                        ]);
                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_PAYNOW:
                    $pollurl = Session::get('paynow_poll_url');
                    if (isset($pollurl)) {
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYNOW)->first();

                        if ($paymentMethod) {
                            $credentials = json_decode($paymentMethod->options);

                            $paynow = new Paynow(
                                $credentials->integration_id,
                                $credentials->integration_key,
                                route('customer.callback.paynow'),
                                route('customer.senderid.payment_success', $senderid->uid)
                            );

                            try {
                                $response = $paynow->pollTransaction($pollurl);

                                if ($response->paid()) {
                                    $invoice = Invoices::create([
                                        'user_id'        => $senderid->user_id,
                                        'currency_id'    => $senderid->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $senderid->price,
                                        'type'           => Invoices::TYPE_SENDERID,
                                        'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                        'transaction_id' => $response->reference(),
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                   = Carbon::now();
                                        $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                        $senderid->status          = 'active';
                                        $senderid->payment_claimed = true;
                                        $senderid->save();

                                        $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                        return redirect()->route('customer.senderid.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                            } catch (Exception $ex) {
                                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                    'status'  => 'error',
                                    'message' => $ex->getMessage(),
                                ]);
                            }


                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'info',
                                'message' => __('locale.sender_id.payment_cancelled'),
                            ]);
                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);
                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                case PaymentMethods::TYPE_INSTAMOJO:
                    $payment_request_id = Session::get('payment_request_id');

                    if ($request->get('payment_request_id') == $payment_request_id) {
                        if ($request->get('payment_status') == 'Completed') {

                            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_INSTAMOJO)->first();

                            $invoice = Invoices::create([
                                'user_id'        => $senderid->user_id,
                                'currency_id'    => $senderid->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $senderid->price,
                                'type'           => Invoices::TYPE_SENDERID,
                                'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                'transaction_id' => $request->get('payment_id'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                   = Carbon::now();
                                $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                $senderid->status          = 'active';
                                $senderid->payment_claimed = true;
                                $senderid->save();

                                $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                return redirect()->route('customer.senderid.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'info',
                            'message' => $request->get('payment_status'),
                        ]);
                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'info',
                        'message' => __('locale.payment_gateways.payment_info_not_found'),
                    ]);

                case PaymentMethods::TYPE_PAYUMONEY:

                    $status      = $request->get('status');
                    $firstname   = $request->get('firstname');
                    $amount      = $request->get('amount');
                    $txnid       = $request->get('txnid');
                    $posted_hash = $request->get('hash');
                    $key         = $request->get('key');
                    $productinfo = $request->get('productinfo');
                    $email       = $request->get('email');
                    $salt        = "";

                    // Salt should be same Post Request
                    if (isset($request->additionalCharges)) {
                        $additionalCharges = $request->additionalCharges;
                        $retHashSeq        = $additionalCharges . '|' . $salt . '|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
                    } else {
                        $retHashSeq = $salt . '|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
                    }
                    $hash = hash("sha512", $retHashSeq);
                    if ($hash != $posted_hash) {
                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'info',
                            'message' => __('locale.exceptions.invalid_action'),
                        ]);
                    }

                    if ($status == 'Completed') {

                        $paymentMethod = PaymentMethods::where('status', true)->where('type', 'payumoney')->first();

                        $invoice = Invoices::create([
                            'user_id'        => $senderid->user_id,
                            'currency_id'    => $senderid->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $senderid->price,
                            'type'           => Invoices::TYPE_SENDERID,
                            'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                            'transaction_id' => $txnid,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current                   = Carbon::now();
                            $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                            $senderid->status          = 'active';
                            $senderid->payment_claimed = true;
                            $senderid->save();

                            $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                            return redirect()->route('customer.senderid.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => $status,
                    ]);

                case PaymentMethods::TYPE_DIRECTPAYONLINE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_DIRECTPAYONLINE)->first();

                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        if ($credentials->environment == 'production') {
                            $payment_url = 'https://secure.3gdirectpay.com';
                        } else {
                            $payment_url = 'https://secure1.sandbox.directpay.online';
                        }

                        $companyToken     = $credentials->company_token;
                        $TransactionToken = $request->get('TransactionToken');

                        $postXml = <<<POSTXML
<?xml version="1.0" encoding="utf-8"?>
        <API3G>
          <CompanyToken>$companyToken</CompanyToken>
          <Request>verifyToken</Request>
          <TransactionToken>$TransactionToken</TransactionToken>
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
                        curl_close($curl);

                        if ($response != '') {
                            $xml = new SimpleXMLElement($response);

                            // Check if token was created successfully
                            if ($xml->xpath('Result')[0] != '000') {
                                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                    'status'  => 'info',
                                    'message' => __('locale.exceptions.invalid_action'),
                                ]);
                            }

                            if (isset($request->TransID) && isset($request->CCDapproval)) {
                                $invoice_exist = Invoices::where('transaction_id', $request->TransID)->first();
                                if ( ! $invoice_exist) {
                                    $invoice = Invoices::create([
                                        'user_id'        => $senderid->user_id,
                                        'currency_id'    => $senderid->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $senderid->price,
                                        'type'           => Invoices::TYPE_SENDERID,
                                        'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                        'transaction_id' => $request->TransID,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                   = Carbon::now();
                                        $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                        $senderid->status          = 'active';
                                        $senderid->payment_claimed = true;
                                        $senderid->save();

                                        $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                        return redirect()->route('customer.senderid.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                        }
                    }
                    break;

                case PaymentMethods::TYPE_PAYGATEGLOBAL:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYGATEGLOBAL)->first();

                    $parameters = [
                        'auth_token' => $payment_method->api_key,
                        'identify'   => $request->get('identify'),
                    ];

                    try {

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, 'https://paygateglobal.com/api/v2/status');
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $response = curl_exec($ch);
                        curl_close($ch);

                        $get_response = json_decode($response, true);

                        if (isset($get_response) && is_array($get_response) && array_key_exists('status', $get_response)) {
                            if ($get_response['success'] == 0) {

                                $invoice = Invoices::create([
                                    'user_id'        => $senderid->user_id,
                                    'currency_id'    => $senderid->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $senderid->price,
                                    'type'           => Invoices::TYPE_SENDERID,
                                    'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                    'transaction_id' => $request->get('tx_reference'),
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    $current                   = Carbon::now();
                                    $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                    $senderid->status          = 'active';
                                    $senderid->payment_claimed = true;
                                    $senderid->save();

                                    $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                    return redirect()->route('customer.senderid.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }
                            }

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'info',
                                'message' => 'Waiting for administrator approval',
                            ]);
                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    } catch (Exception $e) {
                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => $e->getMessage(),
                        ]);
                    }

                case PaymentMethods::TYPE_ORANGEMONEY:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_ORANGEMONEY)->first();

                    if (isset($request->status)) {
                        if ($request->status == 'SUCCESS') {

                            $invoice = Invoices::create([
                                'user_id'        => $senderid->user_id,
                                'currency_id'    => $senderid->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $senderid->price,
                                'type'           => Invoices::TYPE_SENDERID,
                                'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                'transaction_id' => $request->get('txnid'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                   = Carbon::now();
                                $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                $senderid->status          = 'active';
                                $senderid->payment_claimed = true;
                                $senderid->save();

                                $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                return redirect()->route('customer.senderid.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }
                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'info',
                            'message' => $request->status,
                        ]);
                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_PAYHERELK:

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYHERELK)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        try {

                            if ($credentials->environment == 'sandbox') {
                                $auth_url    = 'https://sandbox.payhere.lk/merchant/v1/oauth/token';
                                $payment_url = 'https://sandbox.payhere.lk/merchant/v1/payment/search';
                            } else {
                                $auth_url    = 'https://payhere.lk/merchant/v1/oauth/token';
                                $payment_url = 'https://payhere.lk/merchant/v1/payment/search';
                            }

                            $headers = [
                                'Content-Type: application/x-www-form-urlencoded',
                                'Authorization: Basic ' . base64_encode("$credentials->app_id:$credentials->app_secret"),
                            ];

                            $ch = curl_init();

                            curl_setopt($ch, CURLOPT_URL, $auth_url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                            $auth_data = curl_exec($ch);

                            if (curl_errno($ch)) {

                                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                    'status'  => 'error',
                                    'message' => curl_error($ch),
                                ]);
                            }
                            curl_close($ch);

                            $result = json_decode($auth_data, true);

                            if (is_array($result)) {
                                if (array_key_exists('error_description', $result)) {

                                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                        'status'  => 'error',
                                        'message' => $result['error_description'],
                                    ]);
                                }


                                $headers = [
                                    'Content-Type: application/json',
                                    'Authorization: Bearer ' . $result['access_token'],
                                ];

                                $curl = curl_init();

                                curl_setopt($curl, CURLOPT_URL, $payment_url);
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($curl, CURLOPT_POST, 1);
                                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['order_id' => $request->get('order_id')]));
                                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

                                $payment_data = curl_exec($curl);

                                if (curl_errno($curl)) {

                                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                        'status'  => 'error',
                                        'message' => curl_error($curl),
                                    ]);
                                }

                                curl_close($curl);

                                $result = json_decode($payment_data, true);

                                if (is_array($result)) {
                                    if (array_key_exists('error_description', $result)) {

                                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                            'status'  => 'error',
                                            'message' => $result['error_description'],
                                        ]);
                                    }

                                    if (array_key_exists('status', $result) && $result['status'] == '-1') {
                                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                            'status'  => 'error',
                                            'message' => $result['msg'],
                                        ]);
                                    }


                                    $invoice = Invoices::create([
                                        'user_id'        => $senderid->user_id,
                                        'currency_id'    => $senderid->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $senderid->price,
                                        'type'           => Invoices::TYPE_SENDERID,
                                        'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                        'transaction_id' => $request->get('order_id'),
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                   = Carbon::now();
                                        $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                        $senderid->status          = 'active';
                                        $senderid->payment_claimed = true;
                                        $senderid->save();

                                        $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                        return redirect()->route('customer.senderid.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);
                                }

                                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        } catch (Exception $exception) {
                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }
                    }

                    break;

                case PaymentMethods::TYPE_MOLLIE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_MOLLIE)->first();

                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        $mollie = new MollieApiClient();
                        $mollie->setApiKey($credentials->api_key);

                        $payment_id = Session::get('payment_id');

                        $payment = $mollie->payments->get($payment_id);

                        if ($payment->isPaid()) {

                            $invoice_exist = Invoices::where('transaction_id', $payment_id)->first();
                            if ( ! $invoice_exist) {
                                $invoice = Invoices::create([
                                    'user_id'        => $senderid->user_id,
                                    'currency_id'    => $senderid->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $senderid->price,
                                    'type'           => Invoices::TYPE_SENDERID,
                                    'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                    'transaction_id' => $payment_id,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    $current                   = Carbon::now();
                                    $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                    $senderid->status          = 'active';
                                    $senderid->payment_claimed = true;
                                    $senderid->save();

                                    $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                    return redirect()->route('customer.senderid.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);

                case PaymentMethods::TYPE_SELCOMMOBILE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_SELCOMMOBILE)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        $orderStatusArray = [
                            "order_id" => $senderid->uid,
                        ];

                        $client = new Client($credentials->payment_url, $credentials->api_key, $credentials->api_secret);

                        // path relative to base url
                        $orderStatusPath = "/checkout/order-status";

                        // create order minimal
                        try {
                            $response = $client->getFunc($orderStatusPath, $orderStatusArray);

                            if (isset($response) && is_array($response) && array_key_exists('data', $response) && array_key_exists('result', $response)) {
                                if ($response['result'] == 'SUCCESS' && array_key_exists('0', $response['data']) && $response['data'][0]['payment_status'] == 'COMPLETED') {
                                    $invoice = Invoices::create([
                                        'user_id'        => $senderid->user_id,
                                        'currency_id'    => $senderid->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $senderid->price,
                                        'type'           => Invoices::TYPE_SENDERID,
                                        'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                        'transaction_id' => $response['data'][0]['transid'],
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                   = Carbon::now();
                                        $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                        $senderid->status          = 'active';
                                        $senderid->payment_claimed = true;
                                        $senderid->save();

                                        $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                        return redirect()->route('customer.senderid.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);
                                } else {
                                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                        'status'  => 'error',
                                        'message' => $response['message'],
                                    ]);
                                }
                            }

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => $response,
                            ]);

                        } catch (Exception $exception) {
                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }

                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_MPGS:

                    $order_id = $senderid->uid;

                    if (empty($order_id)) {
                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => 'Payment error: Invalid transaction.',
                        ]);
                    }

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_MPGS)->first();


                    if ( ! $paymentMethod) {
                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);

                    }

                    $credentials = json_decode($paymentMethod->options);

                    $config = [
                        'payment_url'             => $credentials->payment_url,
                        'api_version'             => $credentials->api_version,
                        'merchant_id'             => $credentials->merchant_id,
                        'authentication_password' => $credentials->authentication_password,
                    ];


                    $paymentData = [
                        'order_id' => $order_id,
                    ];

                    $mpgs   = new MPGS($config, $paymentData);
                    $result = $mpgs->process_response();

                    if (isset($result->getData()->status) && isset($result->getData()->message)) {
                        if ($result->getData()->status == 'success') {

                            $invoice = Invoices::create([
                                'user_id'        => $senderid->user_id,
                                'currency_id'    => $senderid->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $senderid->price,
                                'type'           => Invoices::TYPE_SENDERID,
                                'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                'transaction_id' => $result->getData()->transaction_id,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                   = Carbon::now();
                                $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                $senderid->status          = 'active';
                                $senderid->payment_claimed = true;
                                $senderid->save();

                                $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                return redirect()->route('customer.senderid.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }


                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => $result->getData()->message,
                        ]);
                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

            }

            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);

        }

        /**
         * successful Top up payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         * @throws Exception
         */
        public function successfulTopUpPayment(Request $request): RedirectResponse
        {

            $payment_method = Session::get('payment_method');
            if ($payment_method == null) {
                $payment_method = $request->get('payment_method');
            }

            $user_id = Session::get('user_id');
            if ($user_id == null) {
                $user_id = $request->input('user_id');
            }

            $price = Session::get('sms_unit');
            if ($price == null) {
                $price = $request->input('sms_unit');
            }

            $user = User::find($user_id);

            if ($user) {

                switch ($payment_method) {
                    case PaymentMethods::TYPE_PAYPAL:
                        $token = Session::get('paypal_payment_id');
                        if ($request->input('token') == $token) {
                            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYPAL)->first();

                            if ($paymentMethod) {
                                $credentials = json_decode($paymentMethod->options);

                                if ($credentials->environment == 'sandbox') {
                                    $environment = new SandboxEnvironment($credentials->client_id, $credentials->secret);
                                } else {
                                    $environment = new ProductionEnvironment($credentials->client_id, $credentials->secret);
                                }

                                $client = new PayPalHttpClient($environment);

                                $request = new OrdersCaptureRequest($token);
                                $request->prefer('return=representation');

                                try {
                                    // Call API with your client and get a response for your call
                                    $response = $client->execute($request);

                                    if ($response->statusCode == '201' && $response->result->status == 'COMPLETED' && isset($response->id)) {

                                        $invoice = Invoices::create([
                                            'user_id'        => $user->id,
                                            'currency_id'    => $user->customer->subscription->plan->currency->id,
                                            'payment_method' => $paymentMethod->id,
                                            'amount'         => $price,
                                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                                            'description'    => 'Payment for sms top up',
                                            'transaction_id' => $response->id,
                                            'status'         => Invoices::STATUS_PAID,
                                        ]);

                                        if ($invoice) {

                                            if ($user->sms_unit != '-1') {
                                                $user->sms_unit += $price;
                                                $user->save();
                                            }


                                            $subscription = $user->customer->activeSubscription();

                                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                                'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                                'title'  => 'Add ' . $price . ' sms units',
                                                'amount' => $price . ' sms units',
                                            ]);

                                            return redirect()->route('user.home')->with([
                                                'status'  => 'success',
                                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                                            ]);
                                        }

                                        return redirect()->route('user.home')->with([
                                            'status'  => 'error',
                                            'message' => __('locale.exceptions.something_went_wrong'),
                                        ]);

                                    }

                                } catch (Exception $ex) {
                                    return redirect()->route('user.home')->with([
                                        'status'  => 'error',
                                        'message' => $ex->getMessage(),
                                    ]);
                                }


                                return redirect()->route('user.home')->with([
                                    'status'  => 'info',
                                    'message' => __('locale.sender_id.payment_cancelled'),
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => __('locale.payment_gateways.not_found'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.invalid_action'),
                        ]);

                    case PaymentMethods::TYPE_STRIPE:
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_STRIPE)->first();
                        if ($paymentMethod) {
                            $credentials = json_decode($paymentMethod->options);
                            $secret_key  = $credentials->secret_key;
                            $session_id  = Session::get('session_id');

                            $stripe = new StripeClient($secret_key);

                            try {
                                $response = $stripe->checkout->sessions->retrieve($session_id);

                                $price = $response->amount_total / 100;

                                if ($response->payment_status == 'paid') {

                                    $invoice = Invoices::create([
                                        'user_id'        => $user->id,
                                        'currency_id'    => $user->customer->subscription->plan->currency->id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $price,
                                        'type'           => Invoices::TYPE_SUBSCRIPTION,
                                        'description'    => 'Payment for sms top up',
                                        'transaction_id' => $response->id,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {

                                        if ($user->sms_unit != '-1') {
                                            $user->sms_unit += $price;
                                            $user->save();
                                        }

                                        $subscription = $user->customer->activeSubscription();

                                        $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                            'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                            'title'  => 'Add ' . $price . ' sms units',
                                            'amount' => $price . ' sms units',
                                        ]);

                                        return redirect()->route('user.home')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                            } catch (ApiErrorException $e) {
                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => $e->getMessage(),
                                ]);
                            }

                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);

                    case PaymentMethods::TYPE_2CHECKOUT:
                    case PaymentMethods::TYPE_PAYU:
                    case PaymentMethods::TYPE_COINPAYMENTS:
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', $payment_method)->first();

                        if ($paymentMethod) {

                            $invoice = Invoices::create([
                                'user_id'        => $user->id,
                                'currency_id'    => $user->customer->subscription->plan->currency->id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $price,
                                'type'           => Invoices::TYPE_SUBSCRIPTION,
                                'description'    => 'Payment for sms top up',
                                'transaction_id' => $user->id . '_' . $price,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {

                                if ($user->sms_unit != '-1') {
                                    $user->sms_unit += $price;
                                    $user->save();
                                }

                                $subscription = $user->customer->activeSubscription();

                                $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                    'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                    'title'  => 'Add ' . $price . ' sms units',
                                    'amount' => $price . ' sms units',
                                ]);

                                return redirect()->route('user.home')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    case PaymentMethods::TYPE_PAYGATEGLOBAL:
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYGATEGLOBAL)->first();

                        if ($paymentMethod) {

                            $parameters = [
                                'auth_token' => $paymentMethod->api_key,
                                'identify'   => $request->get('identify'),
                            ];

                            try {

                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, 'https://paygateglobal.com/api/v2/status');
                                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                $response = curl_exec($ch);
                                curl_close($ch);

                                $get_response = json_decode($response, true);

                                if (isset($get_response) && is_array($get_response) && array_key_exists('status', $get_response)) {
                                    if ($get_response['success'] == 0) {

                                        $price = Session::get('price');

                                        $invoice = Invoices::create([
                                            'user_id'        => $user->id,
                                            'currency_id'    => $user->customer->subscription->plan->currency->id,
                                            'payment_method' => $paymentMethod->id,
                                            'amount'         => $price,
                                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                                            'description'    => 'Payment for sms top up',
                                            'transaction_id' => $request->tx_reference,
                                            'status'         => Invoices::STATUS_PAID,
                                        ]);

                                        if ($invoice) {

                                            if ($user->sms_unit != '-1') {
                                                $user->sms_unit += $price;
                                                $user->save();
                                            }

                                            $subscription = $user->customer->activeSubscription();

                                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                                'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                                'title'  => 'Add ' . $price . ' sms units',
                                                'amount' => $price . ' sms units',
                                            ]);

                                            return redirect()->route('user.home')->with([
                                                'status'  => 'success',
                                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                                            ]);

                                        }

                                        return redirect()->route('user.home')->with([
                                            'status'  => 'error',
                                            'message' => __('locale.exceptions.something_went_wrong'),
                                        ]);
                                    }

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'info',
                                        'message' => 'Waiting for administrator approval',
                                    ]);
                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            } catch (Exception $e) {
                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => $e->getMessage(),
                                ]);
                            }
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    case PaymentMethods::TYPE_PAYNOW:
                        $pollurl = Session::get('paynow_poll_url');
                        if (isset($pollurl)) {
                            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYNOW)->first();

                            if ($paymentMethod) {
                                $credentials = json_decode($paymentMethod->options);

                                $paynow = new Paynow(
                                    $credentials->integration_id,
                                    $credentials->integration_key,
                                    route('customer.callback.paynow'),
                                    route('customer.top_up.payment_success', ['user_id' => $user->id, 'sms_unit' => $price])
                                );

                                try {
                                    $response = $paynow->pollTransaction($pollurl);

                                    if ($response->paid()) {

                                        $invoice = Invoices::create([
                                            'user_id'        => $user->id,
                                            'currency_id'    => $user->customer->subscription->plan->currency->id,
                                            'payment_method' => $paymentMethod->id,
                                            'amount'         => $price,
                                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                                            'description'    => 'Payment for sms top up',
                                            'transaction_id' => $response->reference(),
                                            'status'         => Invoices::STATUS_PAID,
                                        ]);

                                        if ($invoice) {

                                            if ($user->sms_unit != '-1') {
                                                $user->sms_unit += $price;
                                                $user->save();
                                            }

                                            $subscription = $user->customer->activeSubscription();

                                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                                'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                                'title'  => 'Add ' . $price . ' sms units',
                                                'amount' => $price . ' sms units',
                                            ]);


                                            return redirect()->route('user.home')->with([
                                                'status'  => 'success',
                                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                                            ]);
                                        }

                                        return redirect()->route('user.home')->with([
                                            'status'  => 'error',
                                            'message' => __('locale.exceptions.something_went_wrong'),
                                        ]);

                                    }

                                } catch (Exception $ex) {
                                    return redirect()->route('user.home')->with([
                                        'status'  => 'error',
                                        'message' => $ex->getMessage(),
                                    ]);
                                }


                                return redirect()->route('user.home')->with([
                                    'status'  => 'info',
                                    'message' => __('locale.sender_id.payment_cancelled'),
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => __('locale.payment_gateways.not_found'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.invalid_action'),
                        ]);

                    case PaymentMethods::TYPE_INSTAMOJO:
                        $payment_request_id = Session::get('payment_request_id');

                        if ($request->get('payment_request_id') == $payment_request_id) {
                            if ($request->get('payment_status') == 'Completed') {

                                $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_INSTAMOJO)->first();

                                $invoice = Invoices::create([
                                    'user_id'        => $user->id,
                                    'currency_id'    => $user->customer->subscription->plan->currency->id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $price,
                                    'type'           => Invoices::TYPE_SUBSCRIPTION,
                                    'description'    => 'Payment for sms top up',
                                    'transaction_id' => $request->get('payment_id'),
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {

                                    if ($user->sms_unit != '-1') {
                                        $user->sms_unit += $price;
                                        $user->save();
                                    }
                                    $subscription = $user->customer->activeSubscription();

                                    $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                        'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                        'title'  => 'Add ' . $price . ' sms units',
                                        'amount' => $price . ' sms units',
                                    ]);

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'info',
                                'message' => $request->get('payment_status'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'info',
                            'message' => __('locale.payment_gateways.payment_info_not_found'),
                        ]);

                    case PaymentMethods::TYPE_PAYUMONEY:

                        $status      = $request->status;
                        $firstname   = $request->firstname;
                        $amount      = $request->amount;
                        $txnid       = $request->get('txnid');
                        $posted_hash = $request->hash;
                        $key         = $request->key;
                        $productinfo = $request->productinfo;
                        $email       = $request->email;
                        $salt        = "";

                        // Salt should be same Post Request
                        if (isset($request->additionalCharges)) {
                            $additionalCharges = $request->additionalCharges;
                            $retHashSeq        = $additionalCharges . '|' . $salt . '|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
                        } else {
                            $retHashSeq = $salt . '|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
                        }
                        $hash = hash("sha512", $retHashSeq);
                        if ($hash != $posted_hash) {
                            return redirect()->route('user.home')->with([
                                'status'  => 'info',
                                'message' => __('locale.exceptions.invalid_action'),
                            ]);
                        }

                        if ($status == 'Completed') {

                            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'payumoney')->first();

                            $invoice = Invoices::create([
                                'user_id'        => $user->id,
                                'currency_id'    => $user->customer->subscription->plan->currency->id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $price,
                                'type'           => Invoices::TYPE_SUBSCRIPTION,
                                'description'    => 'Payment for sms top up',
                                'transaction_id' => $txnid,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {

                                if ($user->sms_unit != '-1') {
                                    $user->sms_unit += $price;
                                    $user->save();
                                }
                                $subscription = $user->customer->activeSubscription();

                                $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                    'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                    'title'  => 'Add ' . $price . ' sms units',
                                    'amount' => $price . ' sms units',
                                ]);

                                return redirect()->route('user.home')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => $status,
                        ]);

                    case PaymentMethods::TYPE_DIRECTPAYONLINE:
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_DIRECTPAYONLINE)->first();

                        if ($paymentMethod) {
                            $credentials = json_decode($paymentMethod->options);

                            if ($credentials->environment == 'production') {
                                $payment_url = 'https://secure.3gdirectpay.com';
                            } else {
                                $payment_url = 'https://secure1.sandbox.directpay.online';
                            }

                            $companyToken     = $credentials->company_token;
                            $TransactionToken = $request->get('TransactionToken');

                            $postXml = <<<POSTXML
<?xml version="1.0" encoding="utf-8"?>
        <API3G>
          <CompanyToken>$companyToken</CompanyToken>
          <Request>verifyToken</Request>
          <TransactionToken>$TransactionToken</TransactionToken>
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
                            curl_close($curl);

                            if ($response != '') {
                                $xml = new SimpleXMLElement($response);

                                // Check if token was created successfully
                                if ($xml->xpath('Result')[0] != '000') {
                                    return redirect()->route('user.home')->with([
                                        'status'  => 'info',
                                        'message' => __('locale.exceptions.invalid_action'),
                                    ]);
                                }

                                if (isset($request->TransID) && isset($request->CCDapproval)) {
                                    $invoice_exist = Invoices::where('transaction_id', $request->TransID)->first();
                                    if ( ! $invoice_exist) {
                                        $invoice = Invoices::create([
                                            'user_id'        => $user->id,
                                            'currency_id'    => $user->customer->subscription->plan->currency->id,
                                            'payment_method' => $paymentMethod->id,
                                            'amount'         => $price,
                                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                                            'description'    => 'Payment for sms top up',
                                            'transaction_id' => $request->TransID,
                                            'status'         => Invoices::STATUS_PAID,
                                        ]);

                                        if ($invoice) {

                                            if ($user->sms_unit != '-1') {
                                                $user->sms_unit += $price;
                                                $user->save();
                                            }
                                            $subscription = $user->customer->activeSubscription();

                                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                                'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                                'title'  => 'Add ' . $price . ' sms units',
                                                'amount' => $price . ' sms units',
                                            ]);

                                            return redirect()->route('user.home')->with([
                                                'status'  => 'success',
                                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                                            ]);
                                        }

                                        return redirect()->route('user.home')->with([
                                            'status'  => 'error',
                                            'message' => __('locale.exceptions.something_went_wrong'),
                                        ]);

                                    }

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                            }
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    case PaymentMethods::TYPE_ORANGEMONEY:
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_ORANGEMONEY)->first();

                        if (isset($request->status)) {
                            if ($request->status == 'SUCCESS') {

                                $invoice = Invoices::create([
                                    'user_id'        => $user->id,
                                    'currency_id'    => $user->customer->subscription->plan->currency->id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $price,
                                    'type'           => Invoices::TYPE_SUBSCRIPTION,
                                    'description'    => 'Payment for sms top up',
                                    'transaction_id' => $request->get('txnid'),
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {

                                    if ($user->sms_unit != '-1') {
                                        $user->sms_unit += $price;
                                        $user->save();
                                    }


                                    $subscription = $user->customer->activeSubscription();

                                    $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                        'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                        'title'  => 'Add ' . $price . ' sms units',
                                        'amount' => $price . ' sms units',
                                    ]);

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'info',
                                'message' => $request->status,
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    case PaymentMethods::TYPE_CINETPAY:

                        $paymentMethod  = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_CINETPAY)->first();
                        $transaction_id = $request->transaction_id;

                        if ( ! $paymentMethod) {
                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => __('locale.payment_gateways.not_found'),
                            ]);
                        }


                        $credentials = json_decode($paymentMethod->options);

                        $payment_data = [
                            'apikey'         => $credentials->api_key,
                            'site_id'        => $credentials->site_id,
                            'transaction_id' => $transaction_id,
                        ];


                        try {

                            $curl = curl_init();

                            curl_setopt_array($curl, [
                                CURLOPT_URL            => $credentials->payment_url . '/check',
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
                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => 'Php curl show false value. Please contact with your provider',
                                ]);

                            }

                            if ($err) {
                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => $err,
                                ]);
                            }

                            $result = json_decode($response, true);


                            if (is_array($result) && array_key_exists('code', $result) && array_key_exists('message', $result)) {
                                if ($result['code'] == '00') {

                                    $invoice = Invoices::create([
                                        'user_id'        => $user->id,
                                        'currency_id'    => $user->customer->subscription->plan->currency->id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $price,
                                        'type'           => Invoices::TYPE_SUBSCRIPTION,
                                        'description'    => 'Payment for sms top up',
                                        'transaction_id' => $transaction_id,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {

                                        if ($user->sms_unit != '-1') {
                                            $user->sms_unit += $price;
                                            $user->save();
                                        }


                                        $subscription = $user->customer->activeSubscription();

                                        $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                            'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                            'title'  => 'Add ' . $price . ' sms units',
                                            'amount' => $price . ' sms units',
                                        ]);

                                        return redirect()->route('user.home')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => $result['message'],
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'       => 'error',
                                'redirect_url' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        } catch (Exception $ex) {

                            return redirect()->route('user.home')->with([
                                'status'       => 'error',
                                'redirect_url' => $ex->getMessage(),
                            ]);
                        }

                    case PaymentMethods::TYPE_PAYHERELK:
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYHERELK)->first();
                        if ($paymentMethod) {
                            $credentials = json_decode($paymentMethod->options);

                            try {

                                if ($credentials->environment == 'sandbox') {
                                    $auth_url    = 'https://sandbox.payhere.lk/merchant/v1/oauth/token';
                                    $payment_url = 'https://sandbox.payhere.lk/merchant/v1/payment/search';
                                } else {
                                    $auth_url    = 'https://payhere.lk/merchant/v1/oauth/token';
                                    $payment_url = 'https://payhere.lk/merchant/v1/payment/search';
                                }

                                $headers = [
                                    'Content-Type: application/x-www-form-urlencoded',
                                    'Authorization: Basic ' . base64_encode("$credentials->app_id:$credentials->app_secret"),
                                ];

                                $ch = curl_init();

                                curl_setopt($ch, CURLOPT_URL, $auth_url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
                                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                                $auth_data = curl_exec($ch);

                                if (curl_errno($ch)) {

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'error',
                                        'message' => curl_error($ch),
                                    ]);
                                }


                                $result = json_decode($auth_data, true);
                                curl_close($ch);
                                if (is_array($result)) {
                                    if (array_key_exists('error_description', $result)) {

                                        return redirect()->route('user.home')->with([
                                            'status'  => 'error',
                                            'message' => $result['error_description'],
                                        ]);
                                    }


                                    $headers = [
                                        'Content-Type: application/json',
                                        'Authorization: Bearer ' . $result['access_token'],
                                    ];

                                    $curl = curl_init();

                                    curl_setopt($curl, CURLOPT_URL, $payment_url);
                                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($curl, CURLOPT_POST, 1);
                                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['order_id' => $request->get('order_id')]));
                                    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

                                    $payment_data = curl_exec($curl);

                                    if (curl_errno($curl)) {

                                        return redirect()->route('user.home')->with([
                                            'status'  => 'error',
                                            'message' => curl_error($curl),
                                        ]);
                                    }


                                    $result = json_decode($payment_data, true);

                                    curl_close($curl);

                                    if (is_array($result)) {
                                        if (array_key_exists('error_description', $result)) {

                                            return redirect()->route('user.home')->with([
                                                'status'  => 'error',
                                                'message' => $result['error_description'],
                                            ]);
                                        }

                                        if (array_key_exists('status', $result) && $result['status'] == '-1') {
                                            return redirect()->route('user.home')->with([
                                                'status'  => 'error',
                                                'message' => $result['msg'],
                                            ]);
                                        }

                                        $invoice = Invoices::create([
                                            'user_id'        => $user->id,
                                            'currency_id'    => $user->customer->subscription->plan->currency->id,
                                            'payment_method' => $paymentMethod->id,
                                            'amount'         => $price,
                                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                                            'description'    => 'Payment for sms top up',
                                            'transaction_id' => $request->get('order_id'),
                                            'status'         => Invoices::STATUS_PAID,
                                        ]);

                                        if ($invoice) {

                                            if ($user->sms_unit != '-1') {
                                                $user->sms_unit += $price;
                                                $user->save();
                                            }
                                            $subscription = $user->customer->activeSubscription();

                                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                                'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                                'title'  => 'Add ' . $price . ' sms units',
                                                'amount' => $price . ' sms units',
                                            ]);

                                            return redirect()->route('user.home')->with([
                                                'status'  => 'success',
                                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                                            ]);
                                        }

                                        return redirect()->route('user.home')->with([
                                            'status'  => 'error',
                                            'message' => __('locale.exceptions.something_went_wrong'),
                                        ]);

                                    }

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            } catch (Exception $exception) {
                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => $exception->getMessage(),
                                ]);
                            }
                        }
                        break;

                    case PaymentMethods::TYPE_MOLLIE:

                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_MOLLIE)->first();

                        if ($paymentMethod) {
                            $credentials = json_decode($paymentMethod->options);

                            $mollie = new MollieApiClient();
                            $mollie->setApiKey($credentials->api_key);

                            $payment_id = Session::get('payment_id');

                            $payment = $mollie->payments->get($payment_id);

                            if ($payment->isPaid()) {

                                $invoice_exist = Invoices::where('transaction_id', $payment_id)->first();
                                if ( ! $invoice_exist) {
                                    $invoice = Invoices::create([
                                        'user_id'        => $user->id,
                                        'currency_id'    => $user->customer->subscription->plan->currency->id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $price,
                                        'type'           => Invoices::TYPE_SUBSCRIPTION,
                                        'description'    => 'Payment for sms top up',
                                        'transaction_id' => $payment_id,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {

                                        if ($user->sms_unit != '-1') {
                                            $user->sms_unit += $price;
                                            $user->save();
                                        }
                                        $subscription = $user->customer->activeSubscription();

                                        $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                            'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                            'title'  => 'Add ' . $price . ' sms units',
                                            'amount' => $price . ' sms units',
                                        ]);

                                        return redirect()->route('user.home')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);

                    case PaymentMethods::TYPE_SELCOMMOBILE:
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', $payment_method)->first();
                        $order_id      = Session::get('order_id');

                        if ($paymentMethod && $order_id != null) {
                            $credentials = json_decode($paymentMethod->options);

                            $orderStatusArray = [
                                "order_id" => $order_id,
                            ];

                            $client = new Client($credentials->payment_url, $credentials->api_key, $credentials->api_secret);

                            // path relative to base url
                            $orderStatusPath = "/checkout/order-status";

                            // create order minimal
                            try {
                                $response = $client->getFunc($orderStatusPath, $orderStatusArray);

                                if (isset($response) && is_array($response) && array_key_exists('data', $response) && array_key_exists('result', $response)) {
                                    if ($response['result'] == 'SUCCESS' && array_key_exists('0', $response['data']) && $response['data'][0]['payment_status'] == 'COMPLETED') {
                                        $invoice = Invoices::create([
                                            'user_id'        => $user->id,
                                            'currency_id'    => $user->customer->subscription->plan->currency->id,
                                            'payment_method' => $paymentMethod->id,
                                            'amount'         => $price,
                                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                                            'description'    => 'Payment for sms top up',
                                            'transaction_id' => $response['data'][0]['transid'],
                                            'status'         => Invoices::STATUS_PAID,
                                        ]);

                                        if ($invoice) {

                                            if ($user->sms_unit != '-1') {
                                                $user->sms_unit += $price;
                                                $user->save();
                                            }

                                            $subscription = $user->customer->activeSubscription();

                                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                                'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                                'title'  => 'Add ' . $price . ' sms units',
                                                'amount' => $price . ' sms units',
                                            ]);

                                            return redirect()->route('user.home')->with([
                                                'status'  => 'success',
                                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                                            ]);
                                        }

                                        return redirect()->route('user.home')->with([
                                            'status'  => 'error',
                                            'message' => __('locale.exceptions.something_went_wrong'),
                                        ]);
                                    } else {
                                        return redirect()->route('user.home')->with([
                                            'status'  => 'error',
                                            'message' => $response['message'],
                                        ]);
                                    }
                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => $response,
                                ]);

                            } catch (Exception $exception) {
                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => $exception->getMessage(),
                                ]);
                            }

                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    case PaymentMethods::TYPE_MPGS:

                        $order_id = $request->input('order_id');

                        if (empty($order_id)) {
                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => 'Payment error: Invalid transaction.',
                            ]);
                        }

                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_MPGS)->first();


                        if ( ! $paymentMethod) {
                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => __('locale.payment_gateways.not_found'),
                            ]);

                        }

                        $credentials = json_decode($paymentMethod->options);

                        $config = [
                            'payment_url'             => $credentials->payment_url,
                            'api_version'             => $credentials->api_version,
                            'merchant_id'             => $credentials->merchant_id,
                            'authentication_password' => $credentials->authentication_password,
                        ];


                        $paymentData = [
                            'user_id'  => Session::get('user_id'),
                            'sms_unit' => Session::get('sms_unit'),
                            'order_id' => $order_id,
                        ];

                        $mpgs   = new MPGS($config, $paymentData);
                        $result = $mpgs->process_response();

                        if (isset($result->getData()->status) && isset($result->getData()->message)) {
                            if ($result->getData()->status == 'success') {

                                $invoice = Invoices::create([
                                    'user_id'        => $user->id,
                                    'currency_id'    => $user->customer->subscription->plan->currency->id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $price,
                                    'type'           => Invoices::TYPE_SUBSCRIPTION,
                                    'description'    => 'Payment for sms top up',
                                    'transaction_id' => $result->getData()->transaction_id,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {

                                    if ($user->sms_unit != '-1') {
                                        $user->sms_unit += $price;
                                        $user->save();
                                    }

                                    $subscription = $user->customer->activeSubscription();

                                    $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                        'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                        'title'  => 'Add ' . $price . ' sms units',
                                        'amount' => $price . ' sms units',
                                    ]);

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }


                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => $result->getData()->message,
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => __('locale.payment_gateways.not_found'),
                ]);
            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.auth.user_not_exist'),
            ]);

        }


        /**
         * cancel payment
         *
         * @param Senderid $senderid
         * @param Request  $request
         *
         * @return RedirectResponse
         */
        public function cancelledSenderIDPayment(Senderid $senderid, Request $request): RedirectResponse
        {

            $payment_method = Session::get('payment_method');

            switch ($payment_method) {
                case PaymentMethods::TYPE_PAYPAL:

                    $token = Session::get('paypal_payment_id');
                    if ($request->get('token') == $token) {
                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'info',
                            'message' => __('locale.sender_id.payment_cancelled'),
                        ]);
                    }
                    break;

                case PaymentMethods::TYPE_STRIPE:
                case PaymentMethods::TYPE_PAYU:
                case PaymentMethods::TYPE_COINPAYMENTS:
                case PaymentMethods::TYPE_PAYUMONEY:
                case PaymentMethods::TYPE_PAYHERELK:
                case PaymentMethods::TYPE_MPGS:
                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'info',
                        'message' => __('locale.sender_id.payment_cancelled'),
                    ]);
            }


            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                'status'  => 'info',
                'message' => __('locale.sender_id.payment_cancelled'),
            ]);

        }

        /**
         * cancel payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function cancelledTopUpPayment(Request $request): RedirectResponse
        {

            $payment_method = Session::get('payment_method');

            switch ($payment_method) {
                case PaymentMethods::TYPE_PAYPAL:

                    $token = Session::get('paypal_payment_id');
                    if ($request->get('token') == $token) {
                        return redirect()->route('user.home')->with([
                            'status'  => 'info',
                            'message' => __('locale.sender_id.payment_cancelled'),
                        ]);
                    }
                    break;

                case PaymentMethods::TYPE_STRIPE:
                case PaymentMethods::TYPE_PAYU:
                case PaymentMethods::TYPE_COINPAYMENTS:
                case PaymentMethods::TYPE_PAYUMONEY:
                case PaymentMethods::TYPE_DIRECTPAYONLINE:
                case PaymentMethods::TYPE_PAYHERELK:
                case PaymentMethods::TYPE_SELCOMMOBILE:
                case PaymentMethods::TYPE_MPGS:
                    return redirect()->route('user.home')->with([
                        'status'  => 'info',
                        'message' => __('locale.sender_id.payment_cancelled'),
                    ]);
            }

            return redirect()->route('user.home')->with([
                'status'  => 'info',
                'message' => __('locale.sender_id.payment_cancelled'),
            ]);

        }

        /**
         * purchase sender id by braintree
         *
         * @param Senderid $senderid
         * @param Request  $request
         *
         * @return RedirectResponse
         */
        public function braintreeSenderID(Senderid $senderid, Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'braintree')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {
                    $gateway = new Gateway([
                        'environment' => $credentials->environment,
                        'merchantId'  => $credentials->merchant_id,
                        'publicKey'   => $credentials->public_key,
                        'privateKey'  => $credentials->private_key,
                    ]);

                    $result = $gateway->transaction()->sale([
                        'amount'             => $senderid->price,
                        'paymentMethodNonce' => $request->get('payment_method_nonce'),
                        'deviceData'         => $request->get('device_data'),
                        'options'            => [
                            'submitForSettlement' => true,
                        ],
                    ]);

                    if ($result->success && isset($result->transaction->id)) {
                        $invoice = Invoices::create([
                            'user_id'        => $senderid->user_id,
                            'currency_id'    => $senderid->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $senderid->price,
                            'type'           => Invoices::TYPE_SENDERID,
                            'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                            'transaction_id' => $result->transaction->id,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current                   = Carbon::now();
                            $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                            $senderid->status          = 'active';
                            $senderid->payment_claimed = true;
                            $senderid->save();

                            $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                            return redirect()->route('customer.senderid.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => $result->message,
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * top up by braintree
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function braintreeTopUp(Request $request): RedirectResponse
        {

            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'braintree')->first();
            $user          = User::find($request->get('user_id'));

            if ($paymentMethod && $user) {

                $credentials = json_decode($paymentMethod->options);

                try {
                    $gateway = new Gateway([
                        'environment' => $credentials->environment,
                        'merchantId'  => $credentials->merchant_id,
                        'publicKey'   => $credentials->public_key,
                        'privateKey'  => $credentials->private_key,
                    ]);

                    $result = $gateway->transaction()->sale([
                        'amount'             => $request->get('sms_unit'),
                        'paymentMethodNonce' => $request->get('payment_method_nonce'),
                        'deviceData'         => $request->get('device_data'),
                        'options'            => [
                            'submitForSettlement' => true,
                        ],
                    ]);

                    if ($result->success && isset($result->transaction->id)) {

                        $invoice = Invoices::create([
                            'user_id'        => $user->id,
                            'currency_id'    => $user->customer->subscription->plan->currency->id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $request->get('sms_unit'),
                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                            'description'    => 'Payment for sms top up',
                            'transaction_id' => $result->transaction->id,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {

                            if ($user->sms_unit != '-1') {
                                $user->sms_unit += $request->get('sms_unit');
                                $user->save();
                            }

                            $subscription = $user->customer->activeSubscription();

                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                'title'  => 'Add ' . $request->get('sms_unit') . ' sms units',
                                'amount' => $request->get('sms_unit') . ' sms units',
                            ]);

                            return redirect()->route('user.home')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => $result->message,
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * paystack callback
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function paystack(Request $request): RedirectResponse
        {

            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'paystack')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);


                $curl      = curl_init();
                $reference = $request->reference;
                if ( ! $reference) {
                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => 'Reference code not found',
                    ]);
                }

                curl_setopt_array($curl, [
                    CURLOPT_URL            => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => [
                        "accept: application/json",
                        "authorization: Bearer " . $credentials->secret_key,
                        "cache-control: no-cache",
                    ],
                ]);

                $response = curl_exec($curl);
                $err      = curl_error($curl);

                curl_close($curl);

                if ($response === false) {
                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => 'Php curl show false value. Please contact with your provider',
                    ]);
                }

                if ($err) {
                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => $err,
                    ]);
                }

                $tranx = json_decode($response);

                if ( ! $tranx->status) {
                    // there was an error from the API
                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => $tranx->message,
                    ]);
                }

                if ('success' == $tranx->data->status) {

                    $request_type = $tranx->data->metadata->request_type;

                    if ($request_type == 'senderid_payment') {

                        $senderid = Senderid::findByUid($tranx->data->metadata->sender_id);

                        $invoice = Invoices::create([
                            'user_id'        => $senderid->user_id,
                            'currency_id'    => $senderid->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $senderid->price,
                            'type'           => Invoices::TYPE_SENDERID,
                            'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                            'transaction_id' => $reference,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current                   = Carbon::now();
                            $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                            $senderid->status          = 'active';
                            $senderid->payment_claimed = true;
                            $senderid->save();

                            $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                            return redirect()->route('customer.senderid.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                    if ($request_type == 'top_up_payment') {

                        $user = User::find($tranx->data->metadata->user_id);

                        $invoice = Invoices::create([
                            'user_id'        => $user->id,
                            'currency_id'    => $user->customer->subscription->plan->currency->id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $user->customer->subscription->plan->getOption('per_unit_price') * $tranx->data->metadata->sms_unit,
                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                            'description'    => 'Payment for sms top up',
                            'transaction_id' => $reference,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {

                            if ($user->sms_unit != '-1') {
                                $user->sms_unit += $tranx->data->metadata->sms_unit;
                                $user->save();
                            }
                            $subscription = $user->customer->activeSubscription();

                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                'title'  => 'Add ' . $tranx->data->metadata->sms_unit . ' sms units',
                                'amount' => $tranx->data->metadata->sms_unit . ' sms units',
                            ]);

                            return redirect()->route('user.home')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                    if ($request_type == 'number_payment') {

                        $number = PhoneNumbers::findByUid($tranx->data->metadata->number_id);

                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $number->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $number->price,
                            'type'           => Invoices::TYPE_NUMBERS,
                            'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                            'transaction_id' => $reference,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current               = Carbon::now();
                            $number->user_id       = auth()->user()->id;
                            $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                            $number->status        = 'assigned';
                            $number->save();

                            $this->createNotification('number', $number->number, auth()->user()->displayName());

                            return redirect()->route('customer.numbers.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                    if ($request_type == 'keyword_payment') {

                        $keyword = Keywords::findByUid($tranx->data->metadata->keyword_id);

                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $keyword->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $keyword->price,
                            'type'           => Invoices::TYPE_KEYWORD,
                            'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                            'transaction_id' => $reference,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current                = Carbon::now();
                            $keyword->user_id       = auth()->user()->id;
                            $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                            $keyword->status        = 'assigned';
                            $keyword->save();

                            $this->createNotification('keyword', $keyword->keyword_name, auth()->user()->displayName());

                            return redirect()->route('customer.keywords.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                    if ($request_type == 'subscription_payment') {

                        $plan = Plan::where('uid', $tranx->data->metadata->plan_id)->first();

                        if ($plan) {
                            $invoice = Invoices::create([
                                'user_id'        => $tranx->data->metadata->user_id,
                                'currency_id'    => $plan->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $plan->price,
                                'type'           => Invoices::TYPE_SUBSCRIPTION,
                                'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                'transaction_id' => $reference,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                if (Auth::user()->customer->activeSubscription()) {
                                    Auth::user()->customer->activeSubscription()->cancelNow();
                                }

                                if (Auth::user()->customer->subscription) {
                                    $subscription          = Auth::user()->customer->subscription;
                                    $get_options           = json_decode($subscription->options, true);
                                    $output                = array_replace($get_options, [
                                        'send_warning' => false,
                                    ]);
                                    $subscription->options = json_encode($output);

                                } else {
                                    $subscription           = new Subscription();
                                    $subscription->user_id  = Auth::user()->id;
                                    $subscription->start_at = Carbon::now();
                                }

                                $subscription->status                 = Subscription::STATUS_ACTIVE;
                                $subscription->plan_id                = $plan->getBillableId();
                                $subscription->end_period_last_days   = '10';
                                $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                $subscription->end_at                 = null;
                                $subscription->end_by                 = null;
                                $subscription->payment_method_id      = $paymentMethod->id;
                                $subscription->save();

                                // add transaction
                                $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                    'end_at'                 => $subscription->end_at,
                                    'current_period_ends_at' => $subscription->current_period_ends_at,
                                    'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                    'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                    'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                ]);

                                // add log
                                $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                    'plan'  => $subscription->plan->getBillableName(),
                                    'price' => $subscription->plan->getBillableFormattedPrice(),
                                ]);


                                $user = User::find($tranx->data->metadata->user_id);

                                if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                } else {
                                    if ($plan->getOption('add_previous_balance') == 'yes') {
                                        $user->sms_unit += $plan->getOption('sms_max');
                                    } else {
                                        $user->sms_unit = $plan->getOption('sms_max');
                                    }
                                }

                                $user->save();

                                $this->createNotification('plan', $plan->name, $user->displayName());

                                return redirect()->route('customer.subscriptions.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
                ]);
            }


            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);

        }

        public function paynow(Request $request)
        {
            logger($request->all());
        }

        /**
         * purchase sender id by authorize net
         *
         * @param Senderid $senderid
         * @param Request  $request
         *
         * @return RedirectResponse
         */
        public function authorizeNetSenderID(Senderid $senderid, Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'authorize_net')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {

                    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
                    $merchantAuthentication->setName($credentials->login_id);
                    $merchantAuthentication->setTransactionKey($credentials->transaction_key);

                    // Set the transaction's refId
                    $refId      = 'ref' . time();
                    $cardNumber = preg_replace('/\s+/', '', $request->cardNumber);

                    // Create the payment data for a credit card
                    $creditCard = new AnetAPI\CreditCardType();
                    $creditCard->setCardNumber($cardNumber);
                    $creditCard->setExpirationDate($request->expiration_year . "-" . $request->expiration_month);
                    $creditCard->setCardCode($request->cvv);


                    // Add the payment data to a paymentType object
                    $paymentOne = new AnetAPI\PaymentType();
                    $paymentOne->setCreditCard($creditCard);

                    // Create order information
                    $order = new AnetAPI\OrderType();
                    $order->setInvoiceNumber($senderid->uid);
                    $order->setDescription(__('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id);


                    // Set the customer's Bill To address
                    $customerAddress = new AnetAPI\CustomerAddressType();
                    $customerAddress->setFirstName(auth()->user()->first_name);
                    $customerAddress->setLastName(auth()->user()->last_name);

                    // Set the customer's identifying information
                    $customerData = new AnetAPI\CustomerDataType();
                    $customerData->setType("individual");
                    $customerData->setId(auth()->user()->id);
                    $customerData->setEmail(auth()->user()->email);


                    // Create a TransactionRequestType object and add the previous objects to it
                    $transactionRequestType = new AnetAPI\TransactionRequestType();
                    $transactionRequestType->setTransactionType("authCaptureTransaction");
                    $transactionRequestType->setAmount($senderid->price);
                    $transactionRequestType->setOrder($order);
                    $transactionRequestType->setPayment($paymentOne);
                    $transactionRequestType->setBillTo($customerAddress);
                    $transactionRequestType->setCustomer($customerData);


                    // Assemble the complete transaction request
                    $requests = new AnetAPI\CreateTransactionRequest();
                    $requests->setMerchantAuthentication($merchantAuthentication);
                    $requests->setRefId($refId);
                    $requests->setTransactionRequest($transactionRequestType);

                    // Create the controller and get the response
                    $controller = new AnetController\CreateTransactionController($requests);
                    if ($credentials->environment == 'sandbox') {
                        $result = $controller->executeWithApiResponse(ANetEnvironment::SANDBOX);
                    } else {
                        $result = $controller->executeWithApiResponse(ANetEnvironment::PRODUCTION);
                    }

                    if (isset($result) && $result->getMessages()->getResultCode() == 'Ok' && $result->getTransactionResponse()) {

                        $invoice = Invoices::create([
                            'user_id'        => $senderid->user_id,
                            'currency_id'    => $senderid->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $senderid->price,
                            'type'           => Invoices::TYPE_SENDERID,
                            'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                            'transaction_id' => $result->getRefId(),
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current                   = Carbon::now();
                            $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                            $senderid->status          = 'active';
                            $senderid->payment_claimed = true;
                            $senderid->save();

                            $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                            return redirect()->route('customer.senderid.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * top up by authorize net
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function authorizeNetTopUp(Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'authorize_net')->first();
            $user          = User::find($request->get('user_id'));

            if ($paymentMethod && $user) {
                $credentials = json_decode($paymentMethod->options);

                try {

                    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
                    $merchantAuthentication->setName($credentials->login_id);
                    $merchantAuthentication->setTransactionKey($credentials->transaction_key);

                    // Set the transaction's refId
                    $refId      = 'ref' . time();
                    $cardNumber = preg_replace('/\s+/', '', $request->cardNumber);

                    // Create the payment data for a credit card
                    $creditCard = new AnetAPI\CreditCardType();
                    $creditCard->setCardNumber($cardNumber);
                    $creditCard->setExpirationDate($request->expiration_year . "-" . $request->expiration_month);
                    $creditCard->setCardCode($request->cvv);


                    // Add the payment data to a paymentType object
                    $paymentOne = new AnetAPI\PaymentType();
                    $paymentOne->setCreditCard($creditCard);

                    // Create order information
                    $order = new AnetAPI\OrderType();
                    $order->setInvoiceNumber(str_random(10));
                    $order->setDescription('Payment for sms top up');


                    // Set the customer's Bill To address
                    $customerAddress = new AnetAPI\CustomerAddressType();
                    $customerAddress->setFirstName(auth()->user()->first_name);
                    $customerAddress->setLastName(auth()->user()->last_name);

                    // Set the customer's identifying information
                    $customerData = new AnetAPI\CustomerDataType();
                    $customerData->setType("individual");
                    $customerData->setId(auth()->user()->id);
                    $customerData->setEmail(auth()->user()->email);


                    // Create a TransactionRequestType object and add the previous objects to it
                    $transactionRequestType = new AnetAPI\TransactionRequestType();
                    $transactionRequestType->setTransactionType("authCaptureTransaction");
                    $transactionRequestType->setAmount($request->get('sms_unit'));
                    $transactionRequestType->setOrder($order);
                    $transactionRequestType->setPayment($paymentOne);
                    $transactionRequestType->setBillTo($customerAddress);
                    $transactionRequestType->setCustomer($customerData);


                    // Assemble the complete transaction request
                    $requests = new AnetAPI\CreateTransactionRequest();
                    $requests->setMerchantAuthentication($merchantAuthentication);
                    $requests->setRefId($refId);
                    $requests->setTransactionRequest($transactionRequestType);

                    // Create the controller and get the response
                    $controller = new AnetController\CreateTransactionController($requests);
                    if ($credentials->environment == 'sandbox') {
                        $result = $controller->executeWithApiResponse(ANetEnvironment::SANDBOX);
                    } else {
                        $result = $controller->executeWithApiResponse(ANetEnvironment::PRODUCTION);
                    }

                    if (isset($result) && $result->getMessages()->getResultCode() == 'Ok' && $result->getTransactionResponse()) {

                        $invoice = Invoices::create([
                            'user_id'        => $user->id,
                            'currency_id'    => $user->customer->subscription->plan->currency->id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $request->get('sms_unit'),
                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                            'description'    => 'Payment for sms top up',
                            'transaction_id' => $result->getRefId(),
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {

                            if ($user->sms_unit != '-1') {
                                $user->sms_unit += $request->get('sms_unit');
                                $user->save();
                            }
                            $subscription = $user->customer->activeSubscription();

                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                'title'  => 'Add ' . $request->get('sms_unit') . ' sms units',
                                'amount' => $request->get('sms_unit') . ' sms units',
                            ]);

                            return redirect()->route('user.home')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * razorpay sender id payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function razorpaySenderID(Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'razorpay')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);
                $order_id    = Session::get('razorpay_order_id');

                if (isset($order_id) && empty($request->razorpay_payment_id) === false) {

                    $senderid = Senderid::where('transaction_id', $order_id)->first();
                    if ($senderid) {

                        $api        = new Api($credentials->key_id, $credentials->key_secret);
                        $attributes = [
                            'razorpay_order_id'   => $order_id,
                            'razorpay_payment_id' => $request->razorpay_payment_id,
                            'razorpay_signature'  => $request->razorpay_signature,
                        ];

                        try {

                            $response = $api->utility->verifyPaymentSignature($attributes);

                            if ($response) {
                                $invoice = Invoices::create([
                                    'user_id'        => $senderid->user_id,
                                    'currency_id'    => $senderid->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $senderid->price,
                                    'type'           => Invoices::TYPE_SENDERID,
                                    'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                    'transaction_id' => $order_id,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    $current                   = Carbon::now();
                                    $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                    $senderid->status          = 'active';
                                    $senderid->payment_claimed = true;
                                    $senderid->save();

                                    $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                    return redirect()->route('customer.senderid.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        } catch (SignatureVerificationError $exception) {

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }
                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * razorpay top up payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function razorpayTopUp(Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'razorpay')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);
                $order_id    = Session::get('razorpay_order_id');
                $user_id     = Session::get('user_id');
                $sms_unit    = Session::get('sms_unit');
                $price       = Session::get('price');

                $user = User::find($user_id);

                if (isset($order_id) && empty($request->razorpay_payment_id) === false && $user) {

                    $api        = new Api($credentials->key_id, $credentials->key_secret);
                    $attributes = [
                        'razorpay_order_id'   => $order_id,
                        'razorpay_payment_id' => $request->razorpay_payment_id,
                        'razorpay_signature'  => $request->razorpay_signature,
                    ];

                    try {

                        $response = $api->utility->verifyPaymentSignature($attributes);

                        if ($response) {
                            $invoice = Invoices::create([
                                'user_id'        => $user->id,
                                'currency_id'    => $user->customer->subscription->plan->currency->id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $price,
                                'type'           => Invoices::TYPE_SUBSCRIPTION,
                                'description'    => 'Payment for sms top up',
                                'transaction_id' => $order_id,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {

                                if ($user->sms_unit != '-1') {
                                    $user->sms_unit += $sms_unit;
                                    $user->save();
                                }

                                $subscription = $user->customer->activeSubscription();

                                $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                    'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                    'title'  => 'Add ' . $sms_unit . ' sms units',
                                    'amount' => $sms_unit . ' sms units',
                                ]);


                                return redirect()->route('user.home')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    } catch (SignatureVerificationError $exception) {

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * sslcommerz sender id payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function sslcommerzSenderID(Request $request): RedirectResponse
        {

            if (isset($request->status)) {
                if ($request->status == 'VALID') {
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'sslcommerz')->first();
                    if ($paymentMethod) {

                        $senderid = Senderid::findByUid($request->get('tran_id'));
                        if ($senderid) {

                            $invoice = Invoices::create([
                                'user_id'        => $senderid->user_id,
                                'currency_id'    => $senderid->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $senderid->price,
                                'type'           => Invoices::TYPE_SENDERID,
                                'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                'transaction_id' => $request->get('bank_tran_id'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                   = Carbon::now();
                                $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                $senderid->status          = 'active';
                                $senderid->payment_claimed = true;
                                $senderid->save();

                                $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                return redirect()->route('customer.senderid.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => $request->status,
                ]);

            }


            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * sslcommerz top up payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function sslcommerzTopUp(Request $request): RedirectResponse
        {

            if (isset($request->status)) {
                if ($request->status == 'VALID') {
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'sslcommerz')->first();
                    $user          = User::find($request->user_id);
                    if ($paymentMethod && $user) {

                        $invoice = Invoices::create([
                            'user_id'        => $user->id,
                            'currency_id'    => $user->customer->subscription->plan->currency->id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $request->get('sms_unit'),
                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                            'description'    => 'Payment for sms top up',
                            'transaction_id' => $request->get('bank_tran_id'),
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {

                            if ($user->sms_unit != '-1') {
                                $user->sms_unit += $request->get('sms_unit');
                                $user->save();
                            }

                            $subscription = $user->customer->activeSubscription();

                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                'title'  => 'Add ' . $request->get('sms_unit') . ' sms units',
                                'amount' => $request->get('sms_unit') . ' sms units',
                            ]);


                            return redirect()->route('user.home')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => $request->status,
                ]);

            }


            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * aamarpay sender id payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function aamarpaySenderID(Request $request): RedirectResponse
        {
            if (isset($request->pay_status) && isset($request->mer_txnid)) {

                $senderid = Senderid::findByUid($request->mer_txnid);

                if ($request->pay_status == 'Successful') {
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'aamarpay')->first();
                    if ($paymentMethod) {

                        if ($senderid) {

                            $invoice = Invoices::create([
                                'user_id'        => $senderid->user_id,
                                'currency_id'    => $senderid->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $senderid->price,
                                'type'           => Invoices::TYPE_SENDERID,
                                'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                'transaction_id' => $request->pg_txnid,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                   = Carbon::now();
                                $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                $senderid->status          = 'active';
                                $senderid->payment_claimed = true;
                                $senderid->save();

                                $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                return redirect()->route('customer.senderid.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                }

                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                    'status'  => 'error',
                    'message' => $request->pay_status,
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * aamarpay top up payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function aamarpayTopUp(Request $request): RedirectResponse
        {
            if (isset($request->pay_status) && isset($request->mer_txnid)) {

                if ($request->pay_status == 'Successful') {
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'aamarpay')->first();
                    $user          = User::find($request->user_id);
                    if ($paymentMethod && $user) {

                        $invoice = Invoices::create([
                            'user_id'        => $user->id,
                            'currency_id'    => $user->customer->subscription->plan->currency->id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $request->get('sms_unit'),
                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                            'description'    => 'Payment for sms top up',
                            'transaction_id' => $request->pg_txnid,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {

                            if ($user->sms_unit != '-1') {
                                $user->sms_unit += $request->get('sms_unit');
                                $user->save();
                            }

                            $subscription = $user->customer->activeSubscription();

                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                'title'  => 'Add ' . $request->get('sms_unit') . ' sms units',
                                'amount' => $request->get('sms_unit') . ' sms units',
                            ]);

                            return redirect()->route('user.home')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => $request->pay_status,
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * top up payments using flutter wave
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function flutterwaveTopUp(Request $request): RedirectResponse
        {
            if (isset($request->status) && isset($request->transaction_id)) {

                if ($request->status == 'successful') {

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'flutterwave')->first();

                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        $curl = curl_init();

                        curl_setopt_array($curl, [
                            CURLOPT_URL            => "https://api.flutterwave.com/v3/transactions/$request->transaction_id/verify",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING       => "",
                            CURLOPT_MAXREDIRS      => 10,
                            CURLOPT_TIMEOUT        => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST  => "GET",
                            CURLOPT_HTTPHEADER     => [
                                "Content-Type: application/json",
                                "Authorization: Bearer $credentials->secret_key",
                            ],
                        ]);

                        $response = curl_exec($curl);
                        curl_close($curl);

                        $get_data = json_decode($response, true);

                        if (isset($get_data) && is_array($get_data) && array_key_exists('status', $get_data)) {
                            if ($get_data['status'] == 'success') {
                                $user = User::find($request->user_id);
                                if ($user) {
                                    $invoice = Invoices::create([
                                        'user_id'        => $user->id,
                                        'currency_id'    => $user->customer->subscription->plan->currency->id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $request->get('sms_unit'),
                                        'type'           => Invoices::TYPE_SUBSCRIPTION,
                                        'description'    => 'Payment for sms top up',
                                        'transaction_id' => $request->transaction_id,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {

                                        if ($user->sms_unit != '-1') {
                                            $user->sms_unit += $request->get('sms_unit');
                                            $user->save();
                                        }

                                        $subscription = $user->customer->activeSubscription();

                                        $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                            'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                            'title'  => 'Add ' . $request->get('sms_unit') . ' sms units',
                                            'amount' => $request->get('sms_unit') . ' sms units',
                                        ]);

                                        return redirect()->route('user.home')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);
                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => __('locale.auth.user_not_exist'),
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => $get_data['message'],
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => $request->status,
                        ]);

                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);
                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => $request->status,
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * sender id payments using flutter wave
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function flutterwaveSenderID(Request $request): RedirectResponse
        {
            if (isset($request->status) && isset($request->transaction_id) && isset($request->tx_ref)) {

                if ($request->status == 'successful') {

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'flutterwave')->first();

                    if ($paymentMethod) {

                        $credentials = json_decode($paymentMethod->options);

                        $curl = curl_init();

                        curl_setopt_array($curl, [
                            CURLOPT_URL            => "https://api.flutterwave.com/v3/transactions/$request->transaction_id/verify",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING       => "",
                            CURLOPT_MAXREDIRS      => 10,
                            CURLOPT_TIMEOUT        => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST  => "GET",
                            CURLOPT_HTTPHEADER     => [
                                "Content-Type: application/json",
                                "Authorization: Bearer $credentials->secret_key",
                            ],
                        ]);

                        $response = curl_exec($curl);
                        curl_close($curl);

                        $get_data = json_decode($response, true);

                        if (isset($get_data) && is_array($get_data) && array_key_exists('status', $get_data)) {
                            if ($get_data['status'] == 'success') {

                                $senderid = Senderid::findByUid($request->tx_ref);

                                if ($senderid) {

                                    $invoice = Invoices::create([
                                        'user_id'        => $senderid->user_id,
                                        'currency_id'    => $senderid->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $senderid->price,
                                        'type'           => Invoices::TYPE_SENDERID,
                                        'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                        'transaction_id' => $request->transaction_id,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                   = Carbon::now();
                                        $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                        $senderid->status          = 'active';
                                        $senderid->payment_claimed = true;
                                        $senderid->save();

                                        $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                        return redirect()->route('customer.senderid.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);
                                }

                                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => $get_data['message'],
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => $request->status,
                        ]);

                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);
                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => $request->status,
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * flutterwave number payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function flutterwaveNumbers(Request $request): RedirectResponse
        {

            if (isset($request->status) && isset($request->transaction_id) && isset($request->tx_ref)) {

                if ($request->status == 'successful') {

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'flutterwave')->first();

                    if ($paymentMethod) {

                        $credentials = json_decode($paymentMethod->options);

                        $curl = curl_init();

                        curl_setopt_array($curl, [
                            CURLOPT_URL            => "https://api.flutterwave.com/v3/transactions/$request->transaction_id/verify",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING       => "",
                            CURLOPT_MAXREDIRS      => 10,
                            CURLOPT_TIMEOUT        => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST  => "GET",
                            CURLOPT_HTTPHEADER     => [
                                "Content-Type: application/json",
                                "Authorization: Bearer $credentials->secret_key",
                            ],
                        ]);

                        $response = curl_exec($curl);
                        curl_close($curl);

                        $get_data = json_decode($response, true);

                        if (isset($get_data) && is_array($get_data) && array_key_exists('status', $get_data)) {
                            if ($get_data['status'] == 'success') {

                                $number = PhoneNumbers::findByUid($request->tx_ref);

                                if ($number) {
                                    $invoice = Invoices::create([
                                        'user_id'        => $get_data['data']['meta']['user_id'],
                                        'currency_id'    => $number->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $number->price,
                                        'type'           => Invoices::TYPE_NUMBERS,
                                        'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                        'transaction_id' => $request->transaction_id,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current               = Carbon::now();
                                        $number->user_id       = $get_data['data']['meta']['user_id'];
                                        $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                        $number->status        = 'assigned';
                                        $number->save();

                                        $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                        return redirect()->route('customer.numbers.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);
                                }

                                return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => $get_data['message'],
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => $request->status,
                        ]);

                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);
                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => $request->status,
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * flutterwave keywords payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function flutterwaveKeywords(Request $request): RedirectResponse
        {

            if (isset($request->status) && isset($request->transaction_id) && isset($request->tx_ref)) {

                if ($request->status == 'successful') {

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'flutterwave')->first();

                    if ($paymentMethod) {

                        $credentials = json_decode($paymentMethod->options);

                        $curl = curl_init();

                        curl_setopt_array($curl, [
                            CURLOPT_URL            => "https://api.flutterwave.com/v3/transactions/$request->transaction_id/verify",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING       => "",
                            CURLOPT_MAXREDIRS      => 10,
                            CURLOPT_TIMEOUT        => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST  => "GET",
                            CURLOPT_HTTPHEADER     => [
                                "Content-Type: application/json",
                                "Authorization: Bearer $credentials->secret_key",
                            ],
                        ]);

                        $response = curl_exec($curl);
                        curl_close($curl);

                        $get_data = json_decode($response, true);

                        if (isset($get_data) && is_array($get_data) && array_key_exists('status', $get_data)) {
                            if ($get_data['status'] == 'success') {

                                $keyword = Keywords::findByUid($request->tx_ref);

                                if ($keyword) {
                                    $invoice = Invoices::create([
                                        'user_id'        => $get_data['data']['meta']['user_id'],
                                        'currency_id'    => $keyword->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $keyword->price,
                                        'type'           => Invoices::TYPE_KEYWORD,
                                        'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                        'transaction_id' => $request->transaction_id,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                = Carbon::now();
                                        $keyword->user_id       = $get_data['data']['meta']['user_id'];
                                        $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                        $keyword->status        = 'assigned';
                                        $keyword->save();

                                        $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                        if (Helper::app_config('keyword_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                        }

                                        $user = auth()->user();

                                        if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                            $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                        }

                                        return redirect()->route('customer.keywords.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);
                                }

                                return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => $get_data['message'],
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => $request->status,
                        ]);

                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);
                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => $request->status,
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * flutterwave subscription payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function flutterwaveSubscriptions(Request $request): RedirectResponse
        {

            if (isset($request->status) && isset($request->transaction_id) && isset($request->tx_ref)) {

                if ($request->status == 'successful') {

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'flutterwave')->first();

                    if ($paymentMethod) {

                        $credentials = json_decode($paymentMethod->options);

                        $curl = curl_init();

                        curl_setopt_array($curl, [
                            CURLOPT_URL            => "https://api.flutterwave.com/v3/transactions/$request->transaction_id/verify",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING       => "",
                            CURLOPT_MAXREDIRS      => 10,
                            CURLOPT_TIMEOUT        => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST  => "GET",
                            CURLOPT_HTTPHEADER     => [
                                "Content-Type: application/json",
                                "Authorization: Bearer $credentials->secret_key",
                            ],
                        ]);

                        $response = curl_exec($curl);
                        curl_close($curl);

                        $get_data = json_decode($response, true);

                        if (isset($get_data) && is_array($get_data) && array_key_exists('status', $get_data)) {
                            if ($get_data['status'] == 'success') {

                                $plan = Plan::where('uid', $request->tx_ref)->first();

                                if ($plan) {
                                    $invoice = Invoices::create([
                                        'user_id'        => $get_data['data']['meta']['user_id'],
                                        'currency_id'    => $plan->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $plan->price,
                                        'type'           => Invoices::TYPE_SUBSCRIPTION,
                                        'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                        'transaction_id' => $request->transaction_id,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        if (Auth::user()->customer->activeSubscription()) {
                                            Auth::user()->customer->activeSubscription()->cancelNow();
                                        }

                                        if (Auth::user()->customer->subscription) {
                                            $subscription = Auth::user()->customer->subscription;

                                            $get_options           = json_decode($subscription->options, true);
                                            $output                = array_replace($get_options, [
                                                'send_warning' => false,
                                            ]);
                                            $subscription->options = json_encode($output);

                                        } else {
                                            $subscription           = new Subscription();
                                            $subscription->user_id  = Auth::user()->id;
                                            $subscription->start_at = Carbon::now();
                                        }

                                        $subscription->status                 = Subscription::STATUS_ACTIVE;
                                        $subscription->plan_id                = $plan->getBillableId();
                                        $subscription->end_period_last_days   = '10';
                                        $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                        $subscription->end_at                 = null;
                                        $subscription->end_by                 = null;
                                        $subscription->payment_method_id      = $paymentMethod->id;
                                        $subscription->save();

                                        // add transaction
                                        $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                            'end_at'                 => $subscription->end_at,
                                            'current_period_ends_at' => $subscription->current_period_ends_at,
                                            'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                            'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                            'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                        ]);

                                        // add log
                                        $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                            'plan'  => $subscription->plan->getBillableName(),
                                            'price' => $subscription->plan->getBillableFormattedPrice(),
                                        ]);


                                        $user = User::find($get_data['data']['meta']['user_id']);

                                        if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                            $user->sms_unit = $plan->getOption('sms_max');
                                        } else {
                                            if ($plan->getOption('add_previous_balance') == 'yes') {
                                                $user->sms_unit += $plan->getOption('sms_max');
                                            } else {
                                                $user->sms_unit = $plan->getOption('sms_max');
                                            }
                                        }

                                        $user->save();

                                        $this->createNotification('plan', $plan->name, $user->displayName());


                                        if (Helper::app_config('subscription_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                        }

                                        if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                            $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                        }

                                        return redirect()->route('customer.subscriptions.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);
                                }

                                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => $get_data['message'],
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => $request->status,
                        ]);

                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);
                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => $request->status,
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * cancel payment
         *
         * @param PhoneNumbers $number
         * @param Request      $request
         *
         * @return RedirectResponse
         */
        public function cancelledNumberPayment(PhoneNumbers $number, Request $request): RedirectResponse
        {

            $payment_method = Session::get('payment_method');

            switch ($payment_method) {
                case PaymentMethods::TYPE_PAYPAL:

                    $token = Session::get('paypal_payment_id');
                    if ($request->get('token') == $token) {
                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'info',
                            'message' => __('locale.sender_id.payment_cancelled'),
                        ]);
                    }
                    break;

                case PaymentMethods::TYPE_STRIPE:
                case PaymentMethods::TYPE_PAYU:
                case PaymentMethods::TYPE_COINPAYMENTS:
                case PaymentMethods::TYPE_PAYUMONEY:
                case PaymentMethods::TYPE_PAYHERELK:
                case PaymentMethods::TYPE_MPGS:
                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'info',
                        'message' => __('locale.sender_id.payment_cancelled'),
                    ]);
            }


            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                'status'  => 'info',
                'message' => __('locale.sender_id.payment_cancelled'),
            ]);

        }

        /**
         * purchase number by braintree
         *
         * @param PhoneNumbers $number
         * @param Request      $request
         *
         * @return RedirectResponse
         */
        public function braintreeNumber(PhoneNumbers $number, Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'braintree')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {
                    $gateway = new Gateway([
                        'environment' => $credentials->environment,
                        'merchantId'  => $credentials->merchant_id,
                        'publicKey'   => $credentials->public_key,
                        'privateKey'  => $credentials->private_key,
                    ]);

                    $result = $gateway->transaction()->sale([
                        'amount'             => $number->price,
                        'paymentMethodNonce' => $request->get('payment_method_nonce'),
                        'deviceData'         => $request->get('device_data'),
                        'options'            => [
                            'submitForSettlement' => true,
                        ],
                    ]);

                    if ($result->success && isset($result->transaction->id)) {
                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $number->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $number->price,
                            'type'           => Invoices::TYPE_NUMBERS,
                            'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                            'transaction_id' => $result->transaction->id,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current               = Carbon::now();
                            $number->user_id       = auth()->user()->id;
                            $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                            $number->status        = 'assigned';
                            $number->save();

                            $this->createNotification('number', $number->number, auth()->user()->displayName());

                            return redirect()->route('customer.numbers.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => $result->message,
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * successful number purchase
         *
         * @param PhoneNumbers $number
         * @param Request      $request
         *
         * @return RedirectResponse
         * @throws Exception
         */
        public function successfulNumberPayment(PhoneNumbers $number, Request $request): RedirectResponse
        {
            $payment_method = Session::get('payment_method');

            if ($payment_method == null) {
                $payment_method = $request->get('payment_method');
            }

            switch ($payment_method) {

                case PaymentMethods::TYPE_PAYPAL:
                    $token = Session::get('paypal_payment_id');
                    if ($request->get('token') == $token) {
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYPAL)->first();

                        if ($paymentMethod) {
                            $credentials = json_decode($paymentMethod->options);

                            if ($credentials->environment == 'sandbox') {
                                $environment = new SandboxEnvironment($credentials->client_id, $credentials->secret);
                            } else {
                                $environment = new ProductionEnvironment($credentials->client_id, $credentials->secret);
                            }

                            $client = new PayPalHttpClient($environment);

                            $request = new OrdersCaptureRequest($token);
                            $request->prefer('return=representation');

                            try {
                                // Call API with your client and get a response for your call
                                $response = $client->execute($request);

                                if ($response->statusCode == '201' && $response->result->status == 'COMPLETED' && isset($response->id)) {
                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $number->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $number->price,
                                        'type'           => Invoices::TYPE_NUMBERS,
                                        'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                        'transaction_id' => $response->id,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current               = Carbon::now();
                                        $number->user_id       = auth()->user()->id;
                                        $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                        $number->status        = 'assigned';
                                        $number->save();

                                        $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                        return redirect()->route('customer.numbers.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                            } catch (Exception $ex) {
                                return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                    'status'  => 'error',
                                    'message' => $ex->getMessage(),
                                ]);
                            }


                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'info',
                                'message' => __('locale.sender_id.payment_cancelled'),
                            ]);
                        }

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);
                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                case PaymentMethods::TYPE_STRIPE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_STRIPE)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);
                        $secret_key  = $credentials->secret_key;
                        $session_id  = Session::get('session_id');

                        $stripe = new StripeClient($secret_key);

                        try {
                            $response = $stripe->checkout->sessions->retrieve($session_id);

                            if ($response->payment_status == 'paid') {
                                $invoice = Invoices::create([
                                    'user_id'        => auth()->user()->id,
                                    'currency_id'    => $number->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $number->price,
                                    'type'           => Invoices::TYPE_NUMBERS,
                                    'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                    'transaction_id' => $response->payment_intent,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    $current               = Carbon::now();
                                    $number->user_id       = auth()->user()->id;
                                    $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                    $number->status        = 'assigned';
                                    $number->save();

                                    $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                    if (Helper::app_config('phone_number_notification_email')) {
                                        $admin = User::find(1);
                                        $admin->notify(new NumberPurchase(route('admin.phone-numbers.show', $number->uid)));
                                    }


                                    return redirect()->route('customer.numbers.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                        } catch (ApiErrorException $e) {
                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => $e->getMessage(),
                            ]);
                        }

                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);

                case PaymentMethods::TYPE_2CHECKOUT:
                case PaymentMethods::TYPE_PAYU:
                case PaymentMethods::TYPE_COINPAYMENTS:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', $payment_method)->first();

                    if ($paymentMethod) {
                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $number->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $number->price,
                            'type'           => Invoices::TYPE_NUMBERS,
                            'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                            'transaction_id' => $number->uid,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current               = Carbon::now();
                            $number->user_id       = auth()->user()->id;
                            $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                            $number->status        = 'assigned';
                            $number->save();

                            $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                            return redirect()->route('customer.numbers.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_PAYNOW:
                    $pollurl = Session::get('paynow_poll_url');
                    if (isset($pollurl)) {
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYNOW)->first();

                        if ($paymentMethod) {
                            $credentials = json_decode($paymentMethod->options);

                            $paynow = new Paynow(
                                $credentials->integration_id,
                                $credentials->integration_key,
                                route('customer.callback.paynow'),
                                route('customer.numbers.payment_success', $number->uid)
                            );

                            try {
                                $response = $paynow->pollTransaction($pollurl);

                                if ($response->paid()) {
                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $number->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $number->price,
                                        'type'           => Invoices::TYPE_NUMBERS,
                                        'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                        'transaction_id' => $response->reference(),
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current               = Carbon::now();
                                        $number->user_id       = auth()->user()->id;
                                        $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                        $number->status        = 'assigned';
                                        $number->save();

                                        $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                        return redirect()->route('customer.numbers.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                            } catch (Exception $ex) {
                                return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                    'status'  => 'error',
                                    'message' => $ex->getMessage(),
                                ]);
                            }


                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'info',
                                'message' => __('locale.sender_id.payment_cancelled'),
                            ]);
                        }

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);
                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                case PaymentMethods::TYPE_INSTAMOJO:
                    $payment_request_id = Session::get('payment_request_id');

                    if ($request->get('payment_request_id') == $payment_request_id) {
                        if ($request->get('payment_status') == 'Completed') {

                            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_INSTAMOJO)->first();

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $number->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $number->price,
                                'type'           => Invoices::TYPE_NUMBERS,
                                'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                'transaction_id' => $request->get('payment_id'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current               = Carbon::now();
                                $number->user_id       = auth()->user()->id;
                                $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                $number->status        = 'assigned';
                                $number->save();

                                $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                return redirect()->route('customer.numbers.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        }

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'info',
                            'message' => $request->get('payment_status'),
                        ]);
                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'info',
                        'message' => __('locale.payment_gateways.payment_info_not_found'),
                    ]);

                case PaymentMethods::TYPE_PAYUMONEY:

                    $status      = $request->status;
                    $firstname   = $request->firstname;
                    $amount      = $request->amount;
                    $txnid       = $request->get('txnid');
                    $posted_hash = $request->hash;
                    $key         = $request->key;
                    $productinfo = $request->productinfo;
                    $email       = $request->email;
                    $salt        = "";

                    // Salt should be same Post Request
                    if (isset($request->additionalCharges)) {
                        $additionalCharges = $request->additionalCharges;
                        $retHashSeq        = $additionalCharges . '|' . $salt . '|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
                    } else {
                        $retHashSeq = $salt . '|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
                    }
                    $hash = hash("sha512", $retHashSeq);
                    if ($hash != $posted_hash) {
                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'info',
                            'message' => __('locale.exceptions.invalid_action'),
                        ]);
                    }

                    if ($status == 'Completed') {

                        $paymentMethod = PaymentMethods::where('status', true)->where('type', 'payumoney')->first();

                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $number->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $number->price,
                            'type'           => Invoices::TYPE_NUMBERS,
                            'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                            'transaction_id' => $txnid,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current               = Carbon::now();
                            $number->user_id       = auth()->user()->id;
                            $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                            $number->status        = 'assigned';
                            $number->save();

                            $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                            return redirect()->route('customer.numbers.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }


                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => $status,
                    ]);

                case PaymentMethods::TYPE_DIRECTPAYONLINE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_DIRECTPAYONLINE)->first();

                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        if ($credentials->environment == 'production') {
                            $payment_url = 'https://secure.3gdirectpay.com';
                        } else {
                            $payment_url = 'https://secure1.sandbox.directpay.online';
                        }

                        $companyToken     = $credentials->company_token;
                        $TransactionToken = $request->get('TransactionToken');

                        $postXml = <<<POSTXML
<?xml version="1.0" encoding="utf-8"?>
        <API3G>
          <CompanyToken>$companyToken</CompanyToken>
          <Request>verifyToken</Request>
          <TransactionToken>$TransactionToken</TransactionToken>
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
                        curl_close($curl);

                        if ($response != '') {
                            $xml = new SimpleXMLElement($response);

                            // Check if token was created successfully
                            if ($xml->xpath('Result')[0] != '000') {
                                return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                    'status'  => 'info',
                                    'message' => __('locale.exceptions.invalid_action'),
                                ]);
                            }

                            if (isset($request->TransID) && isset($request->CCDapproval)) {
                                $invoice_exist = Invoices::where('transaction_id', $request->TransID)->first();
                                if ( ! $invoice_exist) {
                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $number->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $number->price,
                                        'type'           => Invoices::TYPE_NUMBERS,
                                        'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                        'transaction_id' => $request->TransID,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current               = Carbon::now();
                                        $number->user_id       = auth()->user()->id;
                                        $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                        $number->status        = 'assigned';
                                        $number->save();

                                        $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                        return redirect()->route('customer.numbers.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }
                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                        }
                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);

                case PaymentMethods::TYPE_PAYGATEGLOBAL:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYGATEGLOBAL)->first();

                    if ($paymentMethod) {

                        $parameters = [
                            'auth_token' => $paymentMethod->api_key,
                            'identify'   => $request->get('identify'),
                        ];

                        try {

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, 'https://paygateglobal.com/api/v2/status');
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($ch);
                            curl_close($ch);

                            $get_response = json_decode($response, true);

                            if (isset($get_response) && is_array($get_response) && array_key_exists('status', $get_response)) {
                                if ($get_response['success'] == 0) {

                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $number->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $number->price,
                                        'type'           => Invoices::TYPE_NUMBERS,
                                        'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                        'transaction_id' => $request->tx_reference,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current               = Carbon::now();
                                        $number->user_id       = auth()->user()->id;
                                        $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                        $number->status        = 'assigned';
                                        $number->save();

                                        $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                        return redirect()->route('customer.numbers.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                                return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                    'status'  => 'info',
                                    'message' => 'Waiting for administrator approval',
                                ]);
                            }

                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        } catch (Exception $e) {
                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => $e->getMessage(),
                            ]);
                        }
                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_ORANGEMONEY:

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_ORANGEMONEY)->first();

                    if (isset($request->status)) {
                        if ($request->status == 'SUCCESS') {

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $number->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $number->price,
                                'type'           => Invoices::TYPE_NUMBERS,
                                'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                'transaction_id' => $request->get('txnid'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current               = Carbon::now();
                                $number->user_id       = auth()->user()->id;
                                $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                $number->status        = 'assigned';
                                $number->save();

                                $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                return redirect()->route('customer.numbers.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        }

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'info',
                            'message' => $request->status,
                        ]);
                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_CINETPAY:

                    $paymentMethod  = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_CINETPAY)->first();
                    $transaction_id = $request->transaction_id;
                    $credentials    = json_decode($paymentMethod->options);

                    $payment_data = [
                        'apikey'         => $credentials->api_key,
                        'site_id'        => $credentials->site_id,
                        'transaction_id' => $transaction_id,
                    ];


                    try {

                        $curl = curl_init();

                        curl_setopt_array($curl, [
                            CURLOPT_URL            => $credentials->payment_url . '/check',
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
                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => 'Php curl show false value. Please contact with your provider',
                            ]);

                        }

                        if ($err) {
                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => $err,
                            ]);
                        }

                        $result = json_decode($response, true);


                        if (is_array($result) && array_key_exists('code', $result) && array_key_exists('message', $result)) {

                            if ($result['code'] == '00') {
                                $invoice = Invoices::create([
                                    'user_id'        => auth()->user()->id,
                                    'currency_id'    => $number->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $number->price,
                                    'type'           => Invoices::TYPE_NUMBERS,
                                    'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                    'transaction_id' => $transaction_id,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    $current               = Carbon::now();
                                    $number->user_id       = auth()->user()->id;
                                    $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                    $number->status        = 'assigned';
                                    $number->save();

                                    $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                    return redirect()->route('customer.numbers.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                    'status'       => 'error',
                                    'redirect_url' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => $result['message'],
                            ]);
                        }

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'       => 'error',
                            'redirect_url' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    } catch (Exception $ex) {

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'       => 'error',
                            'redirect_url' => $ex->getMessage(),
                        ]);
                    }

                case PaymentMethods::TYPE_PAYHERELK:

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYHERELK)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        try {

                            if ($credentials->environment == 'sandbox') {
                                $auth_url    = 'https://sandbox.payhere.lk/merchant/v1/oauth/token';
                                $payment_url = 'https://sandbox.payhere.lk/merchant/v1/payment/search';
                            } else {
                                $auth_url    = 'https://payhere.lk/merchant/v1/oauth/token';
                                $payment_url = 'https://payhere.lk/merchant/v1/payment/search';
                            }

                            $headers = [
                                'Content-Type: application/x-www-form-urlencoded',
                                'Authorization: Basic ' . base64_encode("$credentials->app_id:$credentials->app_secret"),
                            ];

                            $ch = curl_init();

                            curl_setopt($ch, CURLOPT_URL, $auth_url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                            $auth_data = curl_exec($ch);

                            if (curl_errno($ch)) {

                                return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                    'status'  => 'error',
                                    'message' => curl_error($ch),
                                ]);
                            }

                            curl_close($ch);

                            $result = json_decode($auth_data, true);

                            if (is_array($result)) {
                                if (array_key_exists('error_description', $result)) {

                                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                        'status'  => 'error',
                                        'message' => $result['error_description'],
                                    ]);
                                }


                                $headers = [
                                    'Content-Type: application/json',
                                    'Authorization: Bearer ' . $result['access_token'],
                                ];

                                $curl = curl_init();

                                curl_setopt($curl, CURLOPT_URL, $payment_url);
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($curl, CURLOPT_POST, 1);
                                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['order_id' => $request->get('order_id')]));
                                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

                                $payment_data = curl_exec($curl);

                                if (curl_errno($curl)) {

                                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                        'status'  => 'error',
                                        'message' => curl_error($curl),
                                    ]);
                                }

                                curl_close($curl);

                                $result = json_decode($payment_data, true);

                                if (is_array($result)) {
                                    if (array_key_exists('error_description', $result)) {

                                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                            'status'  => 'error',
                                            'message' => $result['error_description'],
                                        ]);
                                    }

                                    if (array_key_exists('status', $result) && $result['status'] == '-1') {
                                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                            'status'  => 'error',
                                            'message' => $result['msg'],
                                        ]);
                                    }


                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $number->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $number->price,
                                        'type'           => Invoices::TYPE_NUMBERS,
                                        'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                        'transaction_id' => $request->get('order_id'),
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current               = Carbon::now();
                                        $number->user_id       = auth()->user()->id;
                                        $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                        $number->status        = 'assigned';
                                        $number->save();

                                        $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                        return redirect()->route('customer.numbers.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                                return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        } catch (Exception $exception) {
                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }
                    }
                    break;

                case PaymentMethods::TYPE_MOLLIE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_MOLLIE)->first();

                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        $mollie = new MollieApiClient();
                        $mollie->setApiKey($credentials->api_key);

                        $payment_id = Session::get('payment_id');

                        $payment = $mollie->payments->get($payment_id);

                        if ($payment->isPaid()) {

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $number->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $number->price,
                                'type'           => Invoices::TYPE_NUMBERS,
                                'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                'transaction_id' => $payment_id,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current               = Carbon::now();
                                $number->user_id       = auth()->user()->id;
                                $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                $number->status        = 'assigned';
                                $number->save();

                                $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                return redirect()->route('customer.numbers.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);

                case PaymentMethods::TYPE_SELCOMMOBILE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_SELCOMMOBILE)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        $orderStatusArray = [
                            "order_id" => $number->uid,
                        ];

                        $client = new Client($credentials->payment_url, $credentials->api_key, $credentials->api_secret);

                        // path relative to base url
                        $orderStatusPath = "/checkout/order-status";

                        // create order minimal
                        try {
                            $response = $client->getFunc($orderStatusPath, $orderStatusArray);

                            if (isset($response) && is_array($response) && array_key_exists('data', $response) && array_key_exists('result', $response)) {
                                if ($response['result'] == 'SUCCESS' && array_key_exists('0', $response['data']) && $response['data'][0]['payment_status'] == 'COMPLETED') {
                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $number->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $number->price,
                                        'type'           => Invoices::TYPE_NUMBERS,
                                        'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                        'transaction_id' => $response['data'][0]['transid'],
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current               = Carbon::now();
                                        $number->user_id       = auth()->user()->id;
                                        $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                        $number->status        = 'assigned';
                                        $number->save();

                                        $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                        if (Helper::app_config('phone_number_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new NumberPurchase(route('admin.phone-numbers.show', $number->uid)));
                                        }


                                        return redirect()->route('customer.numbers.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);
                                } else {
                                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                        'status'  => 'error',
                                        'message' => $response['message'],
                                    ]);
                                }
                            }

                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => $response,
                            ]);

                        } catch (Exception $exception) {
                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }

                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_MPGS:

                    $order_id = $request->input('order_id');

                    if (empty($order_id)) {
                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => 'Payment error: Invalid transaction.',
                        ]);
                    }

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_MPGS)->first();


                    if ( ! $paymentMethod) {
                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);

                    }

                    $credentials = json_decode($paymentMethod->options);

                    $config = [
                        'payment_url'             => $credentials->payment_url,
                        'api_version'             => $credentials->api_version,
                        'merchant_id'             => $credentials->merchant_id,
                        'authentication_password' => $credentials->authentication_password,
                    ];


                    $paymentData = [
                        'order_id' => $order_id,
                    ];

                    $mpgs   = new MPGS($config, $paymentData);
                    $result = $mpgs->process_response();

                    if (isset($result->getData()->status) && isset($result->getData()->message)) {
                        if ($result->getData()->status == 'success') {

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $number->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $number->price,
                                'type'           => Invoices::TYPE_NUMBERS,
                                'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                'transaction_id' => $result->getData()->transaction_id,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current               = Carbon::now();
                                $number->user_id       = auth()->user()->id;
                                $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                $number->status        = 'assigned';
                                $number->save();

                                $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                return redirect()->route('customer.numbers.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }


                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => $result->getData()->message,
                        ]);
                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

            }

            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);

        }

        /**
         * purchase number by authorize net
         *
         * @param PhoneNumbers $number
         * @param Request      $request
         *
         * @return RedirectResponse
         */
        public function authorizeNetNumber(PhoneNumbers $number, Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'authorize_net')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {

                    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
                    $merchantAuthentication->setName($credentials->login_id);
                    $merchantAuthentication->setTransactionKey($credentials->transaction_key);

                    // Set the transaction's refId
                    $refId      = 'ref' . time();
                    $cardNumber = preg_replace('/\s+/', '', $request->cardNumber);

                    // Create the payment data for a credit card
                    $creditCard = new AnetAPI\CreditCardType();
                    $creditCard->setCardNumber($cardNumber);
                    $creditCard->setExpirationDate($request->expiration_year . "-" . $request->expiration_month);
                    $creditCard->setCardCode($request->cvv);


                    // Add the payment data to a paymentType object
                    $paymentOne = new AnetAPI\PaymentType();
                    $paymentOne->setCreditCard($creditCard);

                    // Create order information
                    $order = new AnetAPI\OrderType();
                    $order->setInvoiceNumber($number->uid);
                    $order->setDescription(__('locale.phone_numbers.payment_for_number') . ' ' . $number->number);


                    // Set the customer's Bill To address
                    $customerAddress = new AnetAPI\CustomerAddressType();
                    $customerAddress->setFirstName(auth()->user()->first_name);
                    $customerAddress->setLastName(auth()->user()->last_name);

                    // Set the customer's identifying information
                    $customerData = new AnetAPI\CustomerDataType();
                    $customerData->setType("individual");
                    $customerData->setId(auth()->user()->id);
                    $customerData->setEmail(auth()->user()->email);


                    // Create a TransactionRequestType object and add the previous objects to it
                    $transactionRequestType = new AnetAPI\TransactionRequestType();
                    $transactionRequestType->setTransactionType("authCaptureTransaction");
                    $transactionRequestType->setAmount($number->price);
                    $transactionRequestType->setOrder($order);
                    $transactionRequestType->setPayment($paymentOne);
                    $transactionRequestType->setBillTo($customerAddress);
                    $transactionRequestType->setCustomer($customerData);


                    // Assemble the complete transaction request
                    $requests = new AnetAPI\CreateTransactionRequest();
                    $requests->setMerchantAuthentication($merchantAuthentication);
                    $requests->setRefId($refId);
                    $requests->setTransactionRequest($transactionRequestType);

                    // Create the controller and get the response
                    $controller = new AnetController\CreateTransactionController($requests);
                    if ($credentials->environment == 'sandbox') {
                        $result = $controller->executeWithApiResponse(ANetEnvironment::SANDBOX);
                    } else {
                        $result = $controller->executeWithApiResponse(ANetEnvironment::PRODUCTION);
                    }

                    if (isset($result) && $result->getMessages()->getResultCode() == 'Ok' && $result->getTransactionResponse()) {

                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $number->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $number->price,
                            'type'           => Invoices::TYPE_NUMBERS,
                            'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                            'transaction_id' => $result->getRefId(),
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current               = Carbon::now();
                            $number->user_id       = auth()->user()->id;
                            $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                            $number->status        = 'assigned';
                            $number->save();

                            $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                            return redirect()->route('customer.numbers.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * razorpay number payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function razorpayNumbers(Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'razorpay')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);
                $order_id    = Session::get('razorpay_order_id');

                if (isset($order_id) && empty($request->razorpay_payment_id) === false) {

                    $number = PhoneNumbers::where('transaction_id', $order_id)->first();

                    if ($number) {
                        $api        = new Api($credentials->key_id, $credentials->key_secret);
                        $attributes = [
                            'razorpay_order_id'   => $order_id,
                            'razorpay_payment_id' => $request->razorpay_payment_id,
                            'razorpay_signature'  => $request->razorpay_signature,
                        ];

                        try {

                            $response = $api->utility->verifyPaymentSignature($attributes);

                            if ($response) {
                                $invoice = Invoices::create([
                                    'user_id'        => auth()->user()->id,
                                    'currency_id'    => $number->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $number->price,
                                    'type'           => Invoices::TYPE_NUMBERS,
                                    'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                    'transaction_id' => $order_id,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    $current               = Carbon::now();
                                    $number->user_id       = auth()->user()->id;
                                    $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                    $number->status        = 'assigned';
                                    $number->save();

                                    $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                    return redirect()->route('customer.numbers.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        } catch (SignatureVerificationError $exception) {

                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }
                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * sslcommerz number payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function sslcommerzNumbers(Request $request): RedirectResponse
        {

            if (isset($request->status)) {
                if ($request->status == 'VALID') {
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'sslcommerz')->first();
                    if ($paymentMethod) {

                        $number = PhoneNumbers::findByUid($request->get('tran_id'));

                        if ($number) {
                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $number->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $number->price,
                                'type'           => Invoices::TYPE_NUMBERS,
                                'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                'transaction_id' => $request->get('bank_tran_id'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current               = Carbon::now();
                                $number->user_id       = auth()->user()->id;
                                $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                $number->status        = 'assigned';
                                $number->save();

                                $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                return redirect()->route('customer.numbers.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => $request->status,
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * aamarpay number payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function aamarpayNumbers(Request $request): RedirectResponse
        {

            if (isset($request->pay_status) && isset($request->mer_txnid)) {

                $number = PhoneNumbers::findByUid($request->mer_txnid);

                if ($request->pay_status == 'Successful') {
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'aamarpay')->first();
                    if ($paymentMethod) {

                        if ($number) {
                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $number->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $number->price,
                                'type'           => Invoices::TYPE_NUMBERS,
                                'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                'transaction_id' => $request->pg_txnid,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current               = Carbon::now();
                                $number->user_id       = auth()->user()->id;
                                $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                $number->status        = 'assigned';
                                $number->save();

                                $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                return redirect()->route('customer.numbers.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                }

                return redirect()->route('customer.numbers.pay', $number->uid)->with([
                    'status'  => 'error',
                    'message' => $request->pay_status,
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * cancel payment
         *
         * @param Keywords $keyword
         * @param Request  $request
         *
         * @return RedirectResponse
         */
        public function cancelledKeywordPayment(Keywords $keyword, Request $request): RedirectResponse
        {

            $payment_method = Session::get('payment_method');

            switch ($payment_method) {
                case PaymentMethods::TYPE_PAYPAL:

                    $token = Session::get('paypal_payment_id');
                    if ($request->get('token') == $token) {
                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'info',
                            'message' => __('locale.sender_id.payment_cancelled'),
                        ]);
                    }
                    break;

                case PaymentMethods::TYPE_STRIPE:
                case PaymentMethods::TYPE_PAYU:
                case PaymentMethods::TYPE_COINPAYMENTS:
                case PaymentMethods::TYPE_PAYUMONEY:
                case PaymentMethods::TYPE_PAYHERELK:
                case PaymentMethods::TYPE_MPGS:
                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'info',
                        'message' => __('locale.sender_id.payment_cancelled'),
                    ]);
            }


            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                'status'  => 'info',
                'message' => __('locale.sender_id.payment_cancelled'),
            ]);

        }

        /**
         * purchase keyword by braintree
         *
         * @param Keywords $keyword
         * @param Request  $request
         *
         * @return RedirectResponse
         */
        public function braintreeKeyword(Keywords $keyword, Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'braintree')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {
                    $gateway = new Gateway([
                        'environment' => $credentials->environment,
                        'merchantId'  => $credentials->merchant_id,
                        'publicKey'   => $credentials->public_key,
                        'privateKey'  => $credentials->private_key,
                    ]);

                    $result = $gateway->transaction()->sale([
                        'amount'             => $keyword->price,
                        'paymentMethodNonce' => $request->get('payment_method_nonce'),
                        'deviceData'         => $request->get('device_data'),
                        'options'            => [
                            'submitForSettlement' => true,
                        ],
                    ]);

                    if ($result->success && isset($result->transaction->id)) {
                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $keyword->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $keyword->price,
                            'type'           => Invoices::TYPE_KEYWORD,
                            'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                            'transaction_id' => $result->transaction->id,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current                = Carbon::now();
                            $keyword->user_id       = auth()->user()->id;
                            $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                            $keyword->status        = 'assigned';
                            $keyword->save();

                            $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                            if (Helper::app_config('keyword_notification_email')) {
                                $admin = User::find(1);
                                $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                            }

                            $user = auth()->user();

                            if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                            }

                            return redirect()->route('customer.keywords.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => $result->message,
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * successful keyword purchase
         *
         * @param Keywords $keyword
         * @param Request  $request
         *
         * @return RedirectResponse
         * @throws Exception
         */
        public function successfulKeywordPayment(Keywords $keyword, Request $request): RedirectResponse
        {
            $payment_method = Session::get('payment_method');
            if ($payment_method == null) {
                $payment_method = $request->get('payment_method');
            }

            switch ($payment_method) {
                case PaymentMethods::TYPE_PAYPAL:
                    $token = Session::get('paypal_payment_id');
                    if ($request->get('token') == $token) {
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYPAL)->first();

                        if ($paymentMethod) {
                            $credentials = json_decode($paymentMethod->options);

                            if ($credentials->environment == 'sandbox') {
                                $environment = new SandboxEnvironment($credentials->client_id, $credentials->secret);
                            } else {
                                $environment = new ProductionEnvironment($credentials->client_id, $credentials->secret);
                            }

                            $client = new PayPalHttpClient($environment);

                            $request = new OrdersCaptureRequest($token);
                            $request->prefer('return=representation');

                            try {
                                // Call API with your client and get a response for your call
                                $response = $client->execute($request);

                                if ($response->statusCode == '201' && $response->result->status == 'COMPLETED' && isset($response->id)) {
                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $keyword->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $keyword->price,
                                        'type'           => Invoices::TYPE_KEYWORD,
                                        'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                        'transaction_id' => $response->id,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                = Carbon::now();
                                        $keyword->user_id       = auth()->user()->id;
                                        $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                        $keyword->status        = 'assigned';
                                        $keyword->save();

                                        $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                        if (Helper::app_config('keyword_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                        }

                                        $user = auth()->user();

                                        if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                            $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                        }

                                        return redirect()->route('customer.keywords.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                            } catch (Exception $ex) {
                                return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                    'status'  => 'error',
                                    'message' => $ex->getMessage(),
                                ]);
                            }


                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'info',
                                'message' => __('locale.sender_id.payment_cancelled'),
                            ]);
                        }

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);
                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                case PaymentMethods::TYPE_STRIPE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_STRIPE)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);
                        $secret_key  = $credentials->secret_key;
                        $session_id  = Session::get('session_id');

                        $stripe = new StripeClient($secret_key);

                        try {
                            $response = $stripe->checkout->sessions->retrieve($session_id);

                            if ($response->payment_status == 'paid') {
                                $invoice = Invoices::create([
                                    'user_id'        => auth()->user()->id,
                                    'currency_id'    => $keyword->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $keyword->price,
                                    'type'           => Invoices::TYPE_KEYWORD,
                                    'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                    'transaction_id' => $response->payment_intent,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    $current                = Carbon::now();
                                    $keyword->user_id       = auth()->user()->id;
                                    $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                    $keyword->status        = 'assigned';
                                    $keyword->save();

                                    $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                    if (Helper::app_config('keyword_notification_email')) {
                                        $admin = User::find(1);
                                        $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                    }

                                    $user = auth()->user();

                                    if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                        $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                    }

                                    return redirect()->route('customer.keywords.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                        } catch (ApiErrorException $e) {
                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => $e->getMessage(),
                            ]);
                        }

                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);

                case PaymentMethods::TYPE_2CHECKOUT:
                case PaymentMethods::TYPE_PAYU:
                case PaymentMethods::TYPE_COINPAYMENTS:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', $payment_method)->first();

                    if ($paymentMethod) {
                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $keyword->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $keyword->price,
                            'type'           => Invoices::TYPE_KEYWORD,
                            'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                            'transaction_id' => $keyword->uid,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current                = Carbon::now();
                            $keyword->user_id       = auth()->user()->id;
                            $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                            $keyword->status        = 'assigned';
                            $keyword->save();

                            $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                            if (Helper::app_config('keyword_notification_email')) {
                                $admin = User::find(1);
                                $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                            }

                            $user = auth()->user();

                            if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                            }

                            return redirect()->route('customer.keywords.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_PAYNOW:
                    $pollurl = Session::get('paynow_poll_url');
                    if (isset($pollurl)) {
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYNOW)->first();

                        if ($paymentMethod) {
                            $credentials = json_decode($paymentMethod->options);

                            $paynow = new Paynow(
                                $credentials->integration_id,
                                $credentials->integration_key,
                                route('customer.callback.paynow'),
                                route('customer.keywords.payment_success', $keyword->uid)
                            );

                            try {
                                $response = $paynow->pollTransaction($pollurl);

                                if ($response->paid()) {
                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $keyword->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $keyword->price,
                                        'type'           => Invoices::TYPE_KEYWORD,
                                        'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                        'transaction_id' => $response->reference(),
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                = Carbon::now();
                                        $keyword->user_id       = auth()->user()->id;
                                        $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                        $keyword->status        = 'assigned';
                                        $keyword->save();

                                        $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                        if (Helper::app_config('keyword_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                        }

                                        $user = auth()->user();

                                        if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                            $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                        }

                                        return redirect()->route('customer.keywords.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                            } catch (Exception $ex) {
                                return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                    'status'  => 'error',
                                    'message' => $ex->getMessage(),
                                ]);
                            }


                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'info',
                                'message' => __('locale.sender_id.payment_cancelled'),
                            ]);
                        }

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);
                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                case PaymentMethods::TYPE_INSTAMOJO:
                    $payment_request_id = Session::get('payment_request_id');

                    if ($request->get('payment_request_id') == $payment_request_id) {
                        if ($request->get('payment_status') == 'Completed') {

                            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_INSTAMOJO)->first();

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $keyword->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $keyword->price,
                                'type'           => Invoices::TYPE_KEYWORD,
                                'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                'transaction_id' => $request->get('payment_id'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                = Carbon::now();
                                $keyword->user_id       = auth()->user()->id;
                                $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                $keyword->status        = 'assigned';
                                $keyword->save();

                                $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                if (Helper::app_config('keyword_notification_email')) {
                                    $admin = User::find(1);
                                    $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                }

                                $user = auth()->user();

                                if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                    $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                }

                                return redirect()->route('customer.keywords.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        }

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'info',
                            'message' => $request->get('payment_status'),
                        ]);
                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'info',
                        'message' => __('locale.payment_gateways.payment_info_not_found'),
                    ]);

                case PaymentMethods::TYPE_PAYUMONEY:

                    $status      = $request->status;
                    $firstname   = $request->firstname;
                    $amount      = $request->amount;
                    $txnid       = $request->get('txnid');
                    $posted_hash = $request->hash;
                    $key         = $request->key;
                    $productinfo = $request->productinfo;
                    $email       = $request->email;
                    $salt        = "";

                    // Salt should be same Post Request
                    if (isset($request->additionalCharges)) {
                        $additionalCharges = $request->additionalCharges;
                        $retHashSeq        = $additionalCharges . '|' . $salt . '|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
                    } else {
                        $retHashSeq = $salt . '|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
                    }
                    $hash = hash("sha512", $retHashSeq);
                    if ($hash != $posted_hash) {
                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'info',
                            'message' => __('locale.exceptions.invalid_action'),
                        ]);
                    }

                    if ($status == 'Completed') {

                        $paymentMethod = PaymentMethods::where('status', true)->where('type', 'payumoney')->first();

                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $keyword->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $keyword->price,
                            'type'           => Invoices::TYPE_KEYWORD,
                            'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                            'transaction_id' => $txnid,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current                = Carbon::now();
                            $keyword->user_id       = auth()->user()->id;
                            $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                            $keyword->status        = 'assigned';
                            $keyword->save();

                            $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                            if (Helper::app_config('keyword_notification_email')) {
                                $admin = User::find(1);
                                $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                            }

                            $user = auth()->user();

                            if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                            }

                            return redirect()->route('customer.keywords.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }


                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => $status,
                    ]);

                case PaymentMethods::TYPE_DIRECTPAYONLINE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_DIRECTPAYONLINE)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        if ($credentials->environment == 'production') {
                            $payment_url = 'https://secure.3gdirectpay.com';
                        } else {
                            $payment_url = 'https://secure1.sandbox.directpay.online';
                        }

                        $companyToken     = $credentials->company_token;
                        $TransactionToken = $request->get('TransactionToken');

                        $postXml = <<<POSTXML
<?xml version="1.0" encoding="utf-8"?>
        <API3G>
          <CompanyToken>$companyToken</CompanyToken>
          <Request>verifyToken</Request>
          <TransactionToken>$TransactionToken</TransactionToken>
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
                        curl_close($curl);

                        if ($response != '') {
                            $xml = new SimpleXMLElement($response);

                            // Check if token was created successfully
                            if ($xml->xpath('Result')[0] != '000') {
                                return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                    'status'  => 'info',
                                    'message' => __('locale.exceptions.invalid_action'),
                                ]);
                            }

                            if (isset($request->TransID) && isset($request->CCDapproval)) {
                                $invoice_exist = Invoices::where('transaction_id', $request->TransID)->first();
                                if ( ! $invoice_exist) {
                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $keyword->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $keyword->price,
                                        'type'           => Invoices::TYPE_KEYWORD,
                                        'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                        'transaction_id' => $request->TransID,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                = Carbon::now();
                                        $keyword->user_id       = auth()->user()->id;
                                        $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                        $keyword->status        = 'assigned';
                                        $keyword->save();

                                        $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                        if (Helper::app_config('keyword_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                        }

                                        $user = auth()->user();

                                        if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                            $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                        }

                                        return redirect()->route('customer.keywords.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                        }
                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);

                case PaymentMethods::TYPE_PAYGATEGLOBAL:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYGATEGLOBAL)->first();

                    if ($paymentMethod) {

                        $parameters = [
                            'auth_token' => $paymentMethod->api_key,
                            'identify'   => $request->get('identify'),
                        ];

                        try {

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, 'https://paygateglobal.com/api/v2/status');
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($ch);
                            curl_close($ch);

                            $get_response = json_decode($response, true);

                            if (isset($get_response) && is_array($get_response) && array_key_exists('status', $get_response)) {
                                if ($get_response['success'] == 0) {

                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $keyword->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $keyword->price,
                                        'type'           => Invoices::TYPE_KEYWORD,
                                        'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                        'transaction_id' => $request->tx_reference,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                = Carbon::now();
                                        $keyword->user_id       = auth()->user()->id;
                                        $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                        $keyword->status        = 'assigned';
                                        $keyword->save();

                                        $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                        if (Helper::app_config('keyword_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                        }

                                        $user = auth()->user();

                                        if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                            $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                        }

                                        return redirect()->route('customer.keywords.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                                return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                    'status'  => 'info',
                                    'message' => 'Waiting for administrator approval',
                                ]);
                            }

                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        } catch (Exception $e) {
                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => $e->getMessage(),
                            ]);
                        }
                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_ORANGEMONEY:

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_ORANGEMONEY)->first();

                    if (isset($request->status)) {
                        if ($request->status == 'SUCCESS') {

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $keyword->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $keyword->price,
                                'type'           => Invoices::TYPE_KEYWORD,
                                'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                'transaction_id' => $request->get('txnid'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                = Carbon::now();
                                $keyword->user_id       = auth()->user()->id;
                                $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                $keyword->status        = 'assigned';
                                $keyword->save();

                                $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                if (Helper::app_config('keyword_notification_email')) {
                                    $admin = User::find(1);
                                    $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                }

                                $user = auth()->user();

                                if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                    $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                }

                                return redirect()->route('customer.keywords.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        }

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'info',
                            'message' => $request->status,
                        ]);
                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_CINETPAY:

                    $paymentMethod  = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_CINETPAY)->first();
                    $credentials    = json_decode($paymentMethod->options);
                    $transaction_id = $request->transaction_id;

                    $payment_data = [
                        'apikey'         => $credentials->api_key,
                        'site_id'        => $credentials->site_id,
                        'transaction_id' => $transaction_id,
                    ];


                    try {

                        $curl = curl_init();

                        curl_setopt_array($curl, [
                            CURLOPT_URL            => $credentials->payment_url . '/check',
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
                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => 'Php curl show false value. Please contact with your provider',
                            ]);

                        }

                        if ($err) {
                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => $err,
                            ]);
                        }

                        $result = json_decode($response, true);


                        if (is_array($result) && array_key_exists('code', $result) && array_key_exists('message', $result)) {
                            if ($result['code'] == '00') {
                                $invoice = Invoices::create([
                                    'user_id'        => auth()->user()->id,
                                    'currency_id'    => $keyword->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $keyword->price,
                                    'type'           => Invoices::TYPE_KEYWORD,
                                    'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                    'transaction_id' => $transaction_id,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    $current                = Carbon::now();
                                    $keyword->user_id       = auth()->user()->id;
                                    $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                    $keyword->status        = 'assigned';
                                    $keyword->save();

                                    $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                    if (Helper::app_config('keyword_notification_email')) {
                                        $admin = User::find(1);
                                        $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                    }

                                    $user = auth()->user();

                                    if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                        $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                    }

                                    return redirect()->route('customer.keywords.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => $result['message'],
                            ]);
                        }

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'       => 'error',
                            'redirect_url' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    } catch (Exception $ex) {

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'       => 'error',
                            'redirect_url' => $ex->getMessage(),
                        ]);
                    }

                case PaymentMethods::TYPE_PAYHERELK:

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYHERELK)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        try {

                            if ($credentials->environment == 'sandbox') {
                                $auth_url    = 'https://sandbox.payhere.lk/merchant/v1/oauth/token';
                                $payment_url = 'https://sandbox.payhere.lk/merchant/v1/payment/search';
                            } else {
                                $auth_url    = 'https://payhere.lk/merchant/v1/oauth/token';
                                $payment_url = 'https://payhere.lk/merchant/v1/payment/search';
                            }

                            $headers = [
                                'Content-Type: application/x-www-form-urlencoded',
                                'Authorization: Basic ' . base64_encode("$credentials->app_id:$credentials->app_secret"),
                            ];

                            $ch = curl_init();

                            curl_setopt($ch, CURLOPT_URL, $auth_url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                            $auth_data = curl_exec($ch);

                            if (curl_errno($ch)) {

                                return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                    'status'  => 'error',
                                    'message' => curl_error($ch),
                                ]);
                            }

                            curl_close($ch);

                            $result = json_decode($auth_data, true);

                            if (is_array($result)) {
                                if (array_key_exists('error_description', $result)) {

                                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                        'status'  => 'error',
                                        'message' => $result['error_description'],
                                    ]);
                                }


                                $headers = [
                                    'Content-Type: application/json',
                                    'Authorization: Bearer ' . $result['access_token'],
                                ];

                                $curl = curl_init();

                                curl_setopt($curl, CURLOPT_URL, $payment_url);
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($curl, CURLOPT_POST, 1);
                                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['order_id' => $request->get('order_id')]));
                                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

                                $payment_data = curl_exec($curl);

                                if (curl_errno($curl)) {

                                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                        'status'  => 'error',
                                        'message' => curl_error($curl),
                                    ]);
                                }

                                curl_close($curl);

                                $result = json_decode($payment_data, true);

                                if (is_array($result)) {
                                    if (array_key_exists('error_description', $result)) {

                                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                            'status'  => 'error',
                                            'message' => $result['error_description'],
                                        ]);
                                    }

                                    if (array_key_exists('status', $result) && $result['status'] == '-1') {
                                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                            'status'  => 'error',
                                            'message' => $result['msg'],
                                        ]);
                                    }

                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $keyword->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $keyword->price,
                                        'type'           => Invoices::TYPE_KEYWORD,
                                        'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                        'transaction_id' => $request->get('order_id'),
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                = Carbon::now();
                                        $keyword->user_id       = auth()->user()->id;
                                        $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                        $keyword->status        = 'assigned';
                                        $keyword->save();

                                        $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                        if (Helper::app_config('keyword_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                        }

                                        $user = auth()->user();

                                        if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                            $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                        }

                                        return redirect()->route('customer.keywords.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                                return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        } catch (Exception $exception) {
                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }
                    }
                    break;

                case PaymentMethods::TYPE_MOLLIE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_MOLLIE)->first();

                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        $mollie = new MollieApiClient();
                        $mollie->setApiKey($credentials->api_key);

                        $payment_id = Session::get('payment_id');

                        $payment = $mollie->payments->get($payment_id);

                        if ($payment->isPaid()) {

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $keyword->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $keyword->price,
                                'type'           => Invoices::TYPE_KEYWORD,
                                'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                'transaction_id' => $payment_id,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                = Carbon::now();
                                $keyword->user_id       = auth()->user()->id;
                                $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                $keyword->status        = 'assigned';
                                $keyword->save();

                                $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                return redirect()->route('customer.keywords.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        }

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);

                case PaymentMethods::TYPE_SELCOMMOBILE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_SELCOMMOBILE)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        $orderStatusArray = [
                            "order_id" => $keyword->uid,
                        ];

                        $client = new Client($credentials->payment_url, $credentials->api_key, $credentials->api_secret);

                        // path relative to base url
                        $orderStatusPath = "/checkout/order-status";

                        // create order minimal
                        try {
                            $response = $client->getFunc($orderStatusPath, $orderStatusArray);

                            if (isset($response) && is_array($response) && array_key_exists('data', $response) && array_key_exists('result', $response)) {
                                if ($response['result'] == 'SUCCESS' && array_key_exists('0', $response['data']) && $response['data'][0]['payment_status'] == 'COMPLETED') {

                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $keyword->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $keyword->price,
                                        'type'           => Invoices::TYPE_KEYWORD,
                                        'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                        'transaction_id' => $response['data'][0]['transid'],
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        $current                = Carbon::now();
                                        $keyword->user_id       = auth()->user()->id;
                                        $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                        $keyword->status        = 'assigned';
                                        $keyword->save();

                                        $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                        if (Helper::app_config('keyword_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                        }

                                        $user = auth()->user();

                                        if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                            $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                        }

                                        return redirect()->route('customer.keywords.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                } else {
                                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                        'status'  => 'error',
                                        'message' => $response['message'],
                                    ]);
                                }
                            }

                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => $response,
                            ]);

                        } catch (Exception $exception) {
                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }

                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_MPGS:

                    $order_id = $request->input('order_id');

                    if (empty($order_id)) {
                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => 'Payment error: Invalid transaction.',
                        ]);
                    }

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_MPGS)->first();


                    if ( ! $paymentMethod) {
                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);

                    }

                    $credentials = json_decode($paymentMethod->options);

                    $config = [
                        'payment_url'             => $credentials->payment_url,
                        'api_version'             => $credentials->api_version,
                        'merchant_id'             => $credentials->merchant_id,
                        'authentication_password' => $credentials->authentication_password,
                    ];


                    $paymentData = [
                        'order_id' => $order_id,
                    ];

                    $mpgs   = new MPGS($config, $paymentData);
                    $result = $mpgs->process_response();

                    if (isset($result->getData()->status) && isset($result->getData()->message)) {
                        if ($result->getData()->status == 'success') {

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $keyword->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $keyword->price,
                                'type'           => Invoices::TYPE_KEYWORD,
                                'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                'transaction_id' => $result->getData()->transaction_id,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                = Carbon::now();
                                $keyword->user_id       = auth()->user()->id;
                                $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                $keyword->status        = 'assigned';
                                $keyword->save();

                                $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                return redirect()->route('customer.keywords.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }


                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => $result->getData()->message,
                        ]);
                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

            }

            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);

        }

        /**
         * purchase Keyword by authorize net
         *
         * @param Keywords $keyword
         * @param Request  $request
         *
         * @return RedirectResponse
         */
        public function authorizeNetKeyword(Keywords $keyword, Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'authorize_net')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {

                    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
                    $merchantAuthentication->setName($credentials->login_id);
                    $merchantAuthentication->setTransactionKey($credentials->transaction_key);

                    // Set the transaction's refId
                    $refId      = 'ref' . time();
                    $cardNumber = preg_replace('/\s+/', '', $request->cardNumber);

                    // Create the payment data for a credit card
                    $creditCard = new AnetAPI\CreditCardType();
                    $creditCard->setCardNumber($cardNumber);
                    $creditCard->setExpirationDate($request->expiration_year . "-" . $request->expiration_month);
                    $creditCard->setCardCode($request->cvv);


                    // Add the payment data to a paymentType object
                    $paymentOne = new AnetAPI\PaymentType();
                    $paymentOne->setCreditCard($creditCard);

                    // Create order information
                    $order = new AnetAPI\OrderType();
                    $order->setInvoiceNumber($keyword->uid);
                    $order->setDescription(__('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name);


                    // Set the customer's Bill To address
                    $customerAddress = new AnetAPI\CustomerAddressType();
                    $customerAddress->setFirstName(auth()->user()->first_name);
                    $customerAddress->setLastName(auth()->user()->last_name);

                    // Set the customer's identifying information
                    $customerData = new AnetAPI\CustomerDataType();
                    $customerData->setType("individual");
                    $customerData->setId(auth()->user()->id);
                    $customerData->setEmail(auth()->user()->email);


                    // Create a TransactionRequestType object and add the previous objects to it
                    $transactionRequestType = new AnetAPI\TransactionRequestType();
                    $transactionRequestType->setTransactionType("authCaptureTransaction");
                    $transactionRequestType->setAmount($keyword->price);
                    $transactionRequestType->setOrder($order);
                    $transactionRequestType->setPayment($paymentOne);
                    $transactionRequestType->setBillTo($customerAddress);
                    $transactionRequestType->setCustomer($customerData);


                    // Assemble the complete transaction request
                    $requests = new AnetAPI\CreateTransactionRequest();
                    $requests->setMerchantAuthentication($merchantAuthentication);
                    $requests->setRefId($refId);
                    $requests->setTransactionRequest($transactionRequestType);

                    // Create the controller and get the response
                    $controller = new AnetController\CreateTransactionController($requests);
                    if ($credentials->environment == 'sandbox') {
                        $result = $controller->executeWithApiResponse(ANetEnvironment::SANDBOX);
                    } else {
                        $result = $controller->executeWithApiResponse(ANetEnvironment::PRODUCTION);
                    }

                    if (isset($result) && $result->getMessages()->getResultCode() == 'Ok' && $result->getTransactionResponse()) {

                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $keyword->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $keyword->price,
                            'type'           => Invoices::TYPE_KEYWORD,
                            'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                            'transaction_id' => $result->getRefId(),
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current                = Carbon::now();
                            $keyword->user_id       = auth()->user()->id;
                            $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                            $keyword->status        = 'assigned';
                            $keyword->save();

                            $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                            if (Helper::app_config('keyword_notification_email')) {
                                $admin = User::find(1);
                                $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                            }

                            $user = auth()->user();

                            if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                            }

                            return redirect()->route('customer.keywords.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * razorpay Keywords payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function razorpayKeywords(Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'razorpay')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);
                $order_id    = Session::get('razorpay_order_id');

                if (isset($order_id) && empty($request->razorpay_payment_id) === false) {

                    $keyword = Keywords::where('transaction_id', $order_id)->first();

                    if ($keyword) {
                        $api        = new Api($credentials->key_id, $credentials->key_secret);
                        $attributes = [
                            'razorpay_order_id'   => $order_id,
                            'razorpay_payment_id' => $request->razorpay_payment_id,
                            'razorpay_signature'  => $request->razorpay_signature,
                        ];

                        try {

                            $response = $api->utility->verifyPaymentSignature($attributes);

                            if ($response) {
                                $invoice = Invoices::create([
                                    'user_id'        => auth()->user()->id,
                                    'currency_id'    => $keyword->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $keyword->price,
                                    'type'           => Invoices::TYPE_KEYWORD,
                                    'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                    'transaction_id' => $order_id,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    $current                = Carbon::now();
                                    $keyword->user_id       = auth()->user()->id;
                                    $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                    $keyword->status        = 'assigned';
                                    $keyword->save();

                                    $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                    if (Helper::app_config('keyword_notification_email')) {
                                        $admin = User::find(1);
                                        $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                    }

                                    $user = auth()->user();

                                    if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                        $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                    }

                                    return redirect()->route('customer.keywords.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        } catch (SignatureVerificationError $exception) {

                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }
                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * sslcommerz keyword payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function sslcommerzKeywords(Request $request): RedirectResponse
        {

            if (isset($request->status)) {
                if ($request->status == 'VALID') {
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'sslcommerz')->first();
                    if ($paymentMethod) {

                        $keyword = Keywords::findByUid($request->get('tran_id'));

                        if ($keyword) {
                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $keyword->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $keyword->price,
                                'type'           => Invoices::TYPE_KEYWORD,
                                'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                'transaction_id' => $request->get('bank_tran_id'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                = Carbon::now();
                                $keyword->user_id       = auth()->user()->id;
                                $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                $keyword->status        = 'assigned';
                                $keyword->save();

                                $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                if (Helper::app_config('keyword_notification_email')) {
                                    $admin = User::find(1);
                                    $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                }

                                $user = auth()->user();

                                if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                    $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                }

                                return redirect()->route('customer.keywords.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => $request->status,
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * aamarpay keyword payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function aamarpayKeywords(Request $request): RedirectResponse
        {

            if (isset($request->pay_status) && isset($request->mer_txnid)) {

                $keyword = Keywords::findByUid($request->mer_txnid);

                if ($request->pay_status == 'Successful') {
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'aamarpay')->first();
                    if ($paymentMethod) {

                        if ($keyword) {
                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $keyword->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $keyword->price,
                                'type'           => Invoices::TYPE_KEYWORD,
                                'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                'transaction_id' => $request->pg_txnid,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                = Carbon::now();
                                $keyword->user_id       = auth()->user()->id;
                                $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                $keyword->status        = 'assigned';
                                $keyword->save();

                                $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                if (Helper::app_config('keyword_notification_email')) {
                                    $admin = User::find(1);
                                    $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                }

                                $user = auth()->user();

                                if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                    $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                }

                                return redirect()->route('customer.keywords.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                }

                return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                    'status'  => 'error',
                    'message' => $request->pay_status,
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * successful subscription purchase
         *
         * @param Plan    $plan
         * @param Request $request
         *
         * @return RedirectResponse
         * @throws Exception
         */
        public function successfulSubscriptionPayment(Plan $plan, Request $request): RedirectResponse
        {
            $payment_method = Session::get('payment_method');
            if ($payment_method == null) {
                $payment_method = $request->get('payment_method');
            }

            switch ($payment_method) {

                case PaymentMethods::TYPE_PAYPAL:

                    $token = Session::get('paypal_payment_id');
                    if ($request->get('token') == $token) {
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYPAL)->first();

                        if ($paymentMethod) {
                            $credentials = json_decode($paymentMethod->options);

                            if ($credentials->environment == 'sandbox') {
                                $environment = new SandboxEnvironment($credentials->client_id, $credentials->secret);
                            } else {
                                $environment = new ProductionEnvironment($credentials->client_id, $credentials->secret);
                            }

                            $client = new PayPalHttpClient($environment);

                            $request = new OrdersCaptureRequest($token);
                            $request->prefer('return=representation');

                            try {
                                // Call API with your client and get a response for your call
                                $response = $client->execute($request);

                                if ($response->statusCode == '201' && $response->result->status == 'COMPLETED' && isset($response->id)) {
                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $plan->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $plan->price,
                                        'type'           => Invoices::TYPE_SUBSCRIPTION,
                                        'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                        'transaction_id' => $response->id,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        if (Auth::user()->customer->activeSubscription()) {
                                            Auth::user()->customer->activeSubscription()->cancelNow();
                                        }

                                        if (Auth::user()->customer->subscription) {
                                            $subscription          = Auth::user()->customer->subscription;
                                            $get_options           = json_decode($subscription->options, true);
                                            $output                = array_replace($get_options, [
                                                'send_warning' => false,
                                            ]);
                                            $subscription->options = json_encode($output);

                                        } else {
                                            $subscription           = new Subscription();
                                            $subscription->user_id  = Auth::user()->id;
                                            $subscription->start_at = Carbon::now();
                                        }

                                        $subscription->status                 = Subscription::STATUS_ACTIVE;
                                        $subscription->plan_id                = $plan->getBillableId();
                                        $subscription->end_period_last_days   = '10';
                                        $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                        $subscription->end_at                 = null;
                                        $subscription->end_by                 = null;
                                        $subscription->payment_method_id      = $paymentMethod->id;

                                        $subscription->save();

                                        // add transaction
                                        $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                            'end_at'                 => $subscription->end_at,
                                            'current_period_ends_at' => $subscription->current_period_ends_at,
                                            'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                            'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                            'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                        ]);

                                        // add log
                                        $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                            'plan'  => $subscription->plan->getBillableName(),
                                            'price' => $subscription->plan->getBillableFormattedPrice(),
                                        ]);

                                        $user = User::find(auth()->user()->id);

                                        if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                            $user->sms_unit = $plan->getOption('sms_max');
                                        } else {
                                            if ($plan->getOption('add_previous_balance') == 'yes') {
                                                $user->sms_unit += $plan->getOption('sms_max');
                                            } else {
                                                $user->sms_unit = $plan->getOption('sms_max');
                                            }
                                        }

                                        $user->save();

                                        //Add default Sender id
                                        $this->planSenderID($plan, $user);

                                        $this->createNotification('plan', $plan->name, auth()->user()->displayName());

                                        if (Helper::app_config('subscription_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                        }

                                        if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                            $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                        }

                                        return redirect()->route('customer.subscriptions.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                            } catch (Exception $ex) {
                                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                    'status'  => 'error',
                                    'message' => $ex->getMessage(),
                                ]);
                            }


                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'info',
                                'message' => __('locale.sender_id.payment_cancelled'),
                            ]);
                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);
                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                case PaymentMethods::TYPE_STRIPE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_STRIPE)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);
                        $secret_key  = $credentials->secret_key;
                        $session_id  = Session::get('session_id');

                        $stripe = new StripeClient($secret_key);

                        try {
                            $response = $stripe->checkout->sessions->retrieve($session_id);

                            if ($response->payment_status == 'paid') {
                                $invoice = Invoices::create([
                                    'user_id'        => auth()->user()->id,
                                    'currency_id'    => $plan->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $plan->price,
                                    'type'           => Invoices::TYPE_SUBSCRIPTION,
                                    'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                    'transaction_id' => $response->payment_intent,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    if (Auth::user()->customer->activeSubscription()) {
                                        Auth::user()->customer->activeSubscription()->cancelNow();
                                    }

                                    if (Auth::user()->customer->subscription) {
                                        $subscription = Auth::user()->customer->subscription;

                                        $get_options           = json_decode($subscription->options, true);
                                        $output                = array_replace($get_options, [
                                            'send_warning' => false,
                                        ]);
                                        $subscription->options = json_encode($output);

                                    } else {
                                        $subscription           = new Subscription();
                                        $subscription->user_id  = Auth::user()->id;
                                        $subscription->start_at = Carbon::now();
                                    }

                                    $subscription->status                 = Subscription::STATUS_ACTIVE;
                                    $subscription->plan_id                = $plan->getBillableId();
                                    $subscription->end_period_last_days   = '10';
                                    $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                    $subscription->end_at                 = null;
                                    $subscription->end_by                 = null;
                                    $subscription->payment_method_id      = $paymentMethod->id;
                                    $subscription->save();

                                    // add transaction
                                    $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                        'end_at'                 => $subscription->end_at,
                                        'current_period_ends_at' => $subscription->current_period_ends_at,
                                        'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                        'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                        'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                    ]);

                                    // add log
                                    $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                        'plan'  => $subscription->plan->getBillableName(),
                                        'price' => $subscription->plan->getBillableFormattedPrice(),
                                    ]);


                                    $user = User::find(auth()->user()->id);

                                    if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                        $user->sms_unit = $plan->getOption('sms_max');
                                    } else {
                                        if ($plan->getOption('add_previous_balance') == 'yes') {
                                            $user->sms_unit += $plan->getOption('sms_max');
                                        } else {
                                            $user->sms_unit = $plan->getOption('sms_max');
                                        }
                                    }

                                    $user->save();

                                    //Add default Sender id
                                    $this->planSenderID($plan, $user);

                                    $this->createNotification('plan', $plan->name, auth()->user()->displayName());

                                    if (Helper::app_config('subscription_notification_email')) {
                                        $admin = User::find(1);
                                        $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                    }

                                    if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                        $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                    }

                                    return redirect()->route('customer.subscriptions.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                        } catch (ApiErrorException $e) {
                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => $e->getMessage(),
                            ]);
                        }

                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);

                case PaymentMethods::TYPE_2CHECKOUT:
                case PaymentMethods::TYPE_PAYU:
                case PaymentMethods::TYPE_COINPAYMENTS:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', $payment_method)->first();

                    if ($paymentMethod) {
                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $plan->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $plan->price,
                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                            'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                            'transaction_id' => $plan->uid,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            if (Auth::user()->customer->activeSubscription()) {
                                Auth::user()->customer->activeSubscription()->cancelNow();
                            }

                            if (Auth::user()->customer->subscription) {
                                $subscription = Auth::user()->customer->subscription;

                                $get_options           = json_decode($subscription->options, true);
                                $output                = array_replace($get_options, [
                                    'send_warning' => false,
                                ]);
                                $subscription->options = json_encode($output);

                            } else {
                                $subscription           = new Subscription();
                                $subscription->user_id  = Auth::user()->id;
                                $subscription->start_at = Carbon::now();
                            }

                            $subscription->status                 = Subscription::STATUS_ACTIVE;
                            $subscription->plan_id                = $plan->getBillableId();
                            $subscription->end_period_last_days   = '10';
                            $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                            $subscription->end_at                 = null;
                            $subscription->end_by                 = null;
                            $subscription->payment_method_id      = $paymentMethod->id;
                            $subscription->save();

                            // add transaction
                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                'end_at'                 => $subscription->end_at,
                                'current_period_ends_at' => $subscription->current_period_ends_at,
                                'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                            ]);

                            // add log
                            $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                'plan'  => $subscription->plan->getBillableName(),
                                'price' => $subscription->plan->getBillableFormattedPrice(),
                            ]);


                            $user = User::find(auth()->user()->id);

                            if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                $user->sms_unit = $plan->getOption('sms_max');
                            } else {
                                if ($plan->getOption('add_previous_balance') == 'yes') {
                                    $user->sms_unit += $plan->getOption('sms_max');
                                } else {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                }
                            }

                            $user->save();

                            //Add default Sender id
                            $this->planSenderID($plan, $user);

                            $this->createNotification('plan', $plan->name, auth()->user()->displayName());

                            if (Helper::app_config('subscription_notification_email')) {
                                $admin = User::find(1);
                                $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                            }

                            if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                            }

                            return redirect()->route('customer.subscriptions.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_PAYNOW:
                    $pollurl = Session::get('paynow_poll_url');
                    if (isset($pollurl)) {
                        $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYNOW)->first();

                        if ($paymentMethod) {
                            $credentials = json_decode($paymentMethod->options);

                            $paynow = new Paynow(
                                $credentials->integration_id,
                                $credentials->integration_key,
                                route('customer.callback.paynow'),
                                route('customer.subscriptions.payment_success', $plan->uid)
                            );

                            try {
                                $response = $paynow->pollTransaction($pollurl);

                                if ($response->paid()) {

                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $plan->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $plan->price,
                                        'type'           => Invoices::TYPE_SUBSCRIPTION,
                                        'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                        'transaction_id' => $response->reference(),
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        if (Auth::user()->customer->activeSubscription()) {
                                            Auth::user()->customer->activeSubscription()->cancelNow();
                                        }

                                        if (Auth::user()->customer->subscription) {
                                            $subscription = Auth::user()->customer->subscription;

                                            $get_options           = json_decode($subscription->options, true);
                                            $output                = array_replace($get_options, [
                                                'send_warning' => false,
                                            ]);
                                            $subscription->options = json_encode($output);

                                        } else {
                                            $subscription           = new Subscription();
                                            $subscription->user_id  = Auth::user()->id;
                                            $subscription->start_at = Carbon::now();
                                        }

                                        $subscription->status                 = Subscription::STATUS_ACTIVE;
                                        $subscription->plan_id                = $plan->getBillableId();
                                        $subscription->end_period_last_days   = '10';
                                        $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                        $subscription->end_at                 = null;
                                        $subscription->end_by                 = null;
                                        $subscription->payment_method_id      = $paymentMethod->id;
                                        $subscription->save();

                                        // add transaction
                                        $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                            'end_at'                 => $subscription->end_at,
                                            'current_period_ends_at' => $subscription->current_period_ends_at,
                                            'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                            'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                            'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                        ]);

                                        // add log
                                        $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                            'plan'  => $subscription->plan->getBillableName(),
                                            'price' => $subscription->plan->getBillableFormattedPrice(),
                                        ]);


                                        $user = User::find(auth()->user()->id);

                                        if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                            $user->sms_unit = $plan->getOption('sms_max');
                                        } else {
                                            if ($plan->getOption('add_previous_balance') == 'yes') {
                                                $user->sms_unit += $plan->getOption('sms_max');
                                            } else {
                                                $user->sms_unit = $plan->getOption('sms_max');
                                            }
                                        }

                                        $user->save();

                                        //Add default Sender id
                                        $this->planSenderID($plan, $user);

                                        $this->createNotification('plan', $plan->name, auth()->user()->displayName());

                                        if (Helper::app_config('subscription_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                        }

                                        if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                            $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                        }

                                        return redirect()->route('customer.subscriptions.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);
                                }

                            } catch (Exception $ex) {
                                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                    'status'  => 'error',
                                    'message' => $ex->getMessage(),
                                ]);
                            }


                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'info',
                                'message' => __('locale.sender_id.payment_cancelled'),
                            ]);
                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);
                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                case PaymentMethods::TYPE_INSTAMOJO:
                    $payment_request_id = Session::get('payment_request_id');

                    if ($request->get('payment_request_id') == $payment_request_id) {
                        if ($request->get('payment_status') == 'Completed') {

                            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_INSTAMOJO)->first();

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $plan->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $plan->price,
                                'type'           => Invoices::TYPE_SUBSCRIPTION,
                                'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                'transaction_id' => $request->get('payment_id'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                if (Auth::user()->customer->activeSubscription()) {
                                    Auth::user()->customer->activeSubscription()->cancelNow();
                                }

                                if (Auth::user()->customer->subscription) {
                                    $subscription = Auth::user()->customer->subscription;

                                    $get_options           = json_decode($subscription->options, true);
                                    $output                = array_replace($get_options, [
                                        'send_warning' => false,
                                    ]);
                                    $subscription->options = json_encode($output);

                                } else {
                                    $subscription           = new Subscription();
                                    $subscription->user_id  = Auth::user()->id;
                                    $subscription->start_at = Carbon::now();
                                }

                                $subscription->status                 = Subscription::STATUS_ACTIVE;
                                $subscription->plan_id                = $plan->getBillableId();
                                $subscription->end_period_last_days   = '10';
                                $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                $subscription->end_at                 = null;
                                $subscription->end_by                 = null;
                                $subscription->payment_method_id      = $paymentMethod->id;
                                $subscription->save();

                                // add transaction
                                $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                    'end_at'                 => $subscription->end_at,
                                    'current_period_ends_at' => $subscription->current_period_ends_at,
                                    'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                    'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                    'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                ]);

                                // add log
                                $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                    'plan'  => $subscription->plan->getBillableName(),
                                    'price' => $subscription->plan->getBillableFormattedPrice(),
                                ]);


                                $user = User::find(auth()->user()->id);

                                if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                } else {
                                    if ($plan->getOption('add_previous_balance') == 'yes') {
                                        $user->sms_unit += $plan->getOption('sms_max');
                                    } else {
                                        $user->sms_unit = $plan->getOption('sms_max');
                                    }
                                }

                                $user->save();

                                $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                                //Add default Sender id
                                $this->planSenderID($plan, $user);

                                if (Helper::app_config('subscription_notification_email')) {
                                    $admin = User::find(1);
                                    $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                }

                                if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                    $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                }

                                return redirect()->route('customer.subscriptions.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'info',
                            'message' => $request->get('payment_status'),
                        ]);
                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'info',
                        'message' => __('locale.payment_gateways.payment_info_not_found'),
                    ]);

                case PaymentMethods::TYPE_PAYUMONEY:

                    $status      = $request->status;
                    $firstname   = $request->firstname;
                    $amount      = $request->amount;
                    $txnid       = $request->get('txnid');
                    $posted_hash = $request->hash;
                    $key         = $request->key;
                    $productinfo = $request->productinfo;
                    $email       = $request->email;
                    $salt        = "";

                    // Salt should be same Post Request
                    if (isset($request->additionalCharges)) {
                        $additionalCharges = $request->additionalCharges;
                        $retHashSeq        = $additionalCharges . '|' . $salt . '|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
                    } else {
                        $retHashSeq = $salt . '|' . $status . '|||||||||||' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
                    }
                    $hash = hash("sha512", $retHashSeq);
                    if ($hash != $posted_hash) {
                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'info',
                            'message' => __('locale.exceptions.invalid_action'),
                        ]);
                    }

                    if ($status == 'Completed') {

                        $paymentMethod = PaymentMethods::where('status', true)->where('type', 'payumoney')->first();


                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $plan->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $plan->price,
                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                            'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                            'transaction_id' => $txnid,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            if (Auth::user()->customer->activeSubscription()) {
                                Auth::user()->customer->activeSubscription()->cancelNow();
                            }

                            if (Auth::user()->customer->subscription) {
                                $subscription = Auth::user()->customer->subscription;

                                $get_options           = json_decode($subscription->options, true);
                                $output                = array_replace($get_options, [
                                    'send_warning' => false,
                                ]);
                                $subscription->options = json_encode($output);

                            } else {
                                $subscription           = new Subscription();
                                $subscription->user_id  = Auth::user()->id;
                                $subscription->start_at = Carbon::now();
                            }

                            $subscription->status                 = Subscription::STATUS_ACTIVE;
                            $subscription->plan_id                = $plan->getBillableId();
                            $subscription->end_period_last_days   = '10';
                            $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                            $subscription->end_at                 = null;
                            $subscription->end_by                 = null;
                            $subscription->payment_method_id      = $paymentMethod->id;
                            $subscription->save();

                            // add transaction
                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                'end_at'                 => $subscription->end_at,
                                'current_period_ends_at' => $subscription->current_period_ends_at,
                                'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                            ]);

                            // add log
                            $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                'plan'  => $subscription->plan->getBillableName(),
                                'price' => $subscription->plan->getBillableFormattedPrice(),
                            ]);


                            $user = User::find(auth()->user()->id);

                            if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                $user->sms_unit = $plan->getOption('sms_max');
                            } else {
                                if ($plan->getOption('add_previous_balance') == 'yes') {
                                    $user->sms_unit += $plan->getOption('sms_max');
                                } else {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                }
                            }

                            $user->save();

                            $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                            //Add default Sender id
                            $this->planSenderID($plan, $user);

                            if (Helper::app_config('subscription_notification_email')) {
                                $admin = User::find(1);
                                $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                            }

                            if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                            }

                            return redirect()->route('customer.subscriptions.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => $status,
                    ]);

                case PaymentMethods::TYPE_DIRECTPAYONLINE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_DIRECTPAYONLINE)->first();

                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        if ($credentials->environment == 'production') {
                            $payment_url = 'https://secure.3gdirectpay.com';
                        } else {
                            $payment_url = 'https://secure1.sandbox.directpay.online';
                        }

                        $companyToken     = $credentials->company_token;
                        $TransactionToken = $request->get('TransactionToken');

                        $postXml = <<<POSTXML
<?xml version="1.0" encoding="utf-8"?>
        <API3G>
          <CompanyToken>$companyToken</CompanyToken>
          <Request>verifyToken</Request>
          <TransactionToken>$TransactionToken</TransactionToken>
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
                        curl_close($curl);

                        if ($response != '') {
                            $xml = new SimpleXMLElement($response);

                            // Check if token was created successfully
                            if ($xml->xpath('Result')[0] != '000') {
                                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                    'status'  => 'info',
                                    'message' => __('locale.exceptions.invalid_action'),
                                ]);
                            }

                            if (isset($request->TransID) && isset($request->CCDapproval)) {
                                $invoice_exist = Invoices::where('transaction_id', $request->TransID)->first();
                                if ( ! $invoice_exist) {
                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $plan->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $plan->price,
                                        'type'           => Invoices::TYPE_SUBSCRIPTION,
                                        'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                        'transaction_id' => $request->TransID,
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        if (Auth::user()->customer->activeSubscription()) {
                                            Auth::user()->customer->activeSubscription()->cancelNow();
                                        }

                                        if (Auth::user()->customer->subscription) {
                                            $subscription = Auth::user()->customer->subscription;

                                            $get_options           = json_decode($subscription->options, true);
                                            $output                = array_replace($get_options, [
                                                'send_warning' => false,
                                            ]);
                                            $subscription->options = json_encode($output);

                                        } else {
                                            $subscription           = new Subscription();
                                            $subscription->user_id  = Auth::user()->id;
                                            $subscription->start_at = Carbon::now();
                                        }

                                        $subscription->status                 = Subscription::STATUS_ACTIVE;
                                        $subscription->plan_id                = $plan->getBillableId();
                                        $subscription->end_period_last_days   = '10';
                                        $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                        $subscription->end_at                 = null;
                                        $subscription->end_by                 = null;
                                        $subscription->payment_method_id      = $paymentMethod->id;
                                        $subscription->save();

                                        // add transaction
                                        $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                            'end_at'                 => $subscription->end_at,
                                            'current_period_ends_at' => $subscription->current_period_ends_at,
                                            'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                            'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                            'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                        ]);

                                        // add log
                                        $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                            'plan'  => $subscription->plan->getBillableName(),
                                            'price' => $subscription->plan->getBillableFormattedPrice(),
                                        ]);


                                        $user = User::find(auth()->user()->id);

                                        if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                            $user->sms_unit = $plan->getOption('sms_max');
                                        } else {
                                            if ($plan->getOption('add_previous_balance') == 'yes') {
                                                $user->sms_unit += $plan->getOption('sms_max');
                                            } else {
                                                $user->sms_unit = $plan->getOption('sms_max');
                                            }
                                        }

                                        $user->save();

                                        $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                                        //Add default Sender id
                                        $this->planSenderID($plan, $user);

                                        if (Helper::app_config('subscription_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                        }

                                        if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                            $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                        }

                                        return redirect()->route('customer.subscriptions.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }

                                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                                return redirect()->route('user.home')->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);

                            }

                        }
                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);

                case PaymentMethods::TYPE_PAYGATEGLOBAL:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYGATEGLOBAL)->first();

                    if ($paymentMethod) {

                        $parameters = [
                            'auth_token' => $payment_method->api_key,
                            'identify'   => $request->get('identify'),
                        ];

                        try {

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, 'https://paygateglobal.com/api/v2/status');
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($ch);
                            curl_close($ch);

                            $get_response = json_decode($response, true);

                            if (isset($get_response) && is_array($get_response) && array_key_exists('status', $get_response)) {
                                if ($get_response['success'] == 0) {
                                    $invoice_exist = Invoices::where('transaction_id', $request->tx_reference)->first();
                                    if ( ! $invoice_exist) {
                                        $invoice = Invoices::create([
                                            'user_id'        => auth()->user()->id,
                                            'currency_id'    => $plan->currency_id,
                                            'payment_method' => $paymentMethod->id,
                                            'amount'         => $plan->price,
                                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                                            'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                            'transaction_id' => $request->tx_reference,
                                            'status'         => Invoices::STATUS_PAID,
                                        ]);

                                        if ($invoice) {
                                            if (Auth::user()->customer->activeSubscription()) {
                                                Auth::user()->customer->activeSubscription()->cancelNow();
                                            }

                                            if (Auth::user()->customer->subscription) {
                                                $subscription = Auth::user()->customer->subscription;

                                                $get_options           = json_decode($subscription->options, true);
                                                $output                = array_replace($get_options, [
                                                    'send_warning' => false,
                                                ]);
                                                $subscription->options = json_encode($output);

                                            } else {
                                                $subscription           = new Subscription();
                                                $subscription->user_id  = Auth::user()->id;
                                                $subscription->start_at = Carbon::now();
                                            }

                                            $subscription->status                 = Subscription::STATUS_ACTIVE;
                                            $subscription->plan_id                = $plan->getBillableId();
                                            $subscription->end_period_last_days   = '10';
                                            $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                            $subscription->end_at                 = null;
                                            $subscription->end_by                 = null;
                                            $subscription->payment_method_id      = $paymentMethod->id;
                                            $subscription->save();

                                            // add transaction
                                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                                'end_at'                 => $subscription->end_at,
                                                'current_period_ends_at' => $subscription->current_period_ends_at,
                                                'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                                'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                                'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                            ]);

                                            // add log
                                            $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                                'plan'  => $subscription->plan->getBillableName(),
                                                'price' => $subscription->plan->getBillableFormattedPrice(),
                                            ]);


                                            $user = User::find(auth()->user()->id);

                                            if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                                $user->sms_unit = $plan->getOption('sms_max');
                                            } else {
                                                if ($plan->getOption('add_previous_balance') == 'yes') {
                                                    $user->sms_unit += $plan->getOption('sms_max');
                                                } else {
                                                    $user->sms_unit = $plan->getOption('sms_max');
                                                }
                                            }

                                            $user->save();

                                            $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                                            //Add default Sender id
                                            $this->planSenderID($plan, $user);

                                            if (Helper::app_config('subscription_notification_email')) {
                                                $admin = User::find(1);
                                                $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                            }

                                            if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                                $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                            }

                                            return redirect()->route('customer.subscriptions.index')->with([
                                                'status'  => 'success',
                                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                                            ]);
                                        }

                                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                            'status'  => 'error',
                                            'message' => __('locale.exceptions.something_went_wrong'),
                                        ]);

                                    }

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                    'status'  => 'info',
                                    'message' => 'Waiting for administrator approval',
                                ]);
                            }

                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        } catch (Exception $e) {
                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => $e->getMessage(),
                            ]);
                        }
                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_ORANGEMONEY:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_ORANGEMONEY)->first();

                    if (isset($request->status)) {
                        if ($request->status == 'SUCCESS') {

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $plan->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $plan->price,
                                'type'           => Invoices::TYPE_SUBSCRIPTION,
                                'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                'transaction_id' => $request->get('txnid'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                if (Auth::user()->customer->activeSubscription()) {
                                    Auth::user()->customer->activeSubscription()->cancelNow();
                                }

                                if (Auth::user()->customer->subscription) {
                                    $subscription          = Auth::user()->customer->subscription;
                                    $get_options           = json_decode($subscription->options, true);
                                    $output                = array_replace($get_options, [
                                        'send_warning' => false,
                                    ]);
                                    $subscription->options = json_encode($output);

                                } else {
                                    $subscription           = new Subscription();
                                    $subscription->user_id  = Auth::user()->id;
                                    $subscription->start_at = Carbon::now();
                                }

                                $subscription->status                 = Subscription::STATUS_ACTIVE;
                                $subscription->plan_id                = $plan->getBillableId();
                                $subscription->end_period_last_days   = '10';
                                $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                $subscription->end_at                 = null;
                                $subscription->end_by                 = null;
                                $subscription->payment_method_id      = $paymentMethod->id;

                                $subscription->save();

                                // add transaction
                                $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                    'end_at'                 => $subscription->end_at,
                                    'current_period_ends_at' => $subscription->current_period_ends_at,
                                    'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                    'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                    'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                ]);

                                // add log
                                $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                    'plan'  => $subscription->plan->getBillableName(),
                                    'price' => $subscription->plan->getBillableFormattedPrice(),
                                ]);

                                $user = User::find(auth()->user()->id);

                                if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                } else {
                                    if ($plan->getOption('add_previous_balance') == 'yes') {
                                        $user->sms_unit += $plan->getOption('sms_max');
                                    } else {
                                        $user->sms_unit = $plan->getOption('sms_max');
                                    }
                                }

                                $user->save();

                                $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                                //Add default Sender id
                                $this->planSenderID($plan, $user);

                                if (Helper::app_config('subscription_notification_email')) {
                                    $admin = User::find(1);
                                    $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                }

                                if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                    $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                }

                                return redirect()->route('customer.subscriptions.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'info',
                            'message' => $request->status,
                        ]);
                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case PaymentMethods::TYPE_CINETPAY:

                    $paymentMethod  = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_CINETPAY)->first();
                    $transaction_id = $request->transaction_id;
                    $credentials    = json_decode($paymentMethod->options);

                    $payment_data = [
                        'apikey'         => $credentials->api_key,
                        'site_id'        => $credentials->site_id,
                        'transaction_id' => $transaction_id,
                    ];


                    try {

                        $curl = curl_init();

                        curl_setopt_array($curl, [
                            CURLOPT_URL            => $credentials->payment_url . '/check',
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
                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => 'Php curl show false value. Please contact with your provider',
                            ]);

                        }

                        if ($err) {
                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => $err,
                            ]);
                        }

                        $result = json_decode($response, true);


                        if (is_array($result) && array_key_exists('code', $result) && array_key_exists('message', $result)) {
                            if ($result['code'] == '00') {

                                $invoice = Invoices::create([
                                    'user_id'        => auth()->user()->id,
                                    'currency_id'    => $plan->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $plan->price,
                                    'type'           => Invoices::TYPE_SUBSCRIPTION,
                                    'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                    'transaction_id' => $transaction_id,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    if (Auth::user()->customer->activeSubscription()) {
                                        Auth::user()->customer->activeSubscription()->cancelNow();
                                    }

                                    if (Auth::user()->customer->subscription) {
                                        $subscription          = Auth::user()->customer->subscription;
                                        $get_options           = json_decode($subscription->options, true);
                                        $output                = array_replace($get_options, [
                                            'send_warning' => false,
                                        ]);
                                        $subscription->options = json_encode($output);

                                    } else {
                                        $subscription           = new Subscription();
                                        $subscription->user_id  = Auth::user()->id;
                                        $subscription->start_at = Carbon::now();
                                    }

                                    $subscription->status                 = Subscription::STATUS_ACTIVE;
                                    $subscription->plan_id                = $plan->getBillableId();
                                    $subscription->end_period_last_days   = '10';
                                    $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                    $subscription->end_at                 = null;
                                    $subscription->end_by                 = null;
                                    $subscription->payment_method_id      = $paymentMethod->id;

                                    $subscription->save();

                                    // add transaction
                                    $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                        'end_at'                 => $subscription->end_at,
                                        'current_period_ends_at' => $subscription->current_period_ends_at,
                                        'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                        'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                        'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                    ]);

                                    // add log
                                    $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                        'plan'  => $subscription->plan->getBillableName(),
                                        'price' => $subscription->plan->getBillableFormattedPrice(),
                                    ]);

                                    $user = User::find(auth()->user()->id);

                                    if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                        $user->sms_unit = $plan->getOption('sms_max');
                                    } else {
                                        if ($plan->getOption('add_previous_balance') == 'yes') {
                                            $user->sms_unit += $plan->getOption('sms_max');
                                        } else {
                                            $user->sms_unit = $plan->getOption('sms_max');
                                        }
                                    }

                                    $user->save();

                                    $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                                    //Add default Sender id
                                    $this->planSenderID($plan, $user);

                                    if (Helper::app_config('subscription_notification_email')) {
                                        $admin = User::find(1);
                                        $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                    }

                                    if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                        $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                    }

                                    return redirect()->route('customer.subscriptions.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => $result['message'],
                            ]);
                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'       => 'error',
                            'redirect_url' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    } catch (Exception $ex) {

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'       => 'error',
                            'redirect_url' => $ex->getMessage(),
                        ]);
                    }

                case PaymentMethods::TYPE_PAYHERELK:

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYHERELK)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        try {

                            if ($credentials->environment == 'sandbox') {
                                $auth_url    = 'https://sandbox.payhere.lk/merchant/v1/oauth/token';
                                $payment_url = 'https://sandbox.payhere.lk/merchant/v1/payment/search';
                            } else {
                                $auth_url    = 'https://payhere.lk/merchant/v1/oauth/token';
                                $payment_url = 'https://payhere.lk/merchant/v1/payment/search';
                            }

                            $headers = [
                                'Content-Type: application/x-www-form-urlencoded',
                                'Authorization: Basic ' . base64_encode("$credentials->app_id:$credentials->app_secret"),
                            ];

                            $ch = curl_init();

                            curl_setopt($ch, CURLOPT_URL, $auth_url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                            $auth_data = curl_exec($ch);

                            if (curl_errno($ch)) {

                                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                    'status'  => 'error',
                                    'message' => curl_error($ch),
                                ]);
                            }

                            curl_close($ch);

                            $result = json_decode($auth_data, true);

                            if (is_array($result)) {
                                if (array_key_exists('error_description', $result)) {
                                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                        'status'  => 'error',
                                        'message' => $result['error_description'],
                                    ]);
                                }


                                $headers = [
                                    'Content-Type: application/json',
                                    'Authorization: Bearer ' . $result['access_token'],
                                ];

                                $curl = curl_init();

                                curl_setopt($curl, CURLOPT_URL, $payment_url);
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($curl, CURLOPT_POST, 1);
                                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['order_id' => $request->get('order_id')]));
                                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

                                $payment_data = curl_exec($curl);

                                if (curl_errno($curl)) {
                                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                        'status'  => 'error',
                                        'message' => curl_error($curl),
                                    ]);
                                }

                                curl_close($curl);

                                $result = json_decode($payment_data, true);

                                if (is_array($result)) {
                                    if (array_key_exists('error_description', $result)) {
                                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                            'status'  => 'error',
                                            'message' => $result['error_description'],
                                        ]);
                                    }

                                    if (array_key_exists('status', $result) && $result['status'] == '-1') {
                                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                            'status'  => 'error',
                                            'message' => $result['msg'],
                                        ]);
                                    }

                                    $invoice = Invoices::create([
                                        'user_id'        => auth()->user()->id,
                                        'currency_id'    => $plan->currency_id,
                                        'payment_method' => $paymentMethod->id,
                                        'amount'         => $plan->price,
                                        'type'           => Invoices::TYPE_SUBSCRIPTION,
                                        'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                        'transaction_id' => $request->get('order_id'),
                                        'status'         => Invoices::STATUS_PAID,
                                    ]);

                                    if ($invoice) {
                                        if (Auth::user()->customer->activeSubscription()) {
                                            Auth::user()->customer->activeSubscription()->cancelNow();
                                        }

                                        if (Auth::user()->customer->subscription) {
                                            $subscription          = Auth::user()->customer->subscription;
                                            $get_options           = json_decode($subscription->options, true);
                                            $output                = array_replace($get_options, [
                                                'send_warning' => false,
                                            ]);
                                            $subscription->options = json_encode($output);

                                        } else {
                                            $subscription           = new Subscription();
                                            $subscription->user_id  = Auth::user()->id;
                                            $subscription->start_at = Carbon::now();
                                        }

                                        $subscription->status                 = Subscription::STATUS_ACTIVE;
                                        $subscription->plan_id                = $plan->getBillableId();
                                        $subscription->end_period_last_days   = '10';
                                        $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                        $subscription->end_at                 = null;
                                        $subscription->end_by                 = null;
                                        $subscription->payment_method_id      = $paymentMethod->id;

                                        $subscription->save();

                                        // add transaction
                                        $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                            'end_at'                 => $subscription->end_at,
                                            'current_period_ends_at' => $subscription->current_period_ends_at,
                                            'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                            'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                            'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                        ]);

                                        // add log
                                        $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                            'plan'  => $subscription->plan->getBillableName(),
                                            'price' => $subscription->plan->getBillableFormattedPrice(),
                                        ]);

                                        $user = User::find(auth()->user()->id);

                                        if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                            $user->sms_unit = $plan->getOption('sms_max');
                                        } else {
                                            if ($plan->getOption('add_previous_balance') == 'yes') {
                                                $user->sms_unit += $plan->getOption('sms_max');
                                            } else {
                                                $user->sms_unit = $plan->getOption('sms_max');
                                            }
                                        }

                                        $user->save();

                                        //Add default Sender id
                                        $this->planSenderID($plan, $user);

                                        $this->createNotification('plan', $plan->name, auth()->user()->displayName());

                                        if (Helper::app_config('subscription_notification_email')) {
                                            $admin = User::find(1);
                                            $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                        }

                                        if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                            $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                        }

                                        return redirect()->route('customer.subscriptions.index')->with([
                                            'status'  => 'success',
                                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                                        ]);
                                    }


                                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                }

                                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        } catch (Exception $exception) {
                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }
                    }
                    break;

                case PaymentMethods::TYPE_MOLLIE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_MOLLIE)->first();

                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        $mollie = new MollieApiClient();
                        $mollie->setApiKey($credentials->api_key);

                        $payment_id = Session::get('payment_id');

                        $payment = $mollie->payments->get($payment_id);

                        if ($payment->isPaid()) {

                            $invoice_exist = Invoices::where('transaction_id', $payment_id)->first();
                            if ( ! $invoice_exist) {

                                $invoice = Invoices::create([
                                    'user_id'        => auth()->user()->id,
                                    'currency_id'    => $plan->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $plan->price,
                                    'type'           => Invoices::TYPE_SUBSCRIPTION,
                                    'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                    'transaction_id' => $payment_id,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    if (Auth::user()->customer->activeSubscription()) {
                                        Auth::user()->customer->activeSubscription()->cancelNow();
                                    }

                                    if (Auth::user()->customer->subscription) {
                                        $subscription = Auth::user()->customer->subscription;

                                        $get_options           = json_decode($subscription->options, true);
                                        $output                = array_replace($get_options, [
                                            'send_warning' => false,
                                        ]);
                                        $subscription->options = json_encode($output);

                                    } else {
                                        $subscription           = new Subscription();
                                        $subscription->user_id  = Auth::user()->id;
                                        $subscription->start_at = Carbon::now();
                                    }

                                    $subscription->status                 = Subscription::STATUS_ACTIVE;
                                    $subscription->plan_id                = $plan->getBillableId();
                                    $subscription->end_period_last_days   = '10';
                                    $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                    $subscription->end_at                 = null;
                                    $subscription->end_by                 = null;
                                    $subscription->payment_method_id      = $paymentMethod->id;
                                    $subscription->save();

                                    // add transaction
                                    $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                        'end_at'                 => $subscription->end_at,
                                        'current_period_ends_at' => $subscription->current_period_ends_at,
                                        'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                        'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                        'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                    ]);

                                    // add log
                                    $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                        'plan'  => $subscription->plan->getBillableName(),
                                        'price' => $subscription->plan->getBillableFormattedPrice(),
                                    ]);


                                    $user = User::find(auth()->user()->id);

                                    if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                        $user->sms_unit = $plan->getOption('sms_max');
                                    } else {
                                        if ($plan->getOption('add_previous_balance') == 'yes') {
                                            $user->sms_unit += $plan->getOption('sms_max');
                                        } else {
                                            $user->sms_unit = $plan->getOption('sms_max');
                                        }
                                    }

                                    $user->save();

                                    $this->createNotification('plan', $plan->name, auth()->user()->displayName());

                                    return redirect()->route('customer.subscriptions.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);

                case PaymentMethods::TYPE_SELCOMMOBILE:
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_SELCOMMOBILE)->first();
                    if ($paymentMethod) {
                        $credentials = json_decode($paymentMethod->options);

                        $orderStatusArray = [
                            "order_id" => $plan->uid,
                        ];

                        $client = new Client($credentials->payment_url, $credentials->api_key, $credentials->api_secret);

                        // path relative to base url
                        $orderStatusPath = "/checkout/order-status";

                        // create order minimal
                        try {
                            $response = $client->getFunc($orderStatusPath, $orderStatusArray);

                            if (isset($response) && is_array($response) && array_key_exists('data', $response) && array_key_exists('result', $response)) {
                                if ($response['result'] == 'SUCCESS' && array_key_exists('0', $response['data']) && $response['data'][0]['payment_status'] == 'COMPLETED') {

                                    $invoice_exist = Invoices::where('transaction_id', $response['data'][0]['transid'])->first();
                                    if ( ! $invoice_exist) {
                                        $invoice = Invoices::create([
                                            'user_id'        => auth()->user()->id,
                                            'currency_id'    => $plan->currency_id,
                                            'payment_method' => $paymentMethod->id,
                                            'amount'         => $plan->price,
                                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                                            'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                            'transaction_id' => $response['data'][0]['transid'],
                                            'status'         => Invoices::STATUS_PAID,
                                        ]);

                                        if ($invoice) {
                                            if (Auth::user()->customer->activeSubscription()) {
                                                Auth::user()->customer->activeSubscription()->cancelNow();
                                            }

                                            if (Auth::user()->customer->subscription) {
                                                $subscription = Auth::user()->customer->subscription;

                                                $get_options           = json_decode($subscription->options, true);
                                                $output                = array_replace($get_options, [
                                                    'send_warning' => false,
                                                ]);
                                                $subscription->options = json_encode($output);

                                            } else {
                                                $subscription           = new Subscription();
                                                $subscription->user_id  = Auth::user()->id;
                                                $subscription->start_at = Carbon::now();
                                            }

                                            $subscription->status                 = Subscription::STATUS_ACTIVE;
                                            $subscription->plan_id                = $plan->getBillableId();
                                            $subscription->end_period_last_days   = '10';
                                            $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                            $subscription->end_at                 = null;
                                            $subscription->end_by                 = null;
                                            $subscription->payment_method_id      = $paymentMethod->id;
                                            $subscription->save();

                                            // add transaction
                                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                                'end_at'                 => $subscription->end_at,
                                                'current_period_ends_at' => $subscription->current_period_ends_at,
                                                'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                                'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                                'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                            ]);

                                            // add log
                                            $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                                'plan'  => $subscription->plan->getBillableName(),
                                                'price' => $subscription->plan->getBillableFormattedPrice(),
                                            ]);


                                            $user = User::find(auth()->user()->id);

                                            if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                                $user->sms_unit = $plan->getOption('sms_max');
                                            } else {
                                                if ($plan->getOption('add_previous_balance') == 'yes') {
                                                    $user->sms_unit += $plan->getOption('sms_max');
                                                } else {
                                                    $user->sms_unit = $plan->getOption('sms_max');
                                                }
                                            }

                                            $user->save();

                                            $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                                            //Add default Sender id
                                            $this->planSenderID($plan, $user);

                                            if (Helper::app_config('subscription_notification_email')) {
                                                $admin = User::find(1);
                                                $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                            }

                                            if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                                $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                            }

                                            return redirect()->route('customer.subscriptions.index')->with([
                                                'status'  => 'success',
                                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                                            ]);
                                        }

                                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                            'status'  => 'error',
                                            'message' => __('locale.exceptions.something_went_wrong'),
                                        ]);

                                    }

                                    return redirect()->route('user.home')->with([
                                        'status'  => 'error',
                                        'message' => __('locale.exceptions.something_went_wrong'),
                                    ]);

                                } else {
                                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                        'status'  => 'error',
                                        'message' => $response['message'],
                                    ]);
                                }
                            }

                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => $response,
                            ]);

                        } catch (Exception $exception) {
                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }

                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.payment_gateways.not_found'),
                    ]);

                case PaymentMethods::TYPE_MPGS:

                    $order_id = $request->input('order_id');

                    if (empty($order_id)) {
                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => 'Payment error: Invalid transaction.',
                        ]);
                    }

                    $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_MPGS)->first();


                    if ( ! $paymentMethod) {
                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.payment_gateways.not_found'),
                        ]);

                    }

                    $credentials = json_decode($paymentMethod->options);

                    $config = [
                        'payment_url'             => $credentials->payment_url,
                        'api_version'             => $credentials->api_version,
                        'merchant_id'             => $credentials->merchant_id,
                        'authentication_password' => $credentials->authentication_password,
                    ];


                    $paymentData = [
                        'order_id' => $order_id,
                    ];

                    $mpgs   = new MPGS($config, $paymentData);
                    $result = $mpgs->process_response();

                    if (isset($result->getData()->status) && isset($result->getData()->message)) {
                        if ($result->getData()->status == 'success') {

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $plan->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $plan->price,
                                'type'           => Invoices::TYPE_SUBSCRIPTION,
                                'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                'transaction_id' => $result->getData()->transaction_id,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                if (Auth::user()->customer->activeSubscription()) {
                                    Auth::user()->customer->activeSubscription()->cancelNow();
                                }

                                if (Auth::user()->customer->subscription) {
                                    $subscription          = Auth::user()->customer->subscription;
                                    $get_options           = json_decode($subscription->options, true);
                                    $output                = array_replace($get_options, [
                                        'send_warning' => false,
                                    ]);
                                    $subscription->options = json_encode($output);

                                } else {
                                    $subscription           = new Subscription();
                                    $subscription->user_id  = Auth::user()->id;
                                    $subscription->start_at = Carbon::now();
                                }

                                $subscription->status                 = Subscription::STATUS_ACTIVE;
                                $subscription->plan_id                = $plan->getBillableId();
                                $subscription->end_period_last_days   = '10';
                                $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                $subscription->end_at                 = null;
                                $subscription->end_by                 = null;
                                $subscription->payment_method_id      = $paymentMethod->id;

                                $subscription->save();

                                // add transaction
                                $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                    'end_at'                 => $subscription->end_at,
                                    'current_period_ends_at' => $subscription->current_period_ends_at,
                                    'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                    'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                    'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                ]);

                                // add log
                                $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                    'plan'  => $subscription->plan->getBillableName(),
                                    'price' => $subscription->plan->getBillableFormattedPrice(),
                                ]);

                                $user = User::find(auth()->user()->id);

                                if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                } else {
                                    if ($plan->getOption('add_previous_balance') == 'yes') {
                                        $user->sms_unit += $plan->getOption('sms_max');
                                    } else {
                                        $user->sms_unit = $plan->getOption('sms_max');
                                    }
                                }

                                $user->save();

                                //Add default Sender id
                                $this->planSenderID($plan, $user);

                                $this->createNotification('plan', $plan->name, auth()->user()->displayName());

                                if (Helper::app_config('subscription_notification_email')) {
                                    $admin = User::find(1);
                                    $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                }

                                if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                    $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                }

                                return redirect()->route('customer.subscriptions.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }


                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => $result->getData()->message,
                        ]);
                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

            }

            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);

        }


        /**
         * cancel payment
         *
         * @param Plan    $plan
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function cancelledSubscriptionPayment(Plan $plan, Request $request): RedirectResponse
        {

            $payment_method = Session::get('payment_method');

            switch ($payment_method) {
                case PaymentMethods::TYPE_PAYPAL:

                    $token = Session::get('paypal_payment_id');
                    if ($request->get('token') == $token) {
                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'info',
                            'message' => __('locale.sender_id.payment_cancelled'),
                        ]);
                    }
                    break;

                case PaymentMethods::TYPE_STRIPE:
                case PaymentMethods::TYPE_PAYU:
                case PaymentMethods::TYPE_COINPAYMENTS:
                case PaymentMethods::TYPE_PAYUMONEY:
                case PaymentMethods::TYPE_PAYHERELK:
                case PaymentMethods::TYPE_MPGS:
                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'info',
                        'message' => __('locale.sender_id.payment_cancelled'),
                    ]);
            }


            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                'status'  => 'info',
                'message' => __('locale.sender_id.payment_cancelled'),
            ]);

        }

        /**
         * purchase sender id by braintree
         *
         * @param Plan    $plan
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function braintreeSubscription(Plan $plan, Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'braintree')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {
                    $gateway = new Gateway([
                        'environment' => $credentials->environment,
                        'merchantId'  => $credentials->merchant_id,
                        'publicKey'   => $credentials->public_key,
                        'privateKey'  => $credentials->private_key,
                    ]);

                    $result = $gateway->transaction()->sale([
                        'amount'             => $plan->price,
                        'paymentMethodNonce' => $request->get('payment_method_nonce'),
                        'deviceData'         => $request->get('device_data'),
                        'options'            => [
                            'submitForSettlement' => true,
                        ],
                    ]);

                    if ($result->success && isset($result->transaction->id)) {
                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $plan->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $plan->price,
                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                            'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                            'transaction_id' => $result->transaction->id,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            if (Auth::user()->customer->activeSubscription()) {
                                Auth::user()->customer->activeSubscription()->cancelNow();
                            }

                            if (Auth::user()->customer->subscription) {
                                $subscription = Auth::user()->customer->subscription;

                                $get_options           = json_decode($subscription->options, true);
                                $output                = array_replace($get_options, [
                                    'send_warning' => false,
                                ]);
                                $subscription->options = json_encode($output);

                            } else {
                                $subscription           = new Subscription();
                                $subscription->user_id  = Auth::user()->id;
                                $subscription->start_at = Carbon::now();
                            }

                            $subscription->status                 = Subscription::STATUS_ACTIVE;
                            $subscription->plan_id                = $plan->getBillableId();
                            $subscription->end_period_last_days   = '10';
                            $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                            $subscription->end_at                 = null;
                            $subscription->end_by                 = null;
                            $subscription->payment_method_id      = $paymentMethod->id;
                            $subscription->save();

                            // add transaction
                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                'end_at'                 => $subscription->end_at,
                                'current_period_ends_at' => $subscription->current_period_ends_at,
                                'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                            ]);

                            // add log
                            $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                'plan'  => $subscription->plan->getBillableName(),
                                'price' => $subscription->plan->getBillableFormattedPrice(),
                            ]);


                            $user = User::find(auth()->user()->id);

                            if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                $user->sms_unit = $plan->getOption('sms_max');
                            } else {
                                if ($plan->getOption('add_previous_balance') == 'yes') {
                                    $user->sms_unit += $plan->getOption('sms_max');
                                } else {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                }
                            }

                            $user->save();

                            $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                            //Add default Sender id
                            $this->planSenderID($plan, $user);

                            if (Helper::app_config('subscription_notification_email')) {
                                $admin = User::find(1);
                                $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                            }

                            if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                            }

                            return redirect()->route('customer.subscriptions.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => $result->message,
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * purchase sender id by authorize net
         *
         * @param Plan    $plan
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function authorizeNetSubscriptions(Plan $plan, Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'authorize_net')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {

                    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
                    $merchantAuthentication->setName($credentials->login_id);
                    $merchantAuthentication->setTransactionKey($credentials->transaction_key);

                    // Set the transaction's refId
                    $refId      = 'ref' . time();
                    $cardNumber = preg_replace('/\s+/', '', $request->cardNumber);

                    // Create the payment data for a credit card
                    $creditCard = new AnetAPI\CreditCardType();
                    $creditCard->setCardNumber($cardNumber);
                    $creditCard->setExpirationDate($request->expiration_year . "-" . $request->expiration_month);
                    $creditCard->setCardCode($request->cvv);


                    // Add the payment data to a paymentType object
                    $paymentOne = new AnetAPI\PaymentType();
                    $paymentOne->setCreditCard($creditCard);

                    // Create order information
                    $order = new AnetAPI\OrderType();
                    $order->setInvoiceNumber($plan->uid);
                    $order->setDescription(__('locale.subscription.payment_for_plan') . ' ' . $plan->name);


                    // Set the customer's Bill To address
                    $customerAddress = new AnetAPI\CustomerAddressType();
                    $customerAddress->setFirstName(auth()->user()->first_name);
                    $customerAddress->setLastName(auth()->user()->last_name);

                    // Set the customer's identifying information
                    $customerData = new AnetAPI\CustomerDataType();
                    $customerData->setType("individual");
                    $customerData->setId(auth()->user()->id);
                    $customerData->setEmail(auth()->user()->email);


                    // Create a TransactionRequestType object and add the previous objects to it
                    $transactionRequestType = new AnetAPI\TransactionRequestType();
                    $transactionRequestType->setTransactionType("authCaptureTransaction");
                    $transactionRequestType->setAmount($plan->price);
                    $transactionRequestType->setOrder($order);
                    $transactionRequestType->setPayment($paymentOne);
                    $transactionRequestType->setBillTo($customerAddress);
                    $transactionRequestType->setCustomer($customerData);


                    // Assemble the complete transaction request
                    $requests = new AnetAPI\CreateTransactionRequest();
                    $requests->setMerchantAuthentication($merchantAuthentication);
                    $requests->setRefId($refId);
                    $requests->setTransactionRequest($transactionRequestType);

                    // Create the controller and get the response
                    $controller = new AnetController\CreateTransactionController($requests);
                    if ($credentials->environment == 'sandbox') {
                        $result = $controller->executeWithApiResponse(ANetEnvironment::SANDBOX);
                    } else {
                        $result = $controller->executeWithApiResponse(ANetEnvironment::PRODUCTION);
                    }

                    if (isset($result) && $result->getMessages()->getResultCode() == 'Ok' && $result->getTransactionResponse()) {
                        $invoice = Invoices::create([
                            'user_id'        => auth()->user()->id,
                            'currency_id'    => $plan->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $plan->price,
                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                            'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                            'transaction_id' => $result->getRefId(),
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            if (Auth::user()->customer->activeSubscription()) {
                                Auth::user()->customer->activeSubscription()->cancelNow();
                            }

                            if (Auth::user()->customer->subscription) {
                                $subscription = Auth::user()->customer->subscription;

                                $get_options           = json_decode($subscription->options, true);
                                $output                = array_replace($get_options, [
                                    'send_warning' => false,
                                ]);
                                $subscription->options = json_encode($output);

                            } else {
                                $subscription           = new Subscription();
                                $subscription->user_id  = Auth::user()->id;
                                $subscription->start_at = Carbon::now();
                            }

                            $subscription->status                 = Subscription::STATUS_ACTIVE;
                            $subscription->plan_id                = $plan->getBillableId();
                            $subscription->end_period_last_days   = '10';
                            $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                            $subscription->end_at                 = null;
                            $subscription->end_by                 = null;
                            $subscription->payment_method_id      = $paymentMethod->id;
                            $subscription->save();

                            // add transaction
                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                'end_at'                 => $subscription->end_at,
                                'current_period_ends_at' => $subscription->current_period_ends_at,
                                'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                            ]);

                            // add log
                            $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                'plan'  => $subscription->plan->getBillableName(),
                                'price' => $subscription->plan->getBillableFormattedPrice(),
                            ]);


                            $user = User::find(auth()->user()->id);

                            if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                $user->sms_unit = $plan->getOption('sms_max');
                            } else {
                                if ($plan->getOption('add_previous_balance') == 'yes') {
                                    $user->sms_unit += $plan->getOption('sms_max');
                                } else {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                }
                            }

                            $user->save();

                            $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                            //Add default Sender id
                            $this->planSenderID($plan, $user);

                            if (Helper::app_config('subscription_notification_email')) {
                                $admin = User::find(1);
                                $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                            }

                            if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                            }

                            return redirect()->route('customer.subscriptions.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * razorpay subscription payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function razorpaySubscriptions(Request $request): RedirectResponse
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'razorpay')->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);
                $order_id    = Session::get('razorpay_order_id');

                if (isset($order_id) && empty($request->razorpay_payment_id) === false) {

                    $plan = Plan::where('transaction_id', $order_id)->first();
                    if ($plan) {

                        $api        = new Api($credentials->key_id, $credentials->key_secret);
                        $attributes = [
                            'razorpay_order_id'   => $order_id,
                            'razorpay_payment_id' => $request->razorpay_payment_id,
                            'razorpay_signature'  => $request->razorpay_signature,
                        ];

                        try {

                            $response = $api->utility->verifyPaymentSignature($attributes);

                            if ($response) {
                                $invoice = Invoices::create([
                                    'user_id'        => auth()->user()->id,
                                    'currency_id'    => $plan->currency_id,
                                    'payment_method' => $paymentMethod->id,
                                    'amount'         => $plan->price,
                                    'type'           => Invoices::TYPE_SUBSCRIPTION,
                                    'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                    'transaction_id' => $order_id,
                                    'status'         => Invoices::STATUS_PAID,
                                ]);

                                if ($invoice) {
                                    if (Auth::user()->customer->activeSubscription()) {
                                        Auth::user()->customer->activeSubscription()->cancelNow();
                                    }

                                    if (Auth::user()->customer->subscription) {
                                        $subscription = Auth::user()->customer->subscription;

                                        $get_options           = json_decode($subscription->options, true);
                                        $output                = array_replace($get_options, [
                                            'send_warning' => false,
                                        ]);
                                        $subscription->options = json_encode($output);

                                    } else {
                                        $subscription           = new Subscription();
                                        $subscription->user_id  = Auth::user()->id;
                                        $subscription->start_at = Carbon::now();
                                    }

                                    $subscription->status                 = Subscription::STATUS_ACTIVE;
                                    $subscription->plan_id                = $plan->getBillableId();
                                    $subscription->end_period_last_days   = '10';
                                    $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                    $subscription->end_at                 = null;
                                    $subscription->end_by                 = null;
                                    $subscription->payment_method_id      = $paymentMethod->id;
                                    $subscription->save();

                                    // add transaction
                                    $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                        'end_at'                 => $subscription->end_at,
                                        'current_period_ends_at' => $subscription->current_period_ends_at,
                                        'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                        'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                        'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                    ]);

                                    // add log
                                    $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                        'plan'  => $subscription->plan->getBillableName(),
                                        'price' => $subscription->plan->getBillableFormattedPrice(),
                                    ]);


                                    $user = User::find(auth()->user()->id);

                                    if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                        $user->sms_unit = $plan->getOption('sms_max');
                                    } else {
                                        if ($plan->getOption('add_previous_balance') == 'yes') {
                                            $user->sms_unit += $plan->getOption('sms_max');
                                        } else {
                                            $user->sms_unit = $plan->getOption('sms_max');
                                        }
                                    }

                                    $user->save();

                                    $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                                    //Add default Sender id
                                    $this->planSenderID($plan, $user);

                                    if (Helper::app_config('subscription_notification_email')) {
                                        $admin = User::find(1);
                                        $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                    }

                                    if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                        $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                    }

                                    return redirect()->route('customer.subscriptions.index')->with([
                                        'status'  => 'success',
                                        'message' => __('locale.payment_gateways.payment_successfully_made'),
                                    ]);
                                }

                                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                    'status'  => 'error',
                                    'message' => __('locale.exceptions.something_went_wrong'),
                                ]);
                            }

                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        } catch (SignatureVerificationError $exception) {

                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => $exception->getMessage(),
                            ]);
                        }
                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
                ]);

            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }

        /**
         * sslcommerz subscription payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function sslcommerzSubscriptions(Request $request): RedirectResponse
        {

            if (isset($request->status)) {
                if ($request->status == 'VALID') {
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'sslcommerz')->first();
                    if ($paymentMethod) {

                        $plan = Plan::where('uid', $request->get('tran_id'))->first();
                        if ($plan) {
                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $plan->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $plan->price,
                                'type'           => Invoices::TYPE_SUBSCRIPTION,
                                'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                'transaction_id' => $request->get('bank_tran_id'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                if (Auth::user()->customer->activeSubscription()) {
                                    Auth::user()->customer->activeSubscription()->cancelNow();
                                }

                                if (Auth::user()->customer->subscription) {
                                    $subscription = Auth::user()->customer->subscription;

                                    $get_options           = json_decode($subscription->options, true);
                                    $output                = array_replace($get_options, [
                                        'send_warning' => false,
                                    ]);
                                    $subscription->options = json_encode($output);

                                } else {
                                    $subscription           = new Subscription();
                                    $subscription->user_id  = Auth::user()->id;
                                    $subscription->start_at = Carbon::now();
                                }

                                $subscription->status                 = Subscription::STATUS_ACTIVE;
                                $subscription->plan_id                = $plan->getBillableId();
                                $subscription->end_period_last_days   = '10';
                                $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                $subscription->end_at                 = null;
                                $subscription->end_by                 = null;
                                $subscription->payment_method_id      = $paymentMethod->id;
                                $subscription->save();

                                // add transaction
                                $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                    'end_at'                 => $subscription->end_at,
                                    'current_period_ends_at' => $subscription->current_period_ends_at,
                                    'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                    'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                    'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                ]);

                                // add log
                                $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                    'plan'  => $subscription->plan->getBillableName(),
                                    'price' => $subscription->plan->getBillableFormattedPrice(),
                                ]);


                                $user = User::find(auth()->user()->id);

                                if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                } else {
                                    if ($plan->getOption('add_previous_balance') == 'yes') {
                                        $user->sms_unit += $plan->getOption('sms_max');
                                    } else {
                                        $user->sms_unit = $plan->getOption('sms_max');
                                    }
                                }

                                $user->save();

                                $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                                //Add default Sender id
                                $this->planSenderID($plan, $user);

                                if (Helper::app_config('subscription_notification_email')) {
                                    $admin = User::find(1);
                                    $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                }

                                if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                    $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                }

                                return redirect()->route('customer.subscriptions.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                }

                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => $request->status,
                ]);

            }


            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * aamarpay subscription payment
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function aamarpaySubscriptions(Request $request): RedirectResponse
        {

            if (isset($request->pay_status) && isset($request->mer_txnid)) {

                $plan = Plan::where('uid', $request->mer_txnid)->first();

                if ($request->pay_status == 'Successful') {
                    $paymentMethod = PaymentMethods::where('status', true)->where('type', 'aamarpay')->first();
                    if ($paymentMethod) {

                        if ($plan) {
                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $plan->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $plan->price,
                                'type'           => Invoices::TYPE_SUBSCRIPTION,
                                'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                'transaction_id' => $request->pg_txnid,
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                if (Auth::user()->customer->activeSubscription()) {
                                    Auth::user()->customer->activeSubscription()->cancelNow();
                                }

                                if (Auth::user()->customer->subscription) {
                                    $subscription = Auth::user()->customer->subscription;

                                    $get_options           = json_decode($subscription->options, true);
                                    $output                = array_replace($get_options, [
                                        'send_warning' => false,
                                    ]);
                                    $subscription->options = json_encode($output);

                                } else {
                                    $subscription           = new Subscription();
                                    $subscription->user_id  = Auth::user()->id;
                                    $subscription->start_at = Carbon::now();
                                }

                                $subscription->status                 = Subscription::STATUS_ACTIVE;
                                $subscription->plan_id                = $plan->getBillableId();
                                $subscription->end_period_last_days   = '10';
                                $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                $subscription->end_at                 = null;
                                $subscription->end_by                 = null;
                                $subscription->payment_method_id      = $paymentMethod->id;
                                $subscription->save();

                                // add transaction
                                $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                    'end_at'                 => $subscription->end_at,
                                    'current_period_ends_at' => $subscription->current_period_ends_at,
                                    'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                    'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                    'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                ]);

                                // add log
                                $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                    'plan'  => $subscription->plan->getBillableName(),
                                    'price' => $subscription->plan->getBillableFormattedPrice(),
                                ]);


                                $user = User::find(auth()->user()->id);

                                if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                } else {
                                    if ($plan->getOption('add_previous_balance') == 'yes') {
                                        $user->sms_unit += $plan->getOption('sms_max');
                                    } else {
                                        $user->sms_unit = $plan->getOption('sms_max');
                                    }
                                }

                                $user->save();

                                $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                                //Add default Sender id
                                $this->planSenderID($plan, $user);

                                if (Helper::app_config('subscription_notification_email')) {
                                    $admin = User::find(1);
                                    $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                }

                                if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                    $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                }

                                return redirect()->route('customer.subscriptions.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }
                }

                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                    'status'  => 'error',
                    'message' => $request->pay_status,
                ]);

            }


            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /*Version 3.4*/

        /**
         * @param         $type
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function offlinePayment($type, Request $request)
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'offline_payment')->first();

            if ( ! $paymentMethod) {
                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => __('locale.payment_gateways.not_found'),
                ]);
            }

            switch ($type) {
                case 'top_up':
                    $post_data = json_decode($request->get('post_data'));

                    $sms_unit = $post_data->sms_unit;
                    $user     = Auth::user();
                    $price    = $sms_unit;

                    $invoice = Invoices::create([
                        'user_id'        => $user->id,
                        'currency_id'    => $user->customer->subscription->plan->currency->id,
                        'payment_method' => $paymentMethod->id,
                        'amount'         => $price,
                        'type'           => Invoices::TYPE_SUBSCRIPTION,
                        'description'    => 'Payment for sms top up',
                        'transaction_id' => 'top_up|' . $sms_unit,
                        'status'         => Invoices::STATUS_PENDING,
                    ]);

                    if ($invoice) {
                        $this->createNotification('topup', 'SMS Unit ' . $sms_unit, $user->displayName());

                        return redirect()->route('user.home')->with([
                            'status'  => 'success',
                            'message' => __('locale.subscription.payment_is_being_verified'),
                        ]);
                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case 'sender_id':
                    $uid       = $request->get('post_data');
                    $sender_id = Senderid::findByUid($uid);
                    $user      = User::find($sender_id->user_id);

                    $invoice = Invoices::create([
                        'user_id'        => $user->id,
                        'currency_id'    => $user->customer->subscription->plan->currency->id,
                        'payment_method' => $paymentMethod->id,
                        'amount'         => $sender_id->price,
                        'type'           => Invoices::TYPE_SENDERID,
                        'description'    => 'Payment for Sender ID ' . $sender_id->sender_id,
                        'transaction_id' => 'sender_id|' . $uid,
                        'status'         => Invoices::STATUS_PENDING,
                    ]);

                    if ($invoice) {
                        $this->createNotification('senderid', 'Sender ID: ' . $sender_id->sender_id . ' Offline ', $user->displayName());
                        $sender_id->update([
                            'payment_claimed' => true,
                        ]);

                        return redirect()->route('customer.senderid.index', $sender_id->uid)->with([
                            'status'  => 'success',
                            'message' => __('locale.subscription.payment_is_being_verified'),
                        ]);
                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case 'number':
                    $uid    = $request->get('post_data');
                    $number = PhoneNumbers::findByUid($uid);

                    $invoice = Invoices::create([
                        'user_id'        => Auth::user()->id,
                        'currency_id'    => $number->currency_id,
                        'payment_method' => $paymentMethod->id,
                        'amount'         => $number->price,
                        'type'           => Invoices::TYPE_NUMBERS,
                        'description'    => 'Payment for Number ' . $number->number,
                        'transaction_id' => 'number|' . $uid,
                        'status'         => Invoices::STATUS_PENDING,
                    ]);

                    if ($invoice) {
                        $this->createNotification('number', 'Number: ' . $number->number . ' Offline ', Auth::user()->displayName());
                        $number->update([
                            'payment_claimed' => true,
                            'user_id'         => Auth::user()->id,
                        ]);

                        return redirect()->route('customer.numbers.index', $number->uid)->with([
                            'status'  => 'success',
                            'message' => __('locale.subscription.payment_is_being_verified'),
                        ]);
                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case 'keyword':
                    $uid     = $request->get('post_data');
                    $keyword = Keywords::findByUid($uid);

                    $invoice = Invoices::create([
                        'user_id'        => Auth::user()->id,
                        'currency_id'    => $keyword->currency_id,
                        'payment_method' => $paymentMethod->id,
                        'amount'         => $keyword->price,
                        'type'           => Invoices::TYPE_KEYWORD,
                        'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                        'transaction_id' => 'keyword|' . $uid,
                        'status'         => Invoices::STATUS_PENDING,
                    ]);

                    if ($invoice) {
                        $this->createNotification('keyword', 'Keyword: ' . $keyword->keyword_name . ' Offline ', Auth::user()->displayName());
                        $keyword->update([
                            'payment_claimed' => true,
                            'user_id'         => Auth::user()->id,
                        ]);

                        return redirect()->route('customer.keywords.index', $keyword->uid)->with([
                            'status'  => 'success',
                            'message' => __('locale.subscription.payment_is_being_verified'),
                        ]);
                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);

                case 'subscription':
                    $uid  = $request->get('post_data');
                    $plan = Plan::findByUid($uid);


                    if (Auth::user()->customer->activeSubscription()) {
                        Auth::user()->customer->activeSubscription()->cancelNow();
                    }

                    if (Auth::user()->customer->subscription) {
                        $subscription          = Auth::user()->customer->subscription;
                        $get_options           = json_decode($subscription->options, true);
                        $output                = array_replace($get_options, [
                            'send_warning' => false,
                        ]);
                        $subscription->options = json_encode($output);

                    } else {
                        $subscription           = new Subscription();
                        $subscription->user_id  = Auth::user()->id;
                        $subscription->start_at = Carbon::now();
                    }

                    $subscription->status                 = Subscription::STATUS_NEW;
                    $subscription->plan_id                = $plan->getBillableId();
                    $subscription->end_period_last_days   = '10';
                    $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                    $subscription->end_at                 = null;
                    $subscription->end_by                 = null;
                    $subscription->payment_method_id      = $paymentMethod->id;

                    $subscription->save();
                    // add transaction
                    $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                        'end_at'                 => $subscription->end_at,
                        'current_period_ends_at' => $subscription->current_period_ends_at,
                        'status'                 => SubscriptionTransaction::STATUS_PENDING,
                        'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                        'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                    ]);

                    // add log
                    $subscription->addLog(SubscriptionLog::TYPE_CLAIMED, [
                        'plan'  => $subscription->plan->getBillableName(),
                        'price' => $subscription->plan->getBillableFormattedPrice(),
                    ]);

                    $this->createNotification('plan', $plan->name, auth()->user()->displayName());

                    Invoices::create([
                        'user_id'        => Auth::user()->id,
                        'currency_id'    => $plan->currency_id,
                        'payment_method' => $paymentMethod->id,
                        'amount'         => $plan->price,
                        'type'           => Invoices::TYPE_SUBSCRIPTION,
                        'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                        'transaction_id' => 'subscription|' . $subscription->uid,
                        'status'         => Invoices::STATUS_PENDING,
                    ]);

                    return redirect()->route('customer.subscriptions.index')->with([
                        'status'  => 'success',
                        'message' => __('locale.subscription.payment_is_being_verified'),
                    ]);

                default:

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);
            }

        }

        /*Version 3.5*/

        /**
         * Plan sender id
         *
         * @param $plan
         * @param $user
         *
         * @return void
         */
        public function planSenderID($plan, $user)
        {
            if (isset($plan->getOptions()['sender_id']) && $plan->getOption('sender_id') !== null) {
                $sender_id = Senderid::where('sender_id', $plan->getOption('sender_id'))->where('user_id', $user->id)->first();
                if ( ! $sender_id) {
                    $current = Carbon::now();
                    Senderid::create([
                        'sender_id'        => $plan->getOption('sender_id'),
                        'status'           => 'active',
                        'price'            => $plan->getOption('sender_id_price'),
                        'billing_cycle'    => $plan->getOption('sender_id_billing_cycle'),
                        'frequency_amount' => $plan->getOption('sender_id_frequency_amount'),
                        'frequency_unit'   => $plan->getOption('sender_id_frequency_unit'),
                        'currency_id'      => $plan->currency->id,
                        'validity_date'    => $current->add($plan->getOption('sender_id_frequency_unit'), $plan->getOption('sender_id_frequency_amount')),
                        'payment_claimed'  => true,
                        'user_id'          => $user->id,
                    ]);
                }
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Version 3.5
    |--------------------------------------------------------------------------
    |
    | vodacommpesa payment integration
    |
    */


        /**
         * top up by vodacommpesa
         *
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function vodacommpesaTopUp(Request $request): RedirectResponse
        {

            $validator = Validator::make($request->all(), [
                'phone' => 'required|min:9|starts_with:85,84',
            ]);

            if ($validator->fails()) {
                return redirect()->route('user.home')->withErrors($validator->errors());
            }

            $paymentMethod = PaymentMethods::where('status', true)->where('type', 'vodacommpesa')->first();
            $user          = User::find($request->user_id);

            if ($paymentMethod && $user) {
                $credentials = json_decode($paymentMethod->options);

                try {

                    $credentialsParams = [
                        'url'                 => $credentials->payment_url,             // Payment URL
                        'apiKey'              => $credentials->apiKey,             // API Key
                        'publicKey'           => $credentials->publicKey,          // Public Key
                        'serviceProviderCode' => $credentials->serviceProviderCode, // Service Provider Code
                    ];

                    $transaction_id = str_random(10);

                    $paymentData = [
                        'from'        => str_replace(' ', '', $request->get('phone')),  // Customer MSISDN
                        'reference'   => $transaction_id,  // Third Party Reference
                        'transaction' => $transaction_id,  // Transaction Reference
                        'amount'      => $request->get('sms_unit'),
                    ];

                    $MPesa = new MPesa($credentialsParams, $paymentData);

                    $response = $MPesa->submit();
                    $result   = json_decode($response, true);

                    if (is_array($result) && array_key_exists('output_ResponseCode', $result) && array_key_exists('output_ResponseDesc', $result)) {
                        if ($result['output_ResponseCode'] == 'INS-0') {
                            $invoice = Invoices::create([
                                'user_id'        => $user->id,
                                'currency_id'    => $user->customer->subscription->plan->currency->id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $request->get('sms_unit'),
                                'type'           => Invoices::TYPE_SUBSCRIPTION,
                                'description'    => 'Payment for sms top up',
                                'transaction_id' => $result['output_TransactionID'],
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {

                                if ($user->sms_unit != '-1') {
                                    $user->sms_unit += $request->get('sms_unit');
                                    $user->save();
                                }
                                $subscription = $user->customer->activeSubscription();

                                $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                    'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                    'title'  => 'Add ' . $request->get('sms_unit') . ' sms units',
                                    'amount' => $request->get('sms_unit') . ' sms units',
                                ]);

                                return redirect()->route('user.home')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('user.home')->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => $result['output_ResponseDesc'],
                        ]);
                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * purchase sender id by vodacommpesa
         *
         * @param Senderid $senderid
         * @param Request  $request
         *
         * @return RedirectResponse
         */
        public function vodacommpesaSenderID(Senderid $senderid, Request $request): RedirectResponse
        {
            $validator = Validator::make($request->all(), [
                'phone' => 'required|min:9|starts_with:85,84',
            ]);

            if ($validator->fails()) {
                return redirect()->route('customer.senderid.pay', $senderid->uid)->withErrors($validator->errors());
            }

            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_VODACOMMPESA)->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {
                    $credentialsParams = [
                        'url'                 => $credentials->payment_url,        // Payment URL
                        'apiKey'              => $credentials->apiKey,             // API Key
                        'publicKey'           => $credentials->publicKey,          // Public Key
                        'serviceProviderCode' => $credentials->serviceProviderCode, // Service Provider Code
                    ];

                    $transaction_id = str_random(10);

                    $paymentData = [
                        'from'        => str_replace(' ', '', $request->get('phone')),  // Customer MSISDN
                        'reference'   => $senderid->uid,  // Third Party Reference
                        'transaction' => $transaction_id,  // Transaction Reference
                        'amount'      => $senderid->price,
                    ];


                    $MPesa = new MPesa($credentialsParams, $paymentData);

                    $response = $MPesa->submit();
                    $result   = json_decode($response, true);

                    if (is_array($result) && array_key_exists('output_ResponseCode', $result) && array_key_exists('output_ResponseDesc', $result)) {
                        if ($result['output_ResponseCode'] == 'INS-0') {

                            $invoice = Invoices::create([
                                'user_id'        => $senderid->user_id,
                                'currency_id'    => $senderid->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $senderid->price,
                                'type'           => Invoices::TYPE_SENDERID,
                                'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                'transaction_id' => $result['output_TransactionID'],
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                   = Carbon::now();
                                $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                $senderid->status          = 'active';
                                $senderid->payment_claimed = true;
                                $senderid->save();

                                $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                return redirect()->route('customer.senderid.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }
                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => $result['output_ResponseDesc'],
                        ]);

                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * purchase number by vodacommpesa
         *
         * @param PhoneNumbers $number
         * @param Request      $request
         *
         * @return RedirectResponse
         */
        public function vodacommpesaNumber(PhoneNumbers $number, Request $request): RedirectResponse
        {
            $validator = Validator::make($request->all(), [
                'phone' => 'required|min:9|starts_with:85,84',
            ]);

            if ($validator->fails()) {
                return redirect()->route('customer.numbers.pay', $number->uid)->withErrors($validator->errors());
            }

            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_VODACOMMPESA)->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {
                    $credentialsParams = [
                        'url'                 => $credentials->payment_url,        // Payment URL
                        'apiKey'              => $credentials->apiKey,             // API Key
                        'publicKey'           => $credentials->publicKey,          // Public Key
                        'serviceProviderCode' => $credentials->serviceProviderCode, // Service Provider Code
                    ];

                    $transaction_id = str_random(10);

                    $paymentData = [
                        'from'        => str_replace(' ', '', $request->get('phone')),  // Customer MSISDN
                        'reference'   => $number->uid,  // Third Party Reference
                        'transaction' => $transaction_id,  // Transaction Reference
                        'amount'      => $number->price,
                    ];

                    $MPesa    = new MPesa($credentialsParams, $paymentData);
                    $response = $MPesa->submit();
                    $result   = json_decode($response, true);

                    if (is_array($result) && array_key_exists('output_ResponseCode', $result) && array_key_exists('output_ResponseDesc', $result)) {
                        if ($result['output_ResponseCode'] == 'INS-0') {

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $number->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $number->price,
                                'type'           => Invoices::TYPE_NUMBERS,
                                'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                                'transaction_id' => $result['output_TransactionID'],
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current               = Carbon::now();
                                $number->user_id       = auth()->user()->id;
                                $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                                $number->status        = 'assigned';
                                $number->save();

                                $this->createNotification('number', $number->number, User::find($number->user_id)->displayName());

                                return redirect()->route('customer.numbers.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }
                        }

                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => $result['output_ResponseDesc'],
                        ]);

                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.numbers.pay', $number->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * purchase Keyword by vodacommpesa
         *
         * @param Keywords $keyword
         * @param Request  $request
         *
         * @return RedirectResponse
         */
        public function vodacommpesaKeyword(Keywords $keyword, Request $request): RedirectResponse
        {
            $validator = Validator::make($request->all(), [
                'phone' => 'required|min:9|starts_with:85,84',
            ]);

            if ($validator->fails()) {
                return redirect()->route('customer.keywords.pay', $keyword->uid)->withErrors($validator->errors());
            }

            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_VODACOMMPESA)->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {

                    $credentialsParams = [
                        'url'                 => $credentials->payment_url,
                        'apiKey'              => $credentials->apiKey,             // API Key
                        'publicKey'           => $credentials->publicKey,          // Public Key
                        'serviceProviderCode' => $credentials->serviceProviderCode, // Service Provider Code
                    ];

                    $transaction_id = str_random(10);

                    $paymentData = [
                        'from'        => str_replace(' ', '', $request->get('phone')),  // Customer MSISDN
                        'reference'   => $keyword->uid,  // Third Party Reference
                        'transaction' => $transaction_id,  // Transaction Reference
                        'amount'      => $keyword->price,
                    ];

                    $MPesa    = new MPesa($credentialsParams, $paymentData);
                    $response = $MPesa->submit();
                    $result   = json_decode($response, true);

                    if (is_array($result) && array_key_exists('output_ResponseCode', $result) && array_key_exists('output_ResponseDesc', $result)) {
                        if ($result['output_ResponseCode'] == 'INS-0') {

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $keyword->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $keyword->price,
                                'type'           => Invoices::TYPE_KEYWORD,
                                'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                                'transaction_id' => $result['output_TransactionID'],
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                = Carbon::now();
                                $keyword->user_id       = auth()->user()->id;
                                $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                                $keyword->status        = 'assigned';
                                $keyword->save();

                                $this->createNotification('keyword', $keyword->keyword_name, User::find($keyword->user_id)->displayName());

                                if (Helper::app_config('keyword_notification_email')) {
                                    $admin = User::find(1);
                                    $admin->notify(new KeywordPurchase(route('admin.keywords.show', $keyword->uid)));
                                }

                                $user = auth()->user();

                                if ($user->customer->getNotifications()['keyword'] == 'yes') {
                                    $user->notify(new KeywordPurchase(route('customer.keywords.show', $keyword->uid)));
                                }

                                return redirect()->route('customer.keywords.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }
                        }

                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => $result['output_ResponseDesc'],
                        ]);

                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * purchase sender id by vodacommpesa
         *
         * @param Plan    $plan
         * @param Request $request
         *
         * @return RedirectResponse
         */
        public function vodacommpesaSubscriptions(Plan $plan, Request $request): RedirectResponse
        {
            $validator = Validator::make($request->all(), [
                'phone' => 'required|min:9|starts_with:85,84',
            ]);

            if ($validator->fails()) {
                return redirect()->route('customer.subscriptions.purchase', $plan->uid)->withErrors($validator->errors());
            }

            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_VODACOMMPESA)->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {

                    $credentialsParams = [
                        'url'                 => $credentials->payment_url,
                        'apiKey'              => $credentials->apiKey,             // API Key
                        'publicKey'           => $credentials->publicKey,          // Public Key
                        'serviceProviderCode' => $credentials->serviceProviderCode, // Service Provider Code
                    ];

                    $transaction_id = str_random(10);

                    $paymentData = [
                        'from'        => str_replace(' ', '', $request->get('phone')),  // Customer MSISDN
                        'reference'   => $plan->uid,  // Third Party Reference
                        'transaction' => $transaction_id,  // Transaction Reference
                        'amount'      => $plan->price,
                    ];

                    $MPesa    = new MPesa($credentialsParams, $paymentData);
                    $response = $MPesa->submit();
                    $result   = json_decode($response, true);

                    if (is_array($result) && array_key_exists('output_ResponseCode', $result) && array_key_exists('output_ResponseDesc', $result)) {
                        if ($result['output_ResponseCode'] == 'INS-0') {

                            $invoice = Invoices::create([
                                'user_id'        => auth()->user()->id,
                                'currency_id'    => $plan->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $plan->price,
                                'type'           => Invoices::TYPE_SUBSCRIPTION,
                                'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                                'transaction_id' => $result['output_ResponseID'],
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                if (Auth::user()->customer->activeSubscription()) {
                                    Auth::user()->customer->activeSubscription()->cancelNow();
                                }

                                if (Auth::user()->customer->subscription) {
                                    $subscription = Auth::user()->customer->subscription;

                                    $get_options           = json_decode($subscription->options, true);
                                    $output                = array_replace($get_options, [
                                        'send_warning' => false,
                                    ]);
                                    $subscription->options = json_encode($output);

                                } else {
                                    $subscription           = new Subscription();
                                    $subscription->user_id  = Auth::user()->id;
                                    $subscription->start_at = Carbon::now();
                                }

                                $subscription->status                 = Subscription::STATUS_ACTIVE;
                                $subscription->plan_id                = $plan->getBillableId();
                                $subscription->end_period_last_days   = '10';
                                $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                                $subscription->end_at                 = null;
                                $subscription->end_by                 = null;
                                $subscription->payment_method_id      = $paymentMethod->id;
                                $subscription->save();

                                // add transaction
                                $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                    'end_at'                 => $subscription->end_at,
                                    'current_period_ends_at' => $subscription->current_period_ends_at,
                                    'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                    'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                    'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                                ]);

                                // add log
                                $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                    'plan'  => $subscription->plan->getBillableName(),
                                    'price' => $subscription->plan->getBillableFormattedPrice(),
                                ]);


                                $user = User::find(auth()->user()->id);

                                if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                } else {
                                    if ($plan->getOption('add_previous_balance') == 'yes') {
                                        $user->sms_unit += $plan->getOption('sms_max');
                                    } else {
                                        $user->sms_unit = $plan->getOption('sms_max');
                                    }
                                }

                                $user->save();

                                $this->createNotification('plan', $plan->name, auth()->user()->displayName());


                                //Add default Sender id
                                $this->planSenderID($plan, $user);

                                if (Helper::app_config('subscription_notification_email')) {
                                    $admin = User::find(1);
                                    $admin->notify(new SubscriptionPurchase(route('admin.invoices.view', $invoice->uid)));
                                }

                                if ($user->customer->getNotifications()['subscription'] == 'yes') {
                                    $user->notify(new SubscriptionPurchase(route('customer.invoices.view', $invoice->uid)));
                                }

                                return redirect()->route('customer.subscriptions.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }
                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => $result['output_ResponseDesc'],
                        ]);

                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_action'),
                    ]);

                } catch (Exception $exception) {
                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * PayHereLK Sender ID payment
         *
         * @param Senderid $senderid
         * @param Request  $request
         *
         * @return RedirectResponse
         */
        public function payherelkSenderID(Senderid $senderid, Request $request)
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_PAYHERELK)->first();

            if ($paymentMethod) {
                $credentials = json_decode($paymentMethod->options);

                try {

                    if ($credentials->environment == 'sandbox') {
                        $auth_url    = 'https://sandbox.payhere.lk/merchant/v1/oauth/token';
                        $payment_url = 'https://sandbox.payhere.lk/merchant/v1/payment/search';
                    } else {
                        $auth_url    = 'https://payhere.lk/merchant/v1/oauth/token';
                        $payment_url = 'https://payhere.lk/merchant/v1/payment/search';
                    }

                    $headers = [
                        'Content-Type: application/x-www-form-urlencoded',
                        'Authorization: Basic ' . base64_encode("$credentials->app_id:$credentials->app_secret"),
                    ];

                    $ch = curl_init();

                    curl_setopt($ch, CURLOPT_URL, $auth_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                    $auth_data = curl_exec($ch);

                    if (curl_errno($ch)) {

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => curl_error($ch),
                        ]);
                    }

                    curl_close($ch);

                    $result = json_decode($auth_data, true);

                    if (is_array($result)) {
                        if (array_key_exists('error_description', $result)) {

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => $result['error_description'],
                            ]);
                        }


                        $headers = [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $result['access_token'],
                        ];

                        $curl = curl_init();

                        curl_setopt($curl, CURLOPT_URL, $payment_url);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($curl, CURLOPT_POST, 1);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['order_id' => $request->get('order_id')]));
                        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

                        $payment_data = curl_exec($curl);

                        if (curl_errno($curl)) {

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => curl_error($curl),
                            ]);
                        }

                        curl_close($curl);

                        $result = json_decode($payment_data, true);

                        if (is_array($result)) {
                            if (array_key_exists('error_description', $result)) {

                                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                    'status'  => 'error',
                                    'message' => $result['error_description'],
                                ]);
                            }

                            if (array_key_exists('status', $result) && $result['status'] == '-1') {
                                return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                    'status'  => 'error',
                                    'message' => $result['msg'],
                                ]);
                            }

                            $invoice = Invoices::create([
                                'user_id'        => $senderid->user_id,
                                'currency_id'    => $senderid->currency_id,
                                'payment_method' => $paymentMethod->id,
                                'amount'         => $senderid->price,
                                'type'           => Invoices::TYPE_SENDERID,
                                'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                                'transaction_id' => $request->get('order_id'),
                                'status'         => Invoices::STATUS_PAID,
                            ]);

                            if ($invoice) {
                                $current                   = Carbon::now();
                                $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                                $senderid->status          = 'active';
                                $senderid->payment_claimed = true;
                                $senderid->save();

                                $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                                return redirect()->route('customer.senderid.index')->with([
                                    'status'  => 'success',
                                    'message' => __('locale.payment_gateways.payment_successfully_made'),
                                ]);
                            }

                            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                                'status'  => 'error',
                                'message' => __('locale.exceptions.something_went_wrong'),
                            ]);

                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);
                } catch (Exception $exception) {
                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                'status'  => 'error',
                'message' => __('locale.payment_gateways.not_found'),
            ]);
        }


        /**
         * easypay callback
         *
         * @param Request $request
         *
         * @return JsonResponse
         */
        public function easypayCallback(Request $request)
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_EASYPAY)->first();

            if ($paymentMethod) {

                $request_type = $request->get('request_type');
                $post_data    = $request->get('post_data');

                if ($request_type == 'senderid') {

                    $senderid = Senderid::findByUid($post_data);

                    $invoice = Invoices::create([
                        'user_id'        => $senderid->user_id,
                        'currency_id'    => $senderid->currency_id,
                        'payment_method' => $paymentMethod->id,
                        'amount'         => $senderid->price,
                        'type'           => Invoices::TYPE_SENDERID,
                        'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                        'transaction_id' => $senderid->uid,
                        'status'         => Invoices::STATUS_PAID,
                    ]);

                    if ($invoice) {
                        $current                   = Carbon::now();
                        $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                        $senderid->status          = 'active';
                        $senderid->payment_claimed = true;
                        $senderid->save();

                        $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                        return response()->json([
                            'status'  => 'success',
                            'url'     => route('customer.senderid.index'),
                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                        ]);
                    }

                    return response()->json([
                        'status'  => 'error',
                        'url'     => route('customer.senderid.pay', $senderid->uid),
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);
                }

                if ($request_type == 'top_up') {

                    $user = Auth::user();

                    $invoice = Invoices::create([
                        'user_id'        => $user->id,
                        'currency_id'    => $user->customer->subscription->plan->currency->id,
                        'payment_method' => $paymentMethod->id,
                        'amount'         => $user->customer->subscription->plan->getOption('per_unit_price') * $post_data,
                        'type'           => Invoices::TYPE_SUBSCRIPTION,
                        'description'    => 'Payment for sms top up',
                        'transaction_id' => uniqid(),
                        'status'         => Invoices::STATUS_PAID,
                    ]);

                    if ($invoice) {

                        if ($user->sms_unit != '-1') {
                            $user->sms_unit += $post_data;
                            $user->save();
                        }
                        $subscription = $user->customer->activeSubscription();

                        $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                            'status' => SubscriptionTransaction::STATUS_SUCCESS,
                            'title'  => 'Add ' . $post_data . ' sms units',
                            'amount' => $post_data . ' sms units',
                        ]);

                        return response()->json([
                            'status'  => 'success',
                            'url'     => route('user.home'),
                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                        ]);
                    }

                    return response()->json([
                        'status'  => 'error',
                        'url'     => route('user.home'),
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);
                }


                if ($request_type == 'number') {

                    $number = PhoneNumbers::findByUid($post_data);

                    $invoice = Invoices::create([
                        'user_id'        => auth()->user()->id,
                        'currency_id'    => $number->currency_id,
                        'payment_method' => $paymentMethod->id,
                        'amount'         => $number->price,
                        'type'           => Invoices::TYPE_NUMBERS,
                        'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                        'transaction_id' => uniqid(),
                        'status'         => Invoices::STATUS_PAID,
                    ]);

                    if ($invoice) {
                        $current               = Carbon::now();
                        $number->user_id       = auth()->user()->id;
                        $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                        $number->status        = 'assigned';
                        $number->save();

                        $this->createNotification('number', $number->number, auth()->user()->displayName());

                        return response()->json([
                            'status'  => 'success',
                            'url'     => route('customer.numbers.index'),
                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                        ]);
                    }

                    return response()->json([
                        'status'  => 'error',
                        'url'     => route('customer.numbers.pay', $number->uid),
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);
                }

                if ($request_type == 'keyword') {

                    $keyword = Keywords::findByUid($post_data);

                    $invoice = Invoices::create([
                        'user_id'        => auth()->user()->id,
                        'currency_id'    => $keyword->currency_id,
                        'payment_method' => $paymentMethod->id,
                        'amount'         => $keyword->price,
                        'type'           => Invoices::TYPE_KEYWORD,
                        'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                        'transaction_id' => uniqid(),
                        'status'         => Invoices::STATUS_PAID,
                    ]);

                    if ($invoice) {
                        $current                = Carbon::now();
                        $keyword->user_id       = auth()->user()->id;
                        $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                        $keyword->status        = 'assigned';
                        $keyword->save();

                        $this->createNotification('keyword', $keyword->keyword_name, auth()->user()->displayName());

                        return response()->json([
                            'status'  => 'success',
                            'url'     => route('customer.keywords.index'),
                            'message' => __('locale.payment_gateways.payment_successfully_made'),
                        ]);
                    }

                    return response()->json([
                        'status'  => 'error',
                        'url'     => route('customer.keywords.pay', $keyword->uid),
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);
                }


                if ($request_type == 'subscription') {

                    $plan = Plan::where('uid', $post_data)->first();
                    $user = Auth::user();

                    if ($plan && $user) {
                        $invoice = Invoices::create([
                            'user_id'        => $user->id,
                            'currency_id'    => $plan->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $plan->price,
                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                            'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                            'transaction_id' => uniqid(),
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            if (Auth::user()->customer->activeSubscription()) {
                                Auth::user()->customer->activeSubscription()->cancelNow();
                            }

                            if (Auth::user()->customer->subscription) {
                                $subscription          = Auth::user()->customer->subscription;
                                $get_options           = json_decode($subscription->options, true);
                                $output                = array_replace($get_options, [
                                    'send_warning' => false,
                                ]);
                                $subscription->options = json_encode($output);

                            } else {
                                $subscription           = new Subscription();
                                $subscription->user_id  = $user->id;
                                $subscription->start_at = Carbon::now();
                            }

                            $subscription->status                 = Subscription::STATUS_ACTIVE;
                            $subscription->plan_id                = $plan->getBillableId();
                            $subscription->end_period_last_days   = '10';
                            $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                            $subscription->end_at                 = null;
                            $subscription->end_by                 = null;
                            $subscription->payment_method_id      = $paymentMethod->id;
                            $subscription->save();

                            // add transaction
                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                'end_at'                 => $subscription->end_at,
                                'current_period_ends_at' => $subscription->current_period_ends_at,
                                'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                            ]);

                            // add log
                            $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                'plan'  => $subscription->plan->getBillableName(),
                                'price' => $subscription->plan->getBillableFormattedPrice(),
                            ]);


                            if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                $user->sms_unit = $plan->getOption('sms_max');
                            } else {
                                if ($plan->getOption('add_previous_balance') == 'yes') {
                                    $user->sms_unit += $plan->getOption('sms_max');
                                } else {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                }
                            }

                            $user->save();

                            $this->createNotification('plan', $plan->name, $user->displayName());

                            return response()->json([
                                'status'  => 'success',
                                'url'     => route('customer.subscriptions.index'),
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return response()->json([
                            'status'  => 'error',
                            'url'     => route('customer.subscriptions.purchase', $plan->uid),
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return response()->json([
                        'status'  => 'error',
                        'url'     => route('customer.subscriptions.purchase', $plan->uid),
                        'message' => __('locale.exceptions.something_went_wrong'),
                    ]);
                }


                return response()->json([
                    'status'  => 'error',
                    'url'     => route('user.home'),
                    'message' => __('locale.exceptions.something_went_wrong'),
                ]);
            }


            return response()->json([
                'status'  => 'error',
                'url'     => route('user.home'),
                'message' => __('locale.payment_gateways.not_found'),
            ]);

        }

        /*
    |--------------------------------------------------------------------------
    | FedaPay Integration
    |--------------------------------------------------------------------------
    |
    |
    |
    */


        public function fedaPayCallback(Request $request)
        {
            $paymentMethod = PaymentMethods::where('status', true)->where('type', PaymentMethods::TYPE_FEDAPAY)->first();

            if ($paymentMethod) {

                $request_type      = $request->get('request_type');
                $post_data         = $request->get('post_data');
                $transactionStatus = $request->input('transaction-status');
                $transactionId     = $request->input('transaction-id');

                if ($request_type == 'sender_id') {

                    $senderid = Senderid::findByUid($post_data);

                    if ($transactionStatus == 'approved') {

                        $invoice = Invoices::create([
                            'user_id'        => $senderid->user_id,
                            'currency_id'    => $senderid->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $senderid->price,
                            'type'           => Invoices::TYPE_SENDERID,
                            'description'    => __('locale.sender_id.payment_for_sender_id') . ' ' . $senderid->sender_id,
                            'transaction_id' => $transactionId,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current                   = Carbon::now();
                            $senderid->validity_date   = $current->add($senderid->frequency_unit, $senderid->frequency_amount);
                            $senderid->status          = 'active';
                            $senderid->payment_claimed = true;
                            $senderid->save();

                            $this->createNotification('senderid', $senderid->sender_id, User::find($senderid->user_id)->displayName());

                            return redirect()->route('customer.senderid.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('customer.senderid.pay', $senderid->uid)->with([
                        'status'  => 'error',
                        'message' => 'Your transaction status is ' . $transactionStatus,
                    ]);
                }

                if ($request_type == 'top_up') {

                    $user = User::find($request->user_id);
                    if ($transactionStatus == 'approved') {
                        $invoice = Invoices::create([
                            'user_id'        => $user->id,
                            'currency_id'    => $user->customer->subscription->plan->currency->id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $user->customer->subscription->plan->getOption('per_unit_price') * $post_data,
                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                            'description'    => 'Payment for sms top up',
                            'transaction_id' => $transactionId,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {

                            if ($user->sms_unit != '-1') {
                                $user->sms_unit += $post_data;
                                $user->save();
                            }
                            $subscription = $user->customer->activeSubscription();

                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                'status' => SubscriptionTransaction::STATUS_SUCCESS,
                                'title'  => 'Add ' . $post_data . ' sms units',
                                'amount' => $post_data . ' sms units',
                            ]);

                            return redirect()->route('customer.subscriptions.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('user.home')->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('user.home')->with([
                        'status'  => 'error',
                        'message' => 'Your transaction status is ' . $transactionStatus,
                    ]);
                }


                if ($request_type == 'number') {

                    $number = PhoneNumbers::findByUid($post_data);

                    if ($transactionStatus == 'approved') {
                        $invoice = Invoices::create([
                            'user_id'        => $number->user_id,
                            'currency_id'    => $number->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $number->price,
                            'type'           => Invoices::TYPE_NUMBERS,
                            'description'    => __('locale.phone_numbers.payment_for_number') . ' ' . $number->number,
                            'transaction_id' => $transactionId,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current               = Carbon::now();
                            $number->user_id       = auth()->user()->id;
                            $number->validity_date = $current->add($number->frequency_unit, $number->frequency_amount);
                            $number->status        = 'assigned';
                            $number->save();

                            $this->createNotification('number', $number->number, auth()->user()->displayName());


                            return redirect()->route('customer.numbers.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }


                        return redirect()->route('customer.numbers.pay', $number->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);

                    }

                    return redirect()->route('customer.numbers.pay', $number->uid)->with([
                        'status'  => 'error',
                        'message' => 'Your transaction status is ' . $transactionStatus,
                    ]);
                }

                if ($request_type == 'keyword') {

                    $keyword = Keywords::findByUid($post_data);

                    if ($transactionStatus == 'approved') {
                        $invoice = Invoices::create([
                            'user_id'        => $keyword->user_id,
                            'currency_id'    => $keyword->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $keyword->price,
                            'type'           => Invoices::TYPE_KEYWORD,
                            'description'    => __('locale.keywords.payment_for_keyword') . ' ' . $keyword->keyword_name,
                            'transaction_id' => $transactionId,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            $current                = Carbon::now();
                            $keyword->user_id       = auth()->user()->id;
                            $keyword->validity_date = $current->add($keyword->frequency_unit, $keyword->frequency_amount);
                            $keyword->status        = 'assigned';
                            $keyword->save();

                            $this->createNotification('keyword', $keyword->keyword_name, auth()->user()->displayName());

                            return redirect()->route('customer.keywords.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }


                        return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('customer.keywords.pay', $keyword->uid)->with([
                        'status'  => 'error',
                        'message' => 'Your transaction status is ' . $transactionStatus,
                    ]);
                }


                if ($request_type == 'subscription') {

                    $plan = Plan::where('uid', $post_data)->first();
                    $user = Auth::user();

                    if ($plan && $user && $transactionStatus == 'approved') {
                        $invoice = Invoices::create([
                            'user_id'        => $user->id,
                            'currency_id'    => $plan->currency_id,
                            'payment_method' => $paymentMethod->id,
                            'amount'         => $plan->price,
                            'type'           => Invoices::TYPE_SUBSCRIPTION,
                            'description'    => __('locale.subscription.payment_for_plan') . ' ' . $plan->name,
                            'transaction_id' => $transactionStatus,
                            'status'         => Invoices::STATUS_PAID,
                        ]);

                        if ($invoice) {
                            if ($user->customer->activeSubscription()) {
                                $user->customer->activeSubscription()->cancelNow();
                            }

                            if ($user->customer->subscription) {
                                $subscription          = $user->customer->subscription;
                                $get_options           = json_decode($subscription->options, true);
                                $output                = array_replace($get_options, [
                                    'send_warning' => false,
                                ]);
                                $subscription->options = json_encode($output);

                            } else {
                                $subscription           = new Subscription();
                                $subscription->user_id  = $user->id;
                                $subscription->start_at = Carbon::now();
                            }

                            $subscription->status                 = Subscription::STATUS_ACTIVE;
                            $subscription->plan_id                = $plan->getBillableId();
                            $subscription->end_period_last_days   = '10';
                            $subscription->current_period_ends_at = $subscription->getPeriodEndsAt(Carbon::now());
                            $subscription->end_at                 = null;
                            $subscription->end_by                 = null;
                            $subscription->payment_method_id      = $paymentMethod->id;
                            $subscription->save();

                            // add transaction
                            $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                                'end_at'                 => $subscription->end_at,
                                'current_period_ends_at' => $subscription->current_period_ends_at,
                                'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                                'title'                  => trans('locale.subscription.subscribed_to_plan', ['plan' => $subscription->plan->getBillableName()]),
                                'amount'                 => $subscription->plan->getBillableFormattedPrice(),
                            ]);

                            // add log
                            $subscription->addLog(SubscriptionLog::TYPE_ADMIN_PLAN_ASSIGNED, [
                                'plan'  => $subscription->plan->getBillableName(),
                                'price' => $subscription->plan->getBillableFormattedPrice(),
                            ]);


                            if ($user->sms_unit == null || $user->sms_unit == '-1' || $plan->getOption('sms_max') == '-1') {
                                $user->sms_unit = $plan->getOption('sms_max');
                            } else {
                                if ($plan->getOption('add_previous_balance') == 'yes') {
                                    $user->sms_unit += $plan->getOption('sms_max');
                                } else {
                                    $user->sms_unit = $plan->getOption('sms_max');
                                }
                            }

                            $user->save();

                            $this->createNotification('plan', $plan->name, $user->displayName());

                            return redirect()->route('customer.subscriptions.index')->with([
                                'status'  => 'success',
                                'message' => __('locale.payment_gateways.payment_successfully_made'),
                            ]);
                        }

                        return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                            'status'  => 'error',
                            'message' => __('locale.exceptions.something_went_wrong'),
                        ]);
                    }

                    return redirect()->route('customer.subscriptions.purchase', $plan->uid)->with([
                        'status'  => 'error',
                        'message' => 'Your transaction status is ' . $transactionStatus,
                    ]);
                }


                return redirect()->route('user.home')->with([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
                ]);
            }

            return redirect()->route('user.home')->with([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
            ]);

        }

        public function paytechTopUp(Request $request)
        {
            dd($request->all());
        }

    }
