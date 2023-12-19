<?php

//Customer Module
/*
|--------------------------------------------------------------------------
| Contact Module
|--------------------------------------------------------------------------
*/


Route::post('contacts/search', 'ContactsController@search')->name('contacts.search');
Route::get('contacts/export', 'ContactsController@export')->name('contacts.export');
Route::get('contacts/{contact}/show', 'ContactsController@show')->name('contacts.show');
Route::post('contacts/{contact}/copy', 'ContactsController@copy')->name('contacts.copy');
Route::post('contacts/{contact}/active', 'ContactsController@activeToggle')->name('contacts.active');
Route::post('contacts/{contact}/message', 'ContactsController@message')->name('contacts.message');
Route::post('contacts/{contact}/get-message-form', 'ContactsController@getMessageForm')->name('contacts.message_form');
Route::post('contacts/{contact}/opt-in-keyword', 'ContactsController@optInKeyword')->name('contacts.optin_keyword');
Route::post('contacts/{contact}/opt-out-keyword', 'ContactsController@optOutKeyword')->name('contacts.optout_keyword');
Route::post('contacts/{contact}/delete-opt-in-keyword', 'ContactsController@deleteOptInKeyword')->name('contacts.delete_optin_keyword');
Route::post('contacts/{contact}/delete-opt-out-keyword', 'ContactsController@deleteOptOutKeyword')->name('contacts.delete_optout_keyword');
Route::post('contacts/batch_action', 'ContactsController@batchAction')->name('contacts.batch_action');

Route::resource('contacts', 'ContactsController', [
        'only' => ['index', 'create', 'store', 'update', 'destroy'],
]);


Route::post('contacts/{contact}/search', 'ContactsController@searchContact')->name('contact.search');
Route::get('contacts/{contact}/export', 'ContactsController@exportContact')->name('contact.export');
Route::get('contacts/{contact}/import', 'ContactsController@importContact')->name('contact.import');
Route::post('contacts/{contact}/import', 'ContactsController@storeImportContact');
Route::post('contacts/{contact}/import-process', 'ContactsController@importProcessData')->name('contact.import_process');
Route::post('contacts/{contact}/batch_action', 'ContactsController@batchActionContact')->name('contact.batch_action');
Route::get('contacts/{contact}/create', 'ContactsController@createContact')->name('contact.create');
Route::post('contacts/{contact}/store', 'ContactsController@storeContact')->name('contact.store');
Route::get('contacts/{contact}/conversions', 'ContactsController@createContact')->name('contact.conversions');
Route::post('contacts/{contact}/status', 'ContactsController@updateContactStatus')->name('contact.status');
Route::get('contacts/{contact}/edit', 'ContactsController@editContact')->name('contact.edit');
Route::post('contacts/{contact}/update', 'ContactsController@updateContact')->name('contact.update');
Route::post('contacts/{contact}/delete', 'ContactsController@deleteContact')->name('contact.delete');


/*
|--------------------------------------------------------------------------
| Sender id module
|--------------------------------------------------------------------------
|
| All sender id module route describe here
|
*/

Route::post('senderid/search', 'SenderIDController@search')->name('senderid.search');
Route::get('senderid/request', 'SenderIDController@request')->name('senderid.request');
Route::post('senderid/{senderid}/active', 'SenderIDController@activeToggle')->name('senderid.active');
Route::get('senderid/{senderid}/pay', 'SenderIDController@pay')->name('senderid.pay');
Route::post('senderid/{senderid}/pay', 'SenderIDController@payment');
Route::any('senderid/{senderid}/success', 'PaymentController@successfulSenderIDPayment')->name('senderid.payment_success');
Route::any('senderid/{senderid}/cancel', 'PaymentController@cancelledSenderIDPayment')->name('senderid.payment_cancel');
Route::any('senderid/{senderid}/fail', 'PaymentController@failedSenderIDPayment')->name('senderid.payment_fail');
Route::post('senderid/{senderid}/braintree', 'PaymentController@braintreeSenderID')->name('senderid.braintree');
Route::post('senderid/{senderid}/authorize-net', 'PaymentController@authorizeNetSenderID')->name('senderid.authorize_net');
Route::post('senderid/{senderid}/vodacommpesa', 'PaymentController@vodacommpesaSenderID')->name('senderid.vodacommpesa');
Route::post('senderid/batch_action', 'SenderIDController@batchAction')->name('senderid.batch_action');
Route::resource('senderid', 'SenderIDController', [
        'only' => ['index', 'store', 'destroy'],
]);


//top up payment


Route::any('payment/top-up/success', 'PaymentController@successfulTopUpPayment')->name('top_up.payment_success');
Route::any('payment/top-up/cancel', 'PaymentController@cancelledTopUpPayment')->name('top_up.payment_cancel');
Route::any('payment/top-up/fail', 'PaymentController@failedTopUpPayment')->name('top_up.payment_fail');
Route::post('payment/top-up/braintree', 'PaymentController@braintreeTopUp')->name('top_up.braintree');
Route::post('payment/top-up/authorize-net', 'PaymentController@authorizeNetTopUp')->name('top_up.authorize_net');
Route::post('payment/top-up/vodacommpesa', 'PaymentController@vodacommpesaTopUp')->name('top_up.vodacommpesa');

/**
 * Payment gateways callback
 */
Route::any('callback/paystack', 'PaymentController@paystack')->name('callback.paystack');
Route::any('callback/paynow', 'PaymentController@paynow')->name('callback.paynow');


Route::any('callback/razorpay/senderid', 'PaymentController@razorpaySenderID')->name('callback.razorpay.senderid');
Route::any('callback/razorpay/numbers', 'PaymentController@razorpayNumbers')->name('callback.razorpay.numbers');
Route::any('callback/razorpay/keywords', 'PaymentController@razorpayKeywords')->name('callback.razorpay.keywords');
Route::any('callback/razorpay/subscriptions', 'PaymentController@razorpaySubscriptions')->name('callback.razorpay.subscriptions');
Route::any('callback/razorpay/top-up', 'PaymentController@razorpayTopUp')->name('callback.razorpay.top_up');


Route::any('callback/sslcommerz/senderid', 'PaymentController@sslcommerzSenderID')->name('callback.sslcommerz.senderid');
Route::any('callback/sslcommerz/numbers', 'PaymentController@sslcommerzNumbers')->name('callback.sslcommerz.numbers');
Route::any('callback/sslcommerz/keywords', 'PaymentController@sslcommerzKeywords')->name('callback.sslcommerz.keywords');
Route::any('callback/sslcommerz/subscriptions', 'PaymentController@sslcommerzSubscriptions')->name('callback.sslcommerz.subscriptions');
Route::any('callback/sslcommerz/top-up', 'PaymentController@sslcommerzTopUp')->name('callback.sslcommerz.top_up');


Route::any('callback/aamarpay/senderid', 'PaymentController@aamarpaySenderID')->name('callback.aamarpay.senderid');
Route::any('callback/aamarpay/numbers', 'PaymentController@aamarpayNumbers')->name('callback.aamarpay.numbers');
Route::any('callback/aamarpay/keywords', 'PaymentController@aamarpayKeywords')->name('callback.aamarpay.keywords');
Route::any('callback/aamarpay/subscriptions', 'PaymentController@aamarpaySubscriptions')->name('callback.aamarpay.subscriptions');
Route::any('callback/aamarpay/top-up', 'PaymentController@aamarpayTopUp')->name('callback.aamarpay.top_up');


Route::any('callback/flutterwave/senderid', 'PaymentController@flutterwaveSenderID')->name('callback.flutterwave.senderid');
Route::any('callback/flutterwave/numbers', 'PaymentController@flutterwaveNumbers')->name('callback.flutterwave.numbers');
Route::any('callback/flutterwave/keywords', 'PaymentController@flutterwaveKeywords')->name('callback.flutterwave.keywords');
Route::any('callback/flutterwave/subscriptions', 'PaymentController@flutterwaveSubscriptions')->name('callback.flutterwave.subscriptions');
Route::any('callback/flutterwave/top-up', 'PaymentController@flutterwaveTopUp')->name('callback.flutterwave.top_up');


/*Version 3.8*/
Route::any('callback/paytech/senderid', 'PaymentController@paytechSenderID')->name('callback.paytech.senderid');
Route::any('callback/paytech/numbers', 'PaymentController@paytechNumbers')->name('callback.paytech.numbers');
Route::any('callback/paytech/keywords', 'PaymentController@paytechKeywords')->name('callback.paytech.keywords');
Route::any('callback/paytech/subscriptions', 'PaymentController@paytechSubscriptions')->name('callback.paytech.subscriptions');
Route::any('callback/paytech/top-up', 'PaymentController@paytechTopUp')->name('callback.paytech.top_up');


/*
|--------------------------------------------------------------------------
| Phone number module
|--------------------------------------------------------------------------
|
| All phone number module route describe here
|
*/

Route::post('numbers/search', 'NumberController@search')->name('numbers.search');
Route::get('numbers/buy', 'NumberController@buy')->name('numbers.buy');
Route::post('numbers/available', 'NumberController@availableNumbers')->name('numbers.available_numbers');
Route::post('numbers/release/{id}', 'NumberController@release')->name('numbers.release');
Route::post('numbers/batch_action', 'NumberController@batchAction')->name('numbers.batch_action');
Route::resource('numbers', 'NumberController', [
        'only' => ['index'],
]);

Route::get('numbers/{number}/pay', 'NumberController@pay')->name('numbers.pay');
Route::post('numbers/{number}/pay', 'NumberController@payment');
Route::any('numbers/{number}/success', 'PaymentController@successfulNumberPayment')->name('numbers.payment_success');
Route::any('numbers/{number}/cancel', 'PaymentController@cancelledNumberPayment')->name('numbers.payment_cancel');
Route::post('numbers/{number}/braintree', 'PaymentController@braintreeNumber')->name('numbers.braintree');
Route::post('numbers/{number}/authorize-net', 'PaymentController@authorizeNetNumber')->name('numbers.authorize_net');
Route::post('numbers/{number}/vodacommpesa', 'PaymentController@vodacommpesaNumber')->name('numbers.vodacommpesa');

/*Version 3.6*/

Route::get('numbers/buy-using-api/{sending_server}', 'NumberController@buyUsingAPI')->name('numbers.buy.api');
Route::post('numbers/buy-using-api/{sending_server}', 'NumberController@searchNumbersUsingAPI');
Route::get('numbers/purchase/{number}/{sending_server}', 'NumberController@purchaseNumber')->name('numbers.purchase');

/*
|--------------------------------------------------------------------------
| Keywords module
|--------------------------------------------------------------------------
|
| Route for Keywords module
|
*/

Route::post('keywords/search', 'KeywordController@search')->name('keywords.search');
Route::get('keywords/create', 'KeywordController@create')->name('keywords.create');
Route::get('keywords/buy', 'KeywordController@buy')->name('keywords.buy');
Route::post('keywords/available', 'KeywordController@available')->name('keywords.available');
Route::get('keywords/{keyword}/show', 'KeywordController@show')->name('keywords.show');
Route::post('keywords/{keyword}/remove-mms', 'KeywordController@removeMMS')->name('keywords.remove-mms');
Route::post('keywords/release/{id}', 'KeywordController@release')->name('keywords.release');
Route::post('keywords/batch_action', 'KeywordController@batchAction')->name('keywords.batch_action');

Route::resource('keywords', 'KeywordController', [
        'only' => ['index', 'create', 'store', 'update'],
]);


Route::get('keywords/{keyword}/pay', 'KeywordController@pay')->name('keywords.pay');
Route::post('keywords/{keyword}/pay', 'KeywordController@payment');
Route::any('keywords/{keyword}/success', 'PaymentController@successfulKeywordPayment')->name('keywords.payment_success');
Route::any('keywords/{keyword}/cancel', 'PaymentController@cancelledKeywordPayment')->name('keywords.payment_cancel');
Route::post('keywords/{keyword}/braintree', 'PaymentController@braintreeKeyword')->name('keywords.braintree');
Route::post('keywords/{keyword}/authorize-net', 'PaymentController@authorizeNetKeyword')->name('keywords.authorize_net');
Route::post('keywords/{keyword}/vodacommpesa', 'PaymentController@vodacommpesaKeyword')->name('keywords.vodacommpesa');


/*
|--------------------------------------------------------------------------
| SMS Template module
|--------------------------------------------------------------------------
|
| Route for sms template module
|
*/

Route::post('templates/search', 'TemplateController@search')->name('templates.search');
Route::get('templates/export', 'TemplateController@export')->name('templates.export');
Route::get('templates/{template}/show', 'TemplateController@show')->name('templates.show');
Route::post('templates/{template}/active', 'TemplateController@activeToggle')->name('templates.active');
Route::post('templates/batch_action', 'TemplateController@batchAction')->name('templates.batch_action');

Route::resource('templates', 'TemplateController', [
        'only' => ['index', 'create', 'store', 'update', 'destroy'],
]);

/*
|-------------------------------------------------------------------------
| Blacklists Module Routes
|-------------------------------------------------------------------------
|
| working with blacklists features in this module
|
*/

Route::post('blacklists/search', 'BlacklistsController@search')->name('blacklists.search');
Route::post('blacklists/batch_action', 'BlacklistsController@batchAction')->name('blacklists.batch_action');
Route::resource('blacklists', 'BlacklistsController', [
        'only' => ['index', 'create', 'store', 'destroy'],
]);


/*
|--------------------------------------------------------------------------
| Subscription module routes
|--------------------------------------------------------------------------
|
|
|
*/

Route::get('subscriptions/{subscription}/renew', 'SubscriptionController@renew')->name('subscriptions.renew');
Route::post('subscriptions/{subscription}/renew', 'SubscriptionController@renewPost');
Route::post('subscriptions/{subscription}/cancel', 'SubscriptionController@cancel')->name('subscriptions.cancel');
Route::get('subscriptions/{subscription}/logs', 'SubscriptionController@logs')->name('subscriptions.logs');
Route::get('subscriptions/{plan}/change-plan', 'SubscriptionController@changePlan')->name('subscriptions.change_plan');
Route::post('subscriptions/{plan}/change-plan', 'SubscriptionController@changePlan');

Route::get('subscriptions/{plan}/purchase', 'SubscriptionController@purchase')->name('subscriptions.purchase');
Route::post('subscriptions/{plan}/purchase', 'SubscriptionController@checkoutPurchase');

Route::resource('subscriptions', 'SubscriptionController', [
        'only' => ['index', 'create', 'store', 'destroy'],
]);

Route::any('subscriptions/{plan}/success', 'PaymentController@successfulSubscriptionPayment')->name('subscriptions.payment_success');
Route::any('subscriptions/{plan}/cancel', 'PaymentController@cancelledSubscriptionPayment')->name('subscriptions.payment_cancel');
Route::post('subscriptions/{plan}/braintree', 'PaymentController@braintreeSubscription')->name('subscriptions.braintree');
Route::post('subscriptions/{plan}/authorize-net', 'PaymentController@authorizeNetSubscriptions')->name('subscriptions.authorize_net');
Route::post('subscriptions/{plan}/vodacommpesa', 'PaymentController@vodacommpesaSubscriptions')->name('subscriptions.vodacommpesa');

Route::post('subscriptions/{subscription}/preferences', 'SubscriptionController@preferences')->name('subscriptions.preferences');

Route::post('invoices/search', 'InvoiceController@search')->name('invoices.search');
Route::get('invoices/{invoice}/view', 'InvoiceController@view')->name('invoices.view');
Route::get('invoices/{invoice}/print', 'InvoiceController@print')->name('invoices.print');

/*Version 3.4*/
Route::post('payment/{type}/offline', 'PaymentController@offlinePayment')->name('payment.offline');
 /*
|--------------------------------------------------------------------------
| Quick Reply module
|--------------------------------------------------------------------------
|
| Route for  Quick Replies  module
|
*/
Route::resource('quick-replies', QuickResponseController::class);

/*
|--------------------------------------------------------------------------
| Campaign module
|--------------------------------------------------------------------------
|
| Campaign module is the most important module of ultimate sms. This module contains send sms, voice messages,
| picture messages and whatsapp messages options.
|
*/

Route::prefix('sms')->name('sms.')->group(function () {
    Route::get('/quick-send', 'CampaignController@quickSend')->name('quick_send');
    Route::post('/quick-send', 'CampaignController@postQuickSend');
    Route::get('/campaign-builder', 'CampaignController@campaignBuilder')->name('campaign_builder');
    Route::post('/campaign-builder', 'CampaignController@storeCampaign');
    Route::get('/import', 'CampaignController@import')->name('import');
    Route::post('/import', 'CampaignController@importCampaign');
    Route::post('/import_process', 'CampaignController@importProcess')->name('import_process');
});

Route::prefix('voice')->name('voice.')->group(function () {
    Route::get('/quick-send', 'CampaignController@voiceQuickSend')->name('quick_send');
    Route::post('/quick-send', 'CampaignController@postVoiceQuickSend');
    Route::get('/campaign-builder', 'CampaignController@voiceCampaignBuilder')->name('campaign_builder');
    Route::post('/campaign-builder', 'CampaignController@storeVoiceCampaign');
    Route::get('/import', 'CampaignController@voiceImport')->name('import');
    Route::post('/import', 'CampaignController@importVoiceCampaign');
    Route::post('/import_process', 'CampaignController@voiceImportProcess')->name('import_process');
});

Route::prefix('mms')->name('mms.')->group(function () {
    Route::get('/quick-send', 'CampaignController@mmsQuickSend')->name('quick_send');
    Route::post('/quick-send', 'CampaignController@postMMSQuickSend');
    Route::get('/campaign-builder', 'CampaignController@mmsCampaignBuilder')->name('campaign_builder');
    Route::post('/campaign-builder', 'CampaignController@storeMMSCampaign');
    Route::get('/import', 'CampaignController@mmsImport')->name('import');
    Route::post('/import', 'CampaignController@importMMSCampaign');
    Route::post('/import_process', 'CampaignController@mmsImportProcess')->name('import_process');
});


Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
    Route::get('/quick-send', 'CampaignController@whatsappQuickSend')->name('quick_send');
    Route::post('/quick-send', 'CampaignController@postWhatsAppQuickSend');
    Route::get('/campaign-builder', 'CampaignController@whatsappCampaignBuilder')->name('campaign_builder');
    Route::post('/campaign-builder', 'CampaignController@storeWhatsAppCampaign');
    Route::get('/import', 'CampaignController@whatsappImport')->name('import');
    Route::post('/import', 'CampaignController@importWhatsAppCampaign');
    Route::post('/import_process', 'CampaignController@whatsappImportProcess')->name('import_process');
});

/*Version 3.8*/
Route::prefix('viber')->name('viber.')->group(function () {
    Route::get('/quick-send', 'CampaignController@viberQuickSend')->name('quick_send');
    Route::post('/quick-send', 'CampaignController@postViberQuickSend');
    Route::get('/campaign-builder', 'CampaignController@viberCampaignBuilder')->name('campaign_builder');
    Route::post('/campaign-builder', 'CampaignController@storeViberCampaign');
    Route::get('/import', 'CampaignController@viberImport')->name('import');
    Route::post('/import', 'CampaignController@importViberCampaign');
    Route::post('/import_process', 'CampaignController@viberImportProcess')->name('import_process');
});

Route::prefix('otp')->name('otp.')->group(function () {
    Route::get('/quick-send', 'CampaignController@otpQuickSend')->name('quick_send');
    Route::post('/quick-send', 'CampaignController@postOTPQuickSend');
    Route::get('/campaign-builder', 'CampaignController@otpCampaignBuilder')->name('campaign_builder');
    Route::post('/campaign-builder', 'CampaignController@storeOTPCampaign');
    Route::get('/import', 'CampaignController@otpImport')->name('import');
    Route::post('/import', 'CampaignController@importOTPCampaign');
    Route::post('/import_process', 'CampaignController@otpImportProcess')->name('import_process');
});

Route::post('templates/show-data/{id}', 'CampaignController@templateData')->name('templates.show_data');

/*
|--------------------------------------------------------------------------
| Reports module
|--------------------------------------------------------------------------
|
|
|
*/

Route::prefix('reports')->name('reports.')->group(function () {
    Route::post('/{uid}/destroy', 'ReportsController@destroy');
    Route::get('/all', 'ReportsController@reports')->name('all');
    Route::post('/{uid}/view', 'ReportsController@viewReports');
    Route::post('/export', 'ReportsController@export')->name('export.all');
    Route::get('/export/sent', 'ReportsController@exportSent')->name('export.sent');
    Route::get('/export/receive', 'ReportsController@exportReceive')->name('export.receive');
    Route::get('/export/{campaign}', 'ReportsController@exportCampaign')->name('export.campaign');
    Route::get('/received', 'ReportsController@received')->name('received');
    Route::get('/sent', 'ReportsController@sent')->name('sent');
    Route::get('/campaigns', 'ReportsController@campaigns')->name('campaigns');
    Route::post('/search', 'ReportsController@searchAllMessages')->name('search.all');
    Route::post('/search/received', 'ReportsController@searchReceivedMessage')->name('search.received');
    Route::post('/search/sent', 'ReportsController@searchSentMessage')->name('search.sent');
    Route::post('/search/campaigns', 'ReportsController@searchCampaigns')->name('search.campaigns');
    Route::post('batch_action', 'ReportsController@batchAction')->name('batch_action');

    Route::get('/campaigns/{campaign}/edit', 'ReportsController@editCampaign')->name('campaign.edit');
    Route::post('/campaigns/{campaign}/edit', 'ReportsController@postEditCampaign');

    Route::get('/campaigns/{campaign}/overview', 'ReportsController@campaignOverview')->name('campaign.overview');
    Route::post('/campaigns/{campaign}/reports', 'ReportsController@campaignReports')->name('campaign.reports');
    Route::post('/campaigns/{campaign}/delete', 'ReportsController@campaignDelete')->name('campaign.delete');
    Route::post('/campaign/batch_action', 'ReportsController@campaignBatchAction')->name('campaign.batch_action');
    Route::get('/campaign/export', 'ReportsController@campaignExport')->name('campaign.export');

    //Version 3.5
    Route::post('/campaigns/{campaign}/pause', 'CampaignController@campaignPause')->name('campaign.pause');
    Route::post('/campaigns/{campaign}/restart', 'CampaignController@campaignRestart')->name('campaign.restart');
    Route::post('/campaigns/{campaign}/resend', 'CampaignController@campaignResend')->name('campaign.resend');

    //Version 3.7
    Route::get('/analyze', 'ReportsController@analyze')->name('analyze');
    Route::post('/analyze', 'ReportsController@postAnalyze');

});

Route::get('/view-charts', 'ReportsController@viewCharts')->name('view.charts');


/*
|--------------------------------------------------------------------------
| Check box module
|--------------------------------------------------------------------------
| 1. create database ->

scenario:
Customer send message
  -> check previous data is available or not. If available then update message and channel name
      if not then create new channel and store data. Finally update send by customer
Admin send message
  -> check previous data is available or not. If available show in chat list. When click on send button update message and channel name
     if not then click on start conversion and redirect new page with select number and customer number. Finally, create new channel, store data
     and update send by admin
|
|
|
*/
Route::prefix('chat-box')->name('chatbox.')->group(function () {
    Route::get('/', 'ChatBoxController@index')->name('index');
    Route::get('/new', 'ChatBoxController@new')->name('new');
    Route::post('/search', 'ChatBoxController@search')->name('search');
    Route::post('/sent', 'ChatBoxController@sent')->name('sent');
    Route::post('/{box}/messages', 'ChatBoxController@messages')->name('messages');
    Route::post('/{box}/messages/trash', 'ChatBoxController@messages_trash')->name('messages.trash');
    Route::post('/{box}/notification', 'ChatBoxController@messagesWithNotification')->name('notification');
    Route::post('/{box}/reply', 'ChatBoxController@reply')->name('reply');
    Route::post('/{box}/swap_num', 'ChatBoxController@swap_num')->name('swap_num');
    Route::post('/{box}/delete', 'ChatBoxController@delete')->name('delete');
    Route::post('/{box}/restore', 'ChatBoxController@restore')->name('restore');
    Route::post('/b_delete', 'ChatBoxController@bulkDelete')->name('b_delete');
    Route::post('/{box}/block', 'ChatBoxController@block')->name('block');
    Route::get('/trash', 'ChatBoxController@trash')->name('trash');
    Route::get('/test', 'ChatBoxController@test')->name('test');
});

/*
|--------------------------------------------------------------------------
| Developer module
|--------------------------------------------------------------------------
|
|
|
*/
Route::get('developers', 'DeveloperController@settings')->name('developer.settings');
Route::post('developers/generate', 'DeveloperController@generate')->name('developer.generate');
Route::post('developers/sending-server', 'DeveloperController@sendingServer')->name('developer.server');
Route::get('developers/docs', 'DeveloperController@docs')->name('developer.docs');
Route::get('developers/http-docs', 'DeveloperController@httpDocs')->name('developer.http-docs');

/*
 * Version 3.6
 *
 * EasyPay.pt
 */
Route::any('callback/easypay', 'PaymentController@easypayCallback')->name('callback.easypay');
Route::any('callback/fedapay', 'PaymentController@fedaPayCallback')->name('callback.fedapay');


/*
|--------------------------------------------------------------------------
| Automations Module
|--------------------------------------------------------------------------
|
| Send Birthday Message, Say Good bye to subscribers and all automations will discuss here
|
*/
Route::prefix('automations')->name('automations.')->group(function () {
    Route::get('/', 'AutomationsController@index')->name('index');
    Route::post('/search', 'AutomationsController@search')->name('search');
    Route::get('/create', 'AutomationsController@create')->name('create');
    Route::get('/say-happy-birthday', 'AutomationsController@sayHappyBirthday')->name('say.happy.birthday');
    Route::post('/say-happy-birthday', 'AutomationsController@postSayHappyBirthday');
    Route::get('/say-goodbye', 'AutomationsController@sayGoodBye')->name('say.goodbye');

    Route::get('/{automation}/show', 'AutomationsController@show')->name('show');
    Route::post('/{automation}/disable', 'AutomationsController@disable')->name('disable');
    Route::post('/{automation}/enable', 'AutomationsController@enable')->name('enable');
    Route::post('/{automation}/delete', 'AutomationsController@delete')->name('delete');
    Route::post('batch_action', 'AutomationsController@batchAction')->name('batch_action');
    Route::post('/export', 'AutomationsController@export')->name('export');
    Route::post('/{automation}/reports', 'AutomationsController@reports')->name('reports');
    Route::post('/{automation}/{subscriber}/send', 'AutomationsController@sendNow')->name('send');


});
