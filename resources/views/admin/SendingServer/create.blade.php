@php use App\Models\SendingServer; @endphp
@extends('layouts/contentLayoutMaster')

@section('title', $server['name'])


@section('content')
    <!-- Basic Vertical form layout section start -->
    <section id="basic-vertical-layouts">
        <div class="row match-height">
            <div class="col-md-6 col-12">

                <form class="form form-vertical"
                      @if(isset($server['id'])) action="{{ route('admin.sending-servers.update',  $server['uid']) }}"
                      @else action="{{ route('admin.sending-servers.store') }}" @endif method="post">
                    @if(isset($server['id']))
                        {{ method_field('PUT') }}
                    @endif
                    @csrf

                    {{--Update Server Credential--}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"> {{__('locale.sending_servers.update_credentials')}} </h4>
                        </div>
                        <div class="card-content">
                            <div class="card-body">

                                @switch($server['settings'])
                                    @case('Twilio')
                                        <p>{!!  __('locale.description.twilio', ['brandname' => config('app.name'), 'url' => route('inbound.twilio')]) !!}</p>
                                        @break

                                    @case('TwilioCopilot')
                                        <p>{!!  __('locale.description.twilio', ['brandname' => config('app.name'), 'url' => route('inbound.twilio_copilot')]) !!}</p>
                                        @break

                                    @case('ClickatellTouch')
                                    @case('ClickatellCentral')
                                        <p>{!!  __('locale.description.clickatell') !!} {{config('app.name')}}. For
                                            Inbound Message Here is the Callback URL:
                                            <code>{{ route('inbound.clickatell') }}</code></p>
                                        @break

                                    @case('RouteMobile')
                                        <p> {!! __('locale.description.route_mobile', ['brandname' => config('app.name'), 'url' => route('dlr.routemobile')]) !!}
                                            .</p>
                                        @break

                                    @case('TextLocal')
                                        <p> {!! __('locale.description.text_local', ['brandname' => config('app.name'), 'url' => route('inbound.textlocal')]) !!}</p>
                                        @break

                                    @case('msg91')
                                        <p> {!! __('locale.description.mgs91') !!} {{config('app.name')}}.</p>
                                        @break

                                    @case('Plivo')
                                        <p> {!! __('locale.description.plivo', ['brandname' => config('app.name'), 'url' => route('inbound.plivo')]) !!}</p>
                                        @break

                                    @case('PlivoPowerpack')
                                        <p> {!! __('locale.description.plivo', ['brandname' => config('app.name'), 'url' => route('inbound.plivo_powerpack')]) !!}</p>
                                        @break

                                    @case('SMSGlobal')
                                        <p> {!! __('locale.description.sms_global', ['brandname' => config('app.name'), 'url' => route('dlr.smsglobal')]) !!}</p>
                                        @break

                                    @case('BulkSMS')
                                        <p> {!! __('locale.description.bulk_sms', ['brandname' => config('app.name'), 'url' => route('inbound.bulksms')]) !!}</p>
                                        @break

                                    @case('Vonage')
                                        <p> {!! __('locale.description.vonage', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.vonage'), 'dlr_url' => route('dlr.vonage')]) !!}</p>
                                        @break

                                    @case('Infobip')
                                        <p> {!! __('locale.description.infobip') !!}. For Inbound Message Here is the
                                            Callback URL: <code>{{ route('inbound.infobip') }}</code></p>
                                        @break

                                    @case('1s2u')
                                        <p> {!! __('locale.description.1s2u', ['brandname' => config('app.name'), 'dlr_url' => route('dlr.1s2u')]) !!}</p>
                                        @break

                                    @case('SmsGatewayMe')
                                        <p> {!! __('locale.description.sms_gateway_me') !!} {{config('app.name')}}.</p>
                                        @break

                                    @case('MessageBird')
                                        <p> {!! __('locale.description.messagebird', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.messagebird')]) !!}</p>
                                        @break

                                    @case('AmazonSNS')
                                        <p> {!! __('locale.description.amazon_sns') !!} {{config('app.name')}}.</p>
                                        @break

                                    @case('Tyntec')
                                        <p> {!! __('locale.description.tyntec') !!} {{config('app.name')}}.</p>
                                        @break

                                    @case('WhatsAppChatApi')
                                        <p> {!! __('locale.description.whatsapp_chat_api', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.chatapi')]) !!}</p>
                                        @break

                                    @case('KarixIO')
                                        <p> {!! __('locale.description.karixio') !!} {{config('app.name')}}.</p>
                                        @break

                                    @case('SignalWire')
                                        <p> {!! __('locale.description.signal_wire', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.signalwire')]) !!}</p>
                                        @break

                                    @case('FlowRoute')
                                        <p> {!! __('locale.description.flowroute', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.flowroute')]) !!}</p>
                                        @break

                                    @case('Telnyx')
                                    @case('TelnyxNumberPool')
                                        <p> {!! __('locale.description.telnyx', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.telnyx')]) !!} </p>
                                        @break

                                    @case('Solucoesdigitais')
                                        <p> {!! __('locale.description.Solucoesdigitais', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.solucoesdigitais')]) !!} </p>
                                        @break

                                    @case('Bandwidth')
                                        <p> {!! __('locale.description.bandwidth', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.bandwidth')]) !!} </p>
                                        @break

                                    @case('SMPP')
                                        <p> {!! __('locale.description.smpp', ['brandname' => config('app.name')]) !!} {{config('app.name')}}
                                            .</p>
                                        @break

                                    @case('Teletopiasms')
                                        <p> {!! __('locale.description.teletopiasms', ['brandname' => config('app.name'), 'url' => route('inbound.teletopiasms')]) !!}</p>
                                        @break

                                    @case('EasySendSMS')
                                        <p> {!! __('locale.description.easysendsms', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.easysendsms'), 'dlr_url' => route('dlr.easysendsms')]) !!}</p>
                                        @break

                                    @case('CMCOM')
                                        <p> {!! __('locale.description.cmcom', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.cm'), 'dlr_url' => route('dlr.cm')]) !!}</p>
                                        @break

                                    @case('Gatewayapi')
                                        <p> {!! __('locale.description.gatewayapi', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.gatewayapi'), 'dlr_url' => route('dlr.gatewayapi')]) !!}</p>
                                        @break

                                    @case('Skyetel')
                                        <p> {!! __('locale.description.skyetel', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.skyetel'), 'brand_url' => 'https://support.skyetel.com/hc/en-us/articles/360056299914-SMS-MMS-API']) !!}</p>
                                        @break

                                    @case('AfricasTalking')
                                        <p> {!! __('locale.description.AfricasTalking', ['brandname' => config('app.name'), 'dlr_url' => route('dlr.africastalking')]) !!}</p>
                                        @break

                                    @case(SendingServer::TYPE_TELEAPI)
                                        <p>For Inbound Message Here is the Callback URL:
                                            <code>{{ route('inbound.teleapi') }}</code></p>
                                        @break

                                    @case(SendingServer::TYPE_NIMBUZ)
                                        <p> {!! __('locale.description.nimbuz', ['dlr_url' => route('dlr.nimbuz')]) !!}
                                            .</p>
                                        @break

                                    @case('GatewaySa')
                                        <p> {!! __('locale.description.gatewaysa', ['dlr_url' => route('dlr.gatewaysa')]) !!}</p>
                                        @break

                                    @case(SendingServer::TYPE_WHATSAPP)
                                        <p> You will find the Language code here <a
                                                    href="https://developers.facebook.com/docs/whatsapp/api/messages/message-templates/"
                                                    target="_blank">View a list of supported languages for message
                                                templates.</a></p>
                                        @break

                                    @case(SendingServer::TYPE_CHEAPGLOBALSMS)
                                        <p>Login with your CheapSMSGlobal portal then click on <code>2-Way-SMS ->
                                                Campaigns: Listeners & Forwarders</code> option. Finally insert this URL
                                            <code>{{ route('inbound.cheapglobalsms') }}</code> on your <code>Callback
                                                URL</code> field</p>
                                        @break

                                    @case(SendingServer::TYPE_VOXIMPLANT)
                                        <p>To receive inbound sms, go to the Voximplant control panel's Webhooks section
                                            and click Add in the middle of the screen. The two text fields appear:
                                            Callback URL and Security salt. In the Callback URL, specify the URL
                                            <code>{{ route('inbound.voximplant') }}</code> and click Save.</p>
                                        @break

                                    @case(SendingServer::TYPE_SMSMODE)
                                        <p> {!! __('locale.description.smsmode', ['brandname' => config('app.name'), 'inbound_url' => route('inbound.smsmode'), 'dlr_url' => route('dlr.smsmode')]) !!}</p>
                                        @break

                                    @case('Inteliquent')
                                        <p>To receive inbound message please update this url on your inbound Callback
                                            URL {{ route('inbound.inteliquent') }}</p>
                                        @break

                                    @case(SendingServer::TYPE_CLICKSEND)
                                        <p>For Inbound Message Here is the Callback URL:
                                            <code>{{ route('inbound.clicksend') }}</code></p>
                                        @break

                                @endswitch

                                <div class="form-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="mb-1">
                                                <label class="form-label required"
                                                       for="name">{{ __('locale.labels.name') }}</label>
                                                <input type="text" id="name"
                                                       class="form-control @error('name') is-invalid @enderror"
                                                       value="{{ $server['name'] }}" name="name" required>
                                                @error('name')
                                                <p><small class="text-danger">{{ $message }}</small></p>
                                                @enderror
                                            </div>
                                        </div>

                                        @if( $server['settings'] != 'Twilio' && $server['settings'] != 'Zang' && $server['settings'] != 'Plivo' && $server['settings'] != 'PlivoPowerpack' && $server['settings'] != 'AmazonSNS' && $server['settings'] != 'TwilioCopilot')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    @if($server['settings'] == 'SignalWire')
                                                        <label class="form-label required" for="api_link">SPACE
                                                            URL</label>
                                                    @elseif($server['settings'] == 'SMPP')
                                                        <label class="form-label required"
                                                               for="api_link">IP/DOMAIN</label>
                                                    @else
                                                        <label class="form-label required" for="api_link">API
                                                            Link</label>
                                                    @endif
                                                    <input type="text" id="api_link"
                                                           class="form-control @error('api_link') is-invalid @enderror"
                                                           value="{{ $server['api_link'] }}" name="api_link" required>
                                                    @error('api_link')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_MESSAGEBIRD || $server['settings'] == SendingServer::TYPE_INFOBIP || $server['settings'] == SendingServer::TYPE_TELNYX || $server['settings'] == SendingServer::TYPE_BULKSMSPROVIDERNG || $server['settings'] == SendingServer::TYPE_AIRTELINDIA)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="voice_api_link">Voice API
                                                        Link</label>

                                                    <input type="text"
                                                           id="voice_api_link"
                                                           class="form-control @error('voice_api_link') is-invalid @enderror"
                                                           value="{{ $server['voice_api_link'] }}"
                                                           name="voice_api_link"
                                                           required>

                                                    @error('voice_api_link')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_MESSAGEBIRD || $server['settings'] == SendingServer::TYPE_TEXTLOCAL || $server['settings'] == SendingServer::TYPE_SMSGLOBAL || $server['settings'] == SendingServer::TYPE_TXTRIA || $server['settings'] == SendingServer::TYPE_TELEAPI || $server['settings'] == SendingServer::TYPE_BULKREPLY)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="mms_api_link">MMS API
                                                        Link</label>

                                                    <input type="text"
                                                           id="mms_api_link"
                                                           class="form-control @error('mms_api_link') is-invalid @enderror"
                                                           value="{{ $server['mms_api_link'] }}"
                                                           name="mms_api_link"
                                                           required>

                                                    @error('mms_api_link')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_MESSAGEBIRD)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="whatsapp_api_link">Whatsapp
                                                        API Link</label>

                                                    <input type="text"
                                                           id="whatsapp_api_link"
                                                           class="form-control @error('whatsapp_api_link') is-invalid @enderror"
                                                           value="{{ $server['whatsapp_api_link'] }}"
                                                           name="whatsapp_api_link"
                                                           required>

                                                    @error('whatsapp_api_link')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_MSG91 || $server['settings'] == SendingServer::TYPE_VOICEANDTEXT)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="otp_api_link">OTP API
                                                        Link</label>

                                                    <input type="text"
                                                           id="otp_api_link"
                                                           class="form-control @error('otp_api_link') is-invalid @enderror"
                                                           value="{{ $server['otp_api_link'] }}"
                                                           name="otp_api_link"
                                                           required>

                                                    @error('otp_api_link')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif


                                        @if($server['settings'] == 'Twilio' || $server['settings'] == 'TwilioCopilot' || $server['settings'] == 'Skyetel' )
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="account_sid">Account
                                                        Sid</label>
                                                    <input type="text" id="account_sid"
                                                           class="form-control @error('account_sid') is-invalid @enderror"
                                                           value="{{ $server['account_sid'] }}" name="account_sid"
                                                           required>
                                                    @error('account_sid')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'Plivo' || $server['settings'] == 'PlivoPowerpack'  || $server['settings'] == 'KarixIO' )
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="auth_id">Auth ID</label>
                                                    <input type="text" id="auth_id"
                                                           class="form-control @error('auth_id') is-invalid @enderror"
                                                           value="{{ $server['auth_id'] }}" name="auth_id" required>
                                                    @error('auth_id')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_VIBER)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="auth_token">Auth
                                                        Token</label>
                                                    <input type="text" id="auth_token"
                                                           class="form-control @error('auth_token') is-invalid @enderror"
                                                           value="{{ $server['auth_token'] }}" name="auth_token"
                                                           required>
                                                    @error('auth_token')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif


                                        @if($server['settings'] == 'SpeedaMobile' || $server['settings'] == 'SMSala')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="auth_id">API ID</label>
                                                    <input type="text" id="auth_id"
                                                           class="form-control @error('auth_id') is-invalid @enderror"
                                                           value="{{ $server['auth_id'] }}" name="auth_id" required>
                                                    @error('auth_id')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="password">API
                                                        Password</label>
                                                    <input type="text" id="password"
                                                           class="form-control @error('password') is-invalid @enderror"
                                                           value="{{ $server['password'] }}" name="password" required>
                                                    @error('password')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif


                                        @if($server['settings'] == 'EnableX')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="application_id">APP
                                                        ID</label>
                                                    <input type="text" id="application_id"
                                                           class="form-control @error('application_id') is-invalid @enderror"
                                                           value="{{ $server['application_id'] }}" name="application_id"
                                                           required>
                                                    @error('application_id')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_key">APP Key</label>
                                                    <input type="text" id="api_key"
                                                           class="form-control @error('api_key') is-invalid @enderror"
                                                           value="{{ $server['api_key'] }}" name="api_key" required>
                                                    @error('api_key')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Campaign ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c2">Template ID</label>
                                                    <input type="text" id="c2"
                                                           class="form-control @error('c2') is-invalid @enderror"
                                                           value="{{ $server['c2'] }}" name="c2" required>
                                                    @error('c2')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'IconGlobalCoUK' || $server['settings'] == SendingServer::TYPE_TECHCORE)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="username">Username</label>
                                                    <input type="text" id="username"
                                                           class="form-control @error('username') is-invalid @enderror"
                                                           value="{{ $server['username'] }}" name="username" required>
                                                    @error('username')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="application_id">API
                                                        ID</label>
                                                    <input type="text" id="application_id"
                                                           class="form-control @error('application_id') is-invalid @enderror"
                                                           value="{{ $server['application_id'] }}" name="application_id"
                                                           required>
                                                    @error('application_id')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="access_token">Access
                                                        Token</label>
                                                    <input type="text" id="access_token"
                                                           class="form-control @error('access_token') is-invalid @enderror"
                                                           value="{{ $server['access_token'] }}" name="access_token"
                                                           required>
                                                    @error('access_token')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'Twilio' || $server['settings'] == 'TwilioCopilot' || $server['settings'] == 'Plivo' || $server['settings'] == 'PlivoPowerpack' || $server['settings'] == 'KarixIO' || $server['settings'] == 'TxTria' )
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="auth_token">Auth
                                                        Token</label>
                                                    <input type="text" id="auth_token"
                                                           class="form-control @error('auth_token') is-invalid @enderror"
                                                           value="{{ $server['auth_token'] }}" name="auth_token"
                                                           required>
                                                    @error('auth_token')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'ClickatellTouch' || $server['settings'] == 'ClickatellCentral' || $server['settings'] == 'TextLocal' || $server['settings'] == 'Vonage' || $server['settings'] == 'MessageBird' || $server['settings'] == 'Tyntec' || $server['settings'] == 'Telnyx' || $server['settings'] == 'TelnyxNumberPool' || $server['settings'] == 'Infobip' || $server['settings'] == 'BroadcasterMobile' || $server['settings'] == 'BeemAfrica' || $server['settings'] == 'ElitBuzzBD' || $server['settings'] == 'HablameV2' || $server['settings'] == 'ZamtelCoZm' || $server['settings'] == 'CellCast' || $server['settings'] == 'AfricasTalking' || $server['settings'] == 'SpoofSend' || $server['settings'] == 'AlhajSms' || $server['settings'] == 'SendroidUltimate' || $server['settings'] == 'RealSMS' || $server['settings'] == 'LTR' || $server['settings'] == 'SmartVision' || $server['settings'] == 'ZipComIo' || $server['settings'] == 'FloatSMS' || $server['settings'] == 'EasySmsXyz' || $server['settings'] == 'Sozuri' || $server['settings'] == 'ExpertTexting' || $server['settings'] == 'Gateway360' || $server['settings'] == 'GlobalSMSCN' || $server['settings'] == 'AjuraTech' || $server['settings'] == 'MOOVCI' || $server['settings'] == 'CamooCM' || $server['settings'] == 'BurstSMS' || $server['settings'] == 'FHMCloud' || $server['settings'] == 'SMSTO' || $server['settings'] == 'TextBelt' || $server['settings'] == 'IntelTele' || $server['settings'] == 'GatewaySa' || $server['settings'] == 'OnBuka' || $server['settings'] == 'TeleSign' || $server['settings'] == 'MidasAppBr' || $server['settings'] ==  SendingServer::TYPE_DIGINTRA || $server['settings'] ==  SendingServer::TYPE_GUPSHUPIO || $server['settings'] ==  SendingServer::TYPE_GUPSHUPIOTEMPLATE || $server['settings'] ==  SendingServer::TYPE_SEMAPHORE || $server['settings'] ==  SendingServer::TYPE_IMARTGROUP || $server['settings'] ==  SendingServer::TYPE_TEXT_CALIBUR || $server['settings'] ==  SendingServer::TYPE_ARKESEL || $server['settings'] ==  SendingServer::TYPE_FAST2SMS || $server['settings'] ==  SendingServer::TYPE_OZONEDESK || $server['settings'] ==  SendingServer::TYPE_BULKREPLY || $server['settings'] ==  SendingServer::TYPE_BULkSMS4BTC || $server['settings'] ==  SendingServer::TYPE_NIMBUZ || $server['settings'] ==  SendingServer::TYPE_MOBITECH || $server['settings'] ==  SendingServer::TYPE_HOSTPINNACLE || $server['settings'] ==  SendingServer::TYPE_LANKABELL || $server['settings'] ==  SendingServer::TYPE_FONOIP || $server['settings'] ==  SendingServer::TYPE_EBULKSMS || $server['settings'] ==  SendingServer::TYPE_CLICKSEND || $server['settings'] ==  SendingServer::TYPE_SMSMODE || $server['settings'] ==  SendingServer::TYPE_GLINTSMS || $server['settings'] ==  SendingServer::TYPE_SMSHTTPREVE || $server['settings'] ==  SendingServer::TYPE_REALSMS || $server['settings'] ==  SendingServer::TYPE_TERMII || $server['settings'] ==  SendingServer::TYPE_VOXIMPLANT || $server['settings'] ==  SendingServer::TYPE_PMCSMS || $server['settings'] ==  SendingServer::TYPE_INTERAKT || $server['settings'] ==  SendingServer::TYPE_JUICYSIMS || $server['settings'] ==  SendingServer::TYPE_BSGWORLD || $server['settings'] ==  SendingServer::TYPE_SNAPISMS || $server['settings'] ==  SendingServer::TYPE_MOCEANAPI || $server['settings'] == SendingServer::TYPE_MESSAGGIO || $server['settings'] == SendingServer::TYPE_AUDIENCEONE)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_key">API Key</label>
                                                    <input type="text" id="api_key"
                                                           class="form-control @error('api_key') is-invalid @enderror"
                                                           value="{{ $server['api_key'] }}" name="api_key" required>
                                                    @error('api_key')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'Bulksmsplans')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="auth_id">API ID</label>
                                                    <input type="text" id="auth_id"
                                                           class="form-control @error('auth_id') is-invalid @enderror"
                                                           value="{{ $server['auth_id'] }}" name="auth_id" required>
                                                    @error('auth_id')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="password">API
                                                        Password</label>
                                                    <input type="text" id="password"
                                                           class="form-control @error('password') is-invalid @enderror"
                                                           value="{{ $server['password'] }}" name="password" required>
                                                    @error('password')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="route">SMS Type</label>
                                                    <select class="form-select" id="route" name="route">
                                                        <option value="Promotional" {{ $server['route'] == 'Promotional' ? 'selected': null }}>
                                                            Promotional
                                                        </option>
                                                        <option value="Transactional" {{ $server['route'] == 'Transactional' ? 'selected': null }}>
                                                            Transactional
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'Vonage' || $server['settings'] == 'Bandwidth' || $server['settings'] == 'BeemAfrica' || $server['settings'] == 'Skyetel' || $server['settings'] == 'ExpertTexting' || $server['settings'] == 'GlobalSMSCN' || $server['settings'] == 'LifetimeSMS' || $server['settings'] == 'CamooCM' || $server['settings'] == 'BurstSMS' || $server['settings'] == 'OnBuka')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_secret">API
                                                        Secret</label>
                                                    <input type="text" id="api_secret"
                                                           class="form-control @error('api_secret') is-invalid @enderror"
                                                           value="{{ $server['api_secret'] }}" name="api_secret"
                                                           required>
                                                    @error('api_secret')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'msg91' || $server['settings'] == 'CyberGateLK' || $server['settings'] == SendingServer::TYPE_ROUNDSMS || $server['settings'] == SendingServer::TYPE_ALLMYSMS || $server['settings'] == SendingServer::TYPE_MSG91)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="auth_key">Auth Key</label>
                                                    <input type="text" id="auth_key"
                                                           class="form-control @error('auth_key') is-invalid @enderror"
                                                           value="{{ $server['auth_key'] }}" name="auth_key" required>
                                                    @error('auth_key')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_ROUNDSMS)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="route">Route</label>
                                                    <select class="form-select" id="route" name="route">
                                                        <option value="1" {{ $server['route'] == '1' ? 'selected': null }}>
                                                            Promotional
                                                        </option>
                                                        <option value="2" {{ $server['route'] == '2' ? 'selected': null }}>
                                                            Transactional
                                                        </option>
                                                        <option value="3" {{ $server['route'] == '3' ? 'selected': null }}>
                                                            Promo Sender ID
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'msg91')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="route">Route</label>
                                                    <select class="form-select" id="route" name="route">
                                                        <option value="1" {{ $server['route'] == '1' ? 'selected': null }}>
                                                            Promotional
                                                        </option>
                                                        <option value="4" {{ $server['route'] == '4' ? 'selected': null }}>
                                                            Transactional
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="country_code">Country
                                                        Code</label>
                                                    <input type="text" id="country_code"
                                                           class="form-control @error('country_code') is-invalid @enderror"
                                                           value="{{ $server['country_code'] }}" name="country_code"
                                                           required>
                                                    @error('country_code')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'PitchWink')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Credential</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_ORANGE)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Authorization
                                                        Header</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c2">Dev Phone Number</label>
                                                    <input type="text" id="c2"
                                                           class="form-control @error('c2') is-invalid @enderror"
                                                           value="{{ $server['c2'] }}" name="c2" required>
                                                    @error('c2')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_MSG91)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Flow ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] ==  SendingServer::TYPE_GUPSHUPIO || $server['settings'] ==  SendingServer::TYPE_GUPSHUPIOTEMPLATE)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Gupshup App Name</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif


                                        @if($server['settings'] == 'MidasAppBr')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Footer Message</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c2">Template 1</label>
                                                    <input type="text" id="c2"
                                                           class="form-control @error('c2') is-invalid @enderror"
                                                           value="{{ $server['c2'] }}" name="c2">
                                                    @error('c2')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c3">Template 2</label>
                                                    <input type="text" id="c3"
                                                           class="form-control @error('c3') is-invalid @enderror"
                                                           value="{{ $server['c3'] }}" name="c3">
                                                    @error('c3')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c4">Button 1</label>
                                                    <input type="text" id="c4"
                                                           class="form-control @error('c4') is-invalid @enderror"
                                                           value="{{ $server['c4'] }}" name="c4">
                                                    @error('c4')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c5">Button 2</label>
                                                    <input type="text" id="c5"
                                                           class="form-control @error('c5') is-invalid @enderror"
                                                           value="{{ $server['c5'] }}" name="c5">
                                                    @error('c5')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c6">Button 3</label>
                                                    <input type="text" id="c6"
                                                           class="form-control @error('c6') is-invalid @enderror"
                                                           value="{{ $server['c6'] }}" name="c6">
                                                    @error('c6')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c7">URL</label>
                                                    <input type="text" id="c7"
                                                           class="form-control @error('c7') is-invalid @enderror"
                                                           value="{{ $server['c7'] }}" name="c7">
                                                    @error('c7')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_BULkSMS4BTC)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Variable</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            {{--                                            <div class="col-12">--}}
                                            {{--                                                <div class="mb-1">--}}
                                            {{--                                                    <label class="form-label" for="c2">Variable Two</label>--}}
                                            {{--                                                    <input type="text" id="c2"--}}
                                            {{--                                                           class="form-control @error('c2') is-invalid @enderror"--}}
                                            {{--                                                           value="{{ $server['c2'] }}" name="c2">--}}
                                            {{--                                                    @error('c2')--}}
                                            {{--                                                    <div class="invalid-feedback">--}}
                                            {{--                                                        {{ $message }}--}}
                                            {{--                                                    </div>--}}
                                            {{--                                                    @enderror--}}
                                            {{--                                                </div>--}}
                                            {{--                                            </div>--}}

                                            {{--                                            <div class="col-12">--}}
                                            {{--                                                <div class="mb-1">--}}
                                            {{--                                                    <label class="form-label" for="c3">Variable Three</label>--}}
                                            {{--                                                    <input type="text" id="c3"--}}
                                            {{--                                                           class="form-control @error('c3') is-invalid @enderror"--}}
                                            {{--                                                           value="{{ $server['c3'] }}" name="c3">--}}
                                            {{--                                                    @error('c3')--}}
                                            {{--                                                    <div class="invalid-feedback">--}}
                                            {{--                                                        {{ $message }}--}}
                                            {{--                                                    </div>--}}
                                            {{--                                                    @enderror--}}
                                            {{--                                                </div>--}}
                                            {{--                                            </div>--}}


                                            {{--                                            <div class="col-12">--}}
                                            {{--                                                <div class="mb-1">--}}
                                            {{--                                                    <label class="form-label" for="c4">Variable Four</label>--}}
                                            {{--                                                    <input type="text" id="c4"--}}
                                            {{--                                                           class="form-control @error('c4') is-invalid @enderror"--}}
                                            {{--                                                           value="{{ $server['c4'] }}" name="c4">--}}
                                            {{--                                                    @error('c4')--}}
                                            {{--                                                    <div class="invalid-feedback">--}}
                                            {{--                                                        {{ $message }}--}}
                                            {{--                                                    </div>--}}
                                            {{--                                                    @enderror--}}
                                            {{--                                                </div>--}}
                                            {{--                                            </div>--}}


                                            {{--                                            <div class="col-12">--}}
                                            {{--                                                <div class="mb-1">--}}
                                            {{--                                                    <label class="form-label" for="c5">Variable Five</label>--}}
                                            {{--                                                    <input type="text" id="c5"--}}
                                            {{--                                                           class="form-control @error('c5') is-invalid @enderror"--}}
                                            {{--                                                           value="{{ $server['c5'] }}" name="c5">--}}
                                            {{--                                                    @error('c5')--}}
                                            {{--                                                    <div class="invalid-feedback">--}}
                                            {{--                                                        {{ $message }}--}}
                                            {{--                                                    </div>--}}
                                            {{--                                                    @enderror--}}
                                            {{--                                                </div>--}}
                                            {{--                                            </div>--}}


                                            {{--                                            <div class="col-12">--}}
                                            {{--                                                <div class="mb-1">--}}
                                            {{--                                                    <label class="form-label" for="c6">Variable Six</label>--}}
                                            {{--                                                    <input type="text" id="c6"--}}
                                            {{--                                                           class="form-control @error('c6') is-invalid @enderror"--}}
                                            {{--                                                           value="{{ $server['c6'] }}" name="c6">--}}
                                            {{--                                                    @error('c6')--}}
                                            {{--                                                    <div class="invalid-feedback">--}}
                                            {{--                                                        {{ $message }}--}}
                                            {{--                                                    </div>--}}
                                            {{--                                                    @enderror--}}
                                            {{--                                                </div>--}}
                                            {{--                                            </div>--}}


                                            {{--                                            <div class="col-12">--}}
                                            {{--                                                <div class="mb-1">--}}
                                            {{--                                                    <label class="form-label" for="c7">Variable Seven</label>--}}
                                            {{--                                                    <input type="text" id="c7"--}}
                                            {{--                                                           class="form-control @error('c7') is-invalid @enderror"--}}
                                            {{--                                                           value="{{ $server['c7'] }}" name="c7">--}}
                                            {{--                                                    @error('c7')--}}
                                            {{--                                                    <div class="invalid-feedback">--}}
                                            {{--                                                        {{ $message }}--}}
                                            {{--                                                    </div>--}}
                                            {{--                                                    @enderror--}}
                                            {{--                                                </div>--}}
                                            {{--                                            </div>--}}

                                        @endif

                                        @if($server['settings'] == 'LuxSMS')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">API ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">API Password</label>
                                                    <input type="text" id="password"
                                                           class="form-control @error('password') is-invalid @enderror"
                                                           value="{{ $server['password'] }}" name="password" required>
                                                    @error('password')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'TeleSign')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Customer ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'TxTria')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">System ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif


                                        @if($server['settings'] == 'MOOVCI')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Login</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'Solucoesdigitais')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Centro custo
                                                        interno</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'Wavy')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="auth_token">Authentication
                                                        Token</label>
                                                    <input type="text" id="auth_token"
                                                           class="form-control @error('auth_token') is-invalid @enderror"
                                                           value="{{ $server['auth_token'] }}" name="auth_token"
                                                           required>
                                                    @error('auth_token')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="username">Username</label>
                                                    <input type="text" id="username"
                                                           class="form-control @error('username') is-invalid @enderror"
                                                           value="{{ $server['username'] }}" name="username" required>
                                                    @error('username')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif


                                        @if($server['settings'] == 'Web2SMS237')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_key">User ID</label>
                                                    <input type="text" id="api_key"
                                                           class="form-control @error('api_key') is-invalid @enderror"
                                                           value="{{ $server['api_key'] }}" name="api_key" required>
                                                    @error('api_key')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_secret">User
                                                        Secret</label>
                                                    <input type="text" id="api_secret"
                                                           class="form-control @error('api_secret') is-invalid @enderror"
                                                           value="{{ $server['api_secret'] }}" name="api_secret"
                                                           required>
                                                    @error('api_secret')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif


                                        @if($server['settings'] == 'SmsGatewayMe' || $server['settings'] == 'WhatsAppChatApi' || $server['settings'] == 'SignalWire' || $server['settings'] == 'Bandwidth' || $server['settings'] == 'BroadcasterMobile' || $server['settings'] == 'GreenWebBD' || $server['settings'] == 'HablameV2' || $server['settings'] == 'CaihCom'  || $server['settings'] == 'SpoofSend' || $server['settings'] == 'AlhajSms' || $server['settings'] == 'SendroidUltimate' || $server['settings'] == 'Sinch' || $server['settings'] == 'PitchWink' || $server['settings'] == 'MaisSMS' || $server['settings'] == 'BulkSMSNigeria' || $server['settings'] == 'SMSCloudCI' || $server['settings'] == 'LifetimeSMS' || $server['settings'] == 'PARATUS' || $server['settings'] == 'LeTexto' || $server['settings'] == 'Whatsender' || $server['settings'] == 'Gatewayapi' || $server['settings'] == 'SemySMS' || $server['settings'] == 'Xmsway' || $server['settings'] == 'Inteliquent' || $server['settings'] == 'BulkGate' || $server['settings'] == 'ClearComMX' || $server['settings'] == 'CyberGateLK' || $server['settings'] == 'SMSCrab' || $server['settings'] == SendingServer::TYPE_MAILJET || $server['settings'] == SendingServer::TYPE_SMSFRL || $server['settings'] == SendingServer::TYPE_GOSMSFUN || $server['settings'] == SendingServer::TYPE_D7NETWORKS || $server['settings'] == SendingServer::TYPE_SAVEWEBHOSTNET || $server['settings'] == SendingServer::TYPE_TELEAPI || $server['settings'] == SendingServer::TYPE_PICKYASSIST || $server['settings'] == SendingServer::TYPE_8x8 || $server['settings'] == SendingServer::TYPE_WAZONE || $server['settings'] == SendingServer::TYPE_MMSCONSOLE || $server['settings'] == SendingServer::TYPE_BMSGLOBAL || $server['settings'] == SendingServer::TYPE_DATAGIFTING || $server['settings'] == SendingServer::TYPE_SMSAPI || $server['settings'] == SendingServer::TYPE_SMSAPIONLINE || $server['settings'] == SendingServer::TYPE_WA2SALES || $server['settings'] == SendingServer::TYPE_ULTIMATESMS || $server['settings'] == SendingServer::TYPE_BLACKSMS || $server['settings'] == SendingServer::TYPE_SMARTSMSSOLUTIONS || $server['settings'] == SendingServer::TYPE_SIMPLETEXTING || $server['settings'] == SendingServer::TYPE_UIPSMS)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_token">API Token</label>
                                                    <input type="text" id="api_token"
                                                           class="form-control @error('api_token') is-invalid @enderror"
                                                           value="{{ $server['api_token'] }}" name="api_token" required>
                                                    @error('api_token')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif



                                        @if($server['settings'] == SendingServer::TYPE_WA2SALES)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Footer Message</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1">
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c2">CallButton Text</label>
                                                    <input type="text" id="c2"
                                                           class="form-control @error('c2') is-invalid @enderror"
                                                           value="{{ $server['c2'] }}" name="c2">
                                                    @error('c2')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c3">CallButton Value</label>
                                                    <input type="text" id="c3"
                                                           class="form-control @error('c3') is-invalid @enderror"
                                                           value="{{ $server['c3'] }}" name="c3">
                                                    @error('c3')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c4">URLButton Text</label>
                                                    <input type="text" id="c4"
                                                           class="form-control @error('c4') is-invalid @enderror"
                                                           value="{{ $server['c4'] }}" name="c4">
                                                    @error('c4')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c5">URLButton Value</label>
                                                    <input type="text" id="c5"
                                                           class="form-control @error('c5') is-invalid @enderror"
                                                           value="{{ $server['c5'] }}" name="c5">
                                                    @error('c5')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c6">QuickReplyButton Text</label>
                                                    <input type="text" id="c6"
                                                           class="form-control @error('c6') is-invalid @enderror"
                                                           value="{{ $server['c6'] }}" name="c6">
                                                    @error('c6')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c7">QuickReplyButton Value</label>
                                                    <input type="text" id="c7"
                                                           class="form-control @error('c7') is-invalid @enderror"
                                                           value="{{ $server['c7'] }}" name="c7">
                                                    @error('c7')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'CMCOM')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_token">Product
                                                        Token</label>
                                                    <input type="text" id="api_token"
                                                           class="form-control @error('api_token') is-invalid @enderror"
                                                           value="{{ $server['api_token'] }}" name="api_token" required>
                                                    @error('api_token')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif


                                        @if($server['settings'] == 'SignalWire')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="project_id">Project
                                                        ID</label>
                                                    <input type="text" id="project_id"
                                                           class="form-control @error('project_id') is-invalid @enderror"
                                                           value="{{ $server['project_id'] }}" name="project_id"
                                                           required>
                                                    @error('project_id')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif
                                        @if($server['settings'] == 'Sozuri')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="project_id">Project
                                                        Name</label>
                                                    <input type="text" id="project_id"
                                                           class="form-control @error('project_id') is-invalid @enderror"
                                                           value="{{ $server['project_id'] }}" name="project_id"
                                                           required>
                                                    @error('project_id')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'SmsGatewayMe' || $server['settings'] == 'Whatsender' || $server['settings'] == 'SemySMS' )

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="device_id">Device ID</label>
                                                    <input type="text" id="device_id"
                                                           class="form-control @error('device_id') is-invalid @enderror"
                                                           value="{{ $server['device_id'] }}" name="device_id" required>
                                                    @error('device_id')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'AmazonSNS' || $server['settings'] == 'FlowRoute' || $server['settings'] == SendingServer::TYPE_ALIBABACLOUDSMS)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="access_key">Access
                                                        Key</label>
                                                    <input type="text" id="access_key"
                                                           class="form-control @error('access_key') is-invalid @enderror"
                                                           value="{{ $server['access_key'] }}" name="access_key"
                                                           required>
                                                    @error('access_key')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_WHATSAPP || $server['settings'] == SendingServer::TYPE_WHATSAPPBYTEMPLATE)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="access_token">Access
                                                        Token</label>
                                                    <input type="text" id="access_token"
                                                           class="form-control @error('access_token') is-invalid @enderror"
                                                           value="{{ $server['access_token'] }}" name="access_token"
                                                           required>
                                                    @error('access_token')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_YUPCHAT)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">SEU ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1"
                                                           required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_token">SEU Token</label>
                                                    <input type="text" id="api_token"
                                                           class="form-control @error('api_token') is-invalid @enderror"
                                                           value="{{ $server['api_token'] }}" name="api_token" required>
                                                    @error('api_token')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif


                                        @if($server['settings'] == 'FlowRoute' || $server['settings'] == 'AjuraTech' || $server['settings'] ==  SendingServer::TYPE_SMSHTTPREVE)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_secret">Secret
                                                        Key</label>
                                                    <input type="text" id="api_secret"
                                                           class="form-control @error('api_secret') is-invalid @enderror"
                                                           value="{{ $server['api_secret'] }}" name="api_secret"
                                                           required>
                                                    @error('api_secret')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif


                                        @if($server['settings'] == 'AmazonSNS' || $server['settings'] == SendingServer::TYPE_ALIBABACLOUDSMS)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="secret_access">Secret
                                                        Access</label>
                                                    <input type="text" id="secret_access"
                                                           class="form-control @error('secret_access') is-invalid @enderror"
                                                           value="{{ $server['secret_access'] }}" name="secret_access"
                                                           required>
                                                    @error('secret_access')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'AmazonSNS' || $server['settings'] == SendingServer::TYPE_ALIBABACLOUDSMS)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="region">Region</label>
                                                    <input type="text" id="region"
                                                           class="form-control @error('region') is-invalid @enderror"
                                                           value="{{ $server['region'] }}" name="region" required>
                                                    @error('region')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'Bandwidth' || $server['settings'] == 'RouteeNet' || $server['settings'] == 'KeccelSMS' || $server['settings'] == 'GlobalSMSCN' || $server['settings'] == 'OnBuka' || $server['settings'] == 'BulkGate' || $server['settings'] == SendingServer::TYPE_PICKYASSIST || $server['settings'] == SendingServer::TYPE_WAVIX)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="application_id">Application
                                                        ID</label>
                                                    <input type="text" id="application_id"
                                                           class="form-control @error('application_id') is-invalid @enderror"
                                                           value="{{ $server['application_id'] }}" name="application_id"
                                                           required>
                                                    @error('application_id')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif


                                        @if($server['settings'] == 'RouteeNet')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_secret">Application
                                                        Secret</label>
                                                    <input type="text" id="api_secret"
                                                           class="form-control @error('api_secret') is-invalid @enderror"
                                                           value="{{ $server['api_secret'] }}" name="api_secret"
                                                           required>
                                                    @error('api_secret')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'BroadcasterMobile')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Country Code</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_MMSCONSOLE || $server['settings'] == SendingServer::TYPE_SMSVEND)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Email</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'MaisSMS')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Parceiro ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'CaihCom')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Channel Key</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'HablameV2')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Account</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_VOXIMPLANT || $server['settings'] == SendingServer::TYPE_LAFRICAMOBILE)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Account ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_LAFRICAMOBILE)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required"
                                                           for="password">{{ __('locale.labels.password') }}</label>
                                                    <input type="text" id="password"
                                                           class="form-control @error('password') is-invalid @enderror"
                                                           value="{{ $server['password'] }}" name="password" required>
                                                    @error('password')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'GatewaySa' || $server['settings'] == 'SendPulse' || $server['settings'] == SendingServer::TYPE_DIGINTRA)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Client ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'SendPulse')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_secret">Client
                                                        Secret</label>
                                                    <input type="text" id="api_secret"
                                                           class="form-control @error('api_secret') is-invalid @enderror"
                                                           value="{{ $server['api_secret'] }}" name="api_secret"
                                                           required>
                                                    @error('api_secret')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'AmazonSNS')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="sms_type">Message
                                                        Type</label>
                                                    <select class="form-select" id="sms_type" name="sms_type">
                                                        <option value="Promotional" {{ $server['sms_type'] == 'Promotional' ? 'selected': null }}>
                                                            Promotional
                                                        </option>
                                                        <option value="Transactional" {{ $server['sms_type'] == 'Transactional' ? 'selected': null }}>
                                                            Transactional
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>

                                        @endif


                                        @if($server['settings'] == SendingServer::TYPE_MESSAGGIO)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="sms_type">Message
                                                        Type</label>
                                                    <select class="form-select" id="sms_type" name="sms_type">
                                                        <option value="promotion" {{ $server['sms_type'] == 'promotion' ? 'selected': null }}>
                                                            Promotional
                                                        </option>
                                                        <option value="transaction" {{ $server['sms_type'] == 'transaction' ? 'selected': null }}>
                                                            Transactional
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>

                                        @endif


                                        @if($server['settings'] == 'LuxSMS')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="sms_type">Message
                                                        Type</label>
                                                    <select class="form-select" id="sms_type" name="sms_type">
                                                        <option value="P" {{ $server['sms_type'] == 'P' ? 'selected': null }}>
                                                            Promotional
                                                        </option>
                                                        <option value="T" {{ $server['sms_type'] == 'T' ? 'selected': null }}>
                                                            Transactional
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'ClickatellCentral' || $server['settings'] == 'RouteMobile' || $server['settings'] == 'SMSGlobal' || $server['settings'] == 'BulkSMS' ||  $server['settings'] == '1s2u' || $server['settings'] == 'SMPP' || $server['settings'] == 'HutchLk' || $server['settings'] == 'Teletopiasms' || $server['settings'] == 'Solutions4mobiles' || $server['settings'] == 'BulkSMSOnline' || $server['settings'] == 'EasySendSMS' || $server['settings'] == 'AfricasTalking' || $server['settings'] == 'KeccelSMS' || $server['settings'] == 'Solucoesdigitais' || $server['settings'] == 'BongaTech' || $server['settings'] == 'Ejoin' || $server['settings'] == 'SendSMSGate' || $server['settings'] == 'SMSCarrierEU' || $server['settings'] == 'VisionUp' || $server['settings'] == 'SMSVas' || $server['settings'] == 'CCSSMS' || $server['settings'] == SendingServer::TYPE_SAFARICOM || $server['settings'] == SendingServer::TYPE_FACILITAMOVEL || $server['settings'] == SendingServer::TYPE_SMSDELIVERER || $server['settings'] == SendingServer::TYPE_YOSMS || $server['settings'] == SendingServer::TYPE_ESOLUTIONS || $server['settings'] == SendingServer::TYPE_ESTORESMS || $server['settings'] == SendingServer::TYPE_GOIP || $server['settings'] == SendingServer::TYPE_ADVANCEMSGSYS || $server['settings'] == SendingServer::TYPE_HOSTPINNACLE || $server['settings'] == SendingServer::TYPE_ZORRA || $server['settings'] == SendingServer::TYPE_HOTMOBILE || $server['settings'] == SendingServer::TYPE_QOOLIZE || $server['settings'] == SendingServer::TYPE_EBULKSMS || $server['settings'] == SendingServer::TYPE_CLICKSEND || $server['settings'] == SendingServer::TYPE_GBESTSMS || $server['settings'] == SendingServer::TYPE_SILVERSTREET || $server['settings'] == SendingServer::TYPE_BULKSMSPROVIDERNG || $server['settings'] == SendingServer::TYPE_OZONESMS || $server['settings'] == SendingServer::TYPE_NIGERIABULKSMS || $server['settings'] == SendingServer::TYPE_AIRTELINDIA || $server['settings'] == SendingServer::TYPE_REALSMS || $server['settings'] == SendingServer::TYPE_CLIQSMS || $server['settings'] == SendingServer::TYPE_SMSVEND || $server['settings'] == SendingServer::TYPE_SMSAFRICANG || $server['settings'] == SendingServer::TYPE_MOBILESMSNG || $server['settings'] == SendingServer::TYPE_SMSEXPERIENCE || $server['settings'] == SendingServer::TYPE_ETROSS || $server['settings'] == SendingServer::TYPE_DINSTAR || $server['settings'] == SendingServer::TYPE_DIAFAAN)

                                            @if($server['settings'] != 'KeccelSMS' && $server['settings'] != SendingServer::TYPE_SMSVEND)
                                                <div class="col-12">
                                                    <div class="mb-1">
                                                        <label class="form-label required" for="username">User
                                                            name</label>
                                                        <input type="text" id="username"
                                                               class="form-control @error('username') is-invalid @enderror"
                                                               value="{{ $server['username'] }}" name="username"
                                                               required>
                                                        @error('username')
                                                        <div class="invalid-feedback">
                                                            {{ $message }}
                                                        </div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            @endif

                                            @if($server['settings'] != 'AfricasTalking' && $server['settings'] !=  SendingServer::TYPE_EBULKSMS && $server['settings'] !=  SendingServer::TYPE_CLICKSEND && $server['settings'] !=  SendingServer::TYPE_REALSMS)
                                                <div class="col-12">
                                                    <div class="mb-1">
                                                        <label class="form-label required"
                                                               for="password">Password</label>
                                                        <input type="text" id="password"
                                                               class="form-control @error('password') is-invalid @enderror"
                                                               value="{{ $server['password'] }}" name="password"
                                                               required>
                                                        @error('password')
                                                        <div class="invalid-feedback">
                                                            {{ $message }}
                                                        </div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            @endif
                                        @endif


                                        @if($server['settings'] == SendingServer::TYPE_VOICEANDTEXT)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="username">User name</label>
                                                    <input type="text" id="username"
                                                           class="form-control @error('username') is-invalid @enderror"
                                                           value="{{ $server['username'] }}" name="username"
                                                           required>
                                                    @error('username')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required"
                                                           for="password">Password/API Admin</label>
                                                    <input type="text" id="password"
                                                           class="form-control @error('password') is-invalid @enderror"
                                                           value="{{ $server['password'] }}" name="password"
                                                           required>
                                                    @error('password')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label"
                                                           for="api_token">Admin Token</label>
                                                    <input type="text" id="api_token"
                                                           class="form-control @error('api_token') is-invalid @enderror"
                                                           value="{{ $server['api_token'] }}" name="api_token">
                                                    @error('api_token')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_OZONESMS)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="sms_type">Channel</label>
                                                    <select class="form-select" id="sms_type" name="sms_type">
                                                        <option value="promotional" {{ $server['sms_type'] == 'promotional' ? 'selected': null }}>
                                                            Promotional
                                                        </option>
                                                        <option value="transactional" {{ $server['sms_type'] == 'transactional' ? 'selected': null }}>
                                                            Transactional
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="route">Route</label>
                                                    <input type="text" id="route"
                                                           class="form-control @error('route') is-invalid @enderror"
                                                           value="{{ $server['route'] }}" name="route"
                                                           required>
                                                    @error('route')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_IMARTGROUP)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Campaign</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1"
                                                           required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="route">Route</label>
                                                    <input type="text" id="route"
                                                           class="form-control @error('route') is-invalid @enderror"
                                                           value="{{ $server['route'] }}" name="route"
                                                           required>
                                                    @error('route')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_PMCSMS || $server['settings'] == SendingServer::TYPE_SMARTSMSSOLUTIONS)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="route">Route</label>
                                                    <input type="text" id="route"
                                                           class="form-control @error('route') is-invalid @enderror"
                                                           value="{{ $server['route'] }}" name="route"
                                                           required>
                                                    @error('route')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] ==  SendingServer::TYPE_TERMII)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Channel</label>
                                                    <select class="form-select" id="c1" name="c1">
                                                        <option value="dnd" {{ $server['c1'] == 'dnd' ? 'selected': null }}>
                                                            dnd
                                                        </option>
                                                        <option value="generic" {{ $server['c1'] == 'generic' ? 'selected': null }}>
                                                            generic
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                        @endif


                                        @if($server['settings'] == SendingServer::TYPE_CLIQSMS)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">DND</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif


                                        @if($server['settings'] == 'BulkSMSNigeria')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c1">Gateway</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1">
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c2">Append Sender</label>
                                                    <input type="text" id="c2"
                                                           class="form-control @error('c2') is-invalid @enderror"
                                                           value="{{ $server['c2'] }}" name="c2">
                                                    @error('c2')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif


                                        @if($server['settings'] == 'ExpertTexting' || $server['settings'] == 'PARATUS' || $server['settings'] == 'IntelTele')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required"
                                                           for="username">{{ __('locale.labels.username') }}</label>
                                                    <input type="text" id="username"
                                                           class="form-control @error('username') is-invalid @enderror"
                                                           value="{{ $server['username'] }}" name="username" required>
                                                    @error('username')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'Callr')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="username">API Login</label>
                                                    <input type="text" id="username"
                                                           class="form-control @error('username') is-invalid @enderror"
                                                           value="{{ $server['username'] }}" name="username" required>
                                                    @error('username')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="password">API
                                                        Password</label>
                                                    <input type="text" id="password"
                                                           class="form-control @error('password') is-invalid @enderror"
                                                           value="{{ $server['password'] }}" name="password" required>
                                                    @error('password')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'CheapGlobalSMS')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="username">Sub
                                                        Account</label>
                                                    <input type="text" id="username"
                                                           class="form-control @error('username') is-invalid @enderror"
                                                           value="{{ $server['username'] }}" name="username" required>
                                                    @error('username')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="password">Sub Account
                                                        Password</label>
                                                    <input type="text" id="password"
                                                           class="form-control @error('password') is-invalid @enderror"
                                                           value="{{ $server['password'] }}" name="password" required>
                                                    @error('password')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif


                                        @if($server['settings'] == 'JohnsonConnect')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_key">App Key</label>
                                                    <input type="text" id="api_key"
                                                           class="form-control @error('api_key') is-invalid @enderror"
                                                           value="{{ $server['api_key'] }}" name="api_key" required>
                                                    @error('api_key')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_secret">Secret
                                                        Key</label>
                                                    <input type="text" id="api_secret"
                                                           class="form-control @error('api_secret') is-invalid @enderror"
                                                           value="{{ $server['api_secret'] }}" name="api_secret"
                                                           required>
                                                    @error('api_secret')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'SpewHub')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">License Key</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_AIRTELINDIA)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Call Flow ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Customer ID</label>
                                                    <input type="text" id="c2"
                                                           class="form-control @error('c2') is-invalid @enderror"
                                                           value="{{ $server['c2'] }}" name="c2" required>
                                                    @error('c2')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_MOCEANAPI)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_secret">API
                                                        Secret</label>
                                                    <input type="text" id="api_secret"
                                                           class="form-control @error('api_secret') is-invalid @enderror"
                                                           value="{{ $server['api_secret'] }}" name="api_secret"
                                                           required>
                                                    @error('api_secret')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif


                                        @if($server['settings'] == SendingServer::TYPE_CHALLENGESMS)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_secret">API
                                                        Secret</label>
                                                    <input type="text" id="api_secret"
                                                           class="form-control @error('api_secret') is-invalid @enderror"
                                                           value="{{ $server['api_secret'] }}" name="api_secret"
                                                           required>
                                                    @error('api_secret')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="device_id">Device ID</label>
                                                    <input type="text" id="device_id"
                                                           class="form-control @error('device_id') is-invalid @enderror"
                                                           value="{{ $server['device_id'] }}" name="device_id">
                                                    @error('device_id')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>



                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Mode</label>
                                                    <select class="form-select" id="c1" name="c1">
                                                        <option value="devices" {{ $server['c1'] == 'devices' ? 'selected': null }}>
                                                            Devices
                                                        </option>
                                                        <option value="credits" {{ $server['c1'] == 'credits' ? 'selected': null }}>
                                                            Credits
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c2">Gateway</label>
                                                    <input type="text" id="c2"
                                                           class="form-control @error('c2') is-invalid @enderror"
                                                           value="{{ $server['c2'] }}" name="c2">
                                                    @error('c2')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c3">Sim</label>
                                                    <input type="text" id="c3"
                                                           class="form-control @error('c3') is-invalid @enderror"
                                                           value="{{ $server['c3'] }}" name="c3">
                                                    @error('c3')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c4">Priority</label>
                                                    <input type="text" id="c4"
                                                           class="form-control @error('c4') is-invalid @enderror"
                                                           value="{{ $server['c4'] }}" name="c4">
                                                    @error('c4')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_SAFARICOM)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Campaign User
                                                        Name</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="project_id">Package
                                                        ID</label>
                                                    <input type="text" id="project_id"
                                                           class="form-control @error('project_id') is-invalid @enderror"
                                                           value="{{ $server['project_id'] }}" name="project_id"
                                                           required>
                                                    @error('project_id')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == 'Telnyx' || $server['settings'] == 'TelnyxNumberPool')
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Message Profile
                                                        ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c1">Message Connection ID</label>
                                                    <input type="text" id="c2"
                                                           class="form-control @error('c2') is-invalid @enderror"
                                                           value="{{ $server['c2'] }}" name="c2">
                                                    @error('c2')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_UIPAPP)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="user_token">User
                                                        Token</label>
                                                    <input type="text" id="user_token"
                                                           class="form-control @error('user_token') is-invalid @enderror"
                                                           value="{{ $server['user_token'] }}" name="user_token"
                                                           required>
                                                    @error('user_token')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'YooAPI')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Client ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c2">Instance ID</label>
                                                    <input type="text" id="c2"
                                                           class="form-control @error('c2') is-invalid @enderror"
                                                           value="{{ $server['c2'] }}" name="c2" required>
                                                    @error('c2')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_WAAPI)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Instance Key</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == 'MSMPusher')

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Private Key</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c2">Public Key</label>
                                                    <input type="text" id="c2"
                                                           class="form-control @error('c2') is-invalid @enderror"
                                                           value="{{ $server['c2'] }}" name="c2" required>
                                                    @error('c2')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif


                                        @if($server['settings'] == SendingServer::TYPE_OZONEDESK)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">User ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_NIMBUZ)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="username">User</label>
                                                    <input type="text" id="username"
                                                           class="form-control @error('username') is-invalid @enderror"
                                                           value="{{ $server['username'] }}" name="username" required>
                                                    @error('username')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_BUDGETSMS)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required"
                                                           for="username">{{ __('locale.labels.username') }}</label>
                                                    <input type="text" id="username"
                                                           class="form-control @error('username') is-invalid @enderror"
                                                           value="{{ $server['username'] }}" name="username" required>
                                                    @error('username')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">User ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c2">Handle</label>
                                                    <input type="text" id="c2"
                                                           class="form-control @error('c2') is-invalid @enderror"
                                                           value="{{ $server['c2'] }}" name="c2" required>
                                                    @error('c2')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif


                                        @if($server['settings'] == SendingServer::TYPE_SKEBBY)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="api_key">User Key</label>
                                                    <input type="text" id="api_key"
                                                           class="form-control @error('api_key') is-invalid @enderror"
                                                           value="{{ $server['api_key'] }}" name="api_key" required>
                                                    @error('api_key')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="access_token">Access
                                                        Token</label>
                                                    <input type="text" id="access_token"
                                                           class="form-control @error('access_token') is-invalid @enderror"
                                                           value="{{ $server['access_token'] }}" name="access_token"
                                                           required>
                                                    @error('access_token')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Message Type</label>
                                                    <select class="form-select" id="c1" name="c1">
                                                        <option value="GP" {{ $server['c1'] == 'GP' ? 'selected': null }}>
                                                            Classic+
                                                        </option>
                                                        <option value="TI" {{ $server['c1'] == 'TI' ? 'selected': null }}>
                                                            Classic
                                                        </option>
                                                        <option value="SI" {{ $server['c1'] == 'SI' ? 'selected': null }}>
                                                            Basic
                                                        </option>
                                                    </select>
                                                </div>
                                                @error('c1')
                                                <div class="invalid-feedback">
                                                    <p>Select Your Message Type</p>
                                                </div>
                                                @enderror
                                            </div>

                                        @endif


                                        @if($server['settings'] == SendingServer::TYPE_INFOBIP || $server['settings'] == SendingServer::TYPE_NIMBUZ || $server['settings'] == SendingServer::TYPE_OZONESMS || $server['settings'] == SendingServer::TYPE_AMAZONSNS || $server['settings'] == SendingServer::TYPE_FAST2SMS)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label"
                                                           for="c1">{{ __('locale.labels.entity_id') }}</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1">
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif



                                        @if($server['settings'] == SendingServer::TYPE_SMSURWAY)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c1">Email</label>
                                                    <input type="email" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1" required>
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="password">Password</label>
                                                    <input type="text" id="password"
                                                           class="form-control @error('password') is-invalid @enderror"
                                                           value="{{ $server['password'] }}" name="password" required>
                                                    @error('assword')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_MSG91)
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="c2">Template ID</label>
                                                    <input type="text" id="c2"
                                                           class="form-control @error('c2') is-invalid @enderror"
                                                           value="{{ $server['c2'] }}" name="c2" required>
                                                    @error('c2')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_DINSTAR)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="port">Port</label>
                                                    <input type="text" id="port"
                                                           class="form-control @error('port') is-invalid @enderror"
                                                           value="{{ $server['port'] }}" name="port">
                                                    @error('port')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_AUDIENCEONE)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c1">Phone book</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1">
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        @if($server['settings'] == SendingServer::TYPE_SMPP)

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="port">Port</label>
                                                    <input type="text" id="port"
                                                           class="form-control @error('port') is-invalid @enderror"
                                                           value="{{ $server['port'] }}" name="port" required>
                                                    @error('port')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="source_addr_ton">Source
                                                        Address Ton</label>
                                                    <input type="text" id="source_addr_ton"
                                                           class="form-control @error('source_addr_ton') is-invalid @enderror"
                                                           value="{{ $server['source_addr_ton'] }}"
                                                           name="source_addr_ton" required>
                                                    @error('source_addr_ton')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="source_addr_npi">Source
                                                        Address NPI</label>
                                                    <input type="text" id="source_addr_npi"
                                                           class="form-control @error('source_addr_npi') is-invalid @enderror"
                                                           value="{{ $server['source_addr_npi'] }}"
                                                           name="source_addr_npi" required>
                                                    @error('source_addr_npi')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>


                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="dest_addr_ton">Destination
                                                        Address Ton</label>
                                                    <input type="text" id="dest_addr_ton"
                                                           class="form-control @error('dest_addr_ton') is-invalid @enderror"
                                                           value="{{ $server['dest_addr_ton'] }}" name="dest_addr_ton"
                                                           required>
                                                    @error('dest_addr_ton')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label required" for="dest_addr_npi">Destination
                                                        Address NPI</label>
                                                    <input type="text" id="dest_addr_npi"
                                                           class="form-control @error('dest_addr_npi') is-invalid @enderror"
                                                           value="{{ $server['dest_addr_npi'] }}" name="dest_addr_npi"
                                                           required>
                                                    @error('dest_addr_npi')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label class="form-label" for="c1">Entity ID</label>
                                                    <input type="text" id="c1"
                                                           class="form-control @error('c1') is-invalid @enderror"
                                                           value="{{ $server['c1'] }}" name="c1">
                                                    @error('c1')
                                                    <div class="invalid-feedback">
                                                        {{ $message }}
                                                    </div>
                                                    @enderror
                                                </div>
                                            </div>

                                        @endif

                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{--Sending Speed and per request sms--}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"> {{__('locale.sending_servers.sending_limit')}} </h4>
                        </div>
                        <div class="card-content">
                            <div class="card-body">
                                <p>{!! __('locale.description.sending_credit') !!} </p>
                                <div class="form-body">
                                    <div class="row">

                                        <div class="col-12">
                                            <div class="mb-1">
                                                <label class="form-label required"
                                                       for="quota_value">{{__('locale.sending_servers.sending_limit')}}</label>
                                                <input type="number" id="quota_value"
                                                       class="form-control @error('quota_value') is-invalid @enderror"
                                                       value="{{ $server['quota_value'] }}" name="quota_value" required>
                                                @error('quota_value')
                                                <p><small class="text-danger">{{ $message }}</small></p>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="mb-1">
                                                <label class="form-label required"
                                                       for="quota_base">{{__('locale.sending_servers.time_base')}}</label>
                                                <input type="number" id="quota_base"
                                                       class="form-control @error('quota_base') is-invalid @enderror"
                                                       value="{{ $server['quota_base'] }}" name="quota_base" required>
                                                @error('quota_base')
                                                <p><small class="text-danger">{{ $message }}</small></p>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="mb-1">
                                                <label class="form-label required"
                                                       for="quota_unit">{{__('locale.sending_servers.time_unit')}}</label>
                                                <select class="form-select" id="quota_unit" name="quota_unit">
                                                    <option value="minute" {{ $server['quota_unit'] == 'minute' ? 'selected': null }}> {{__('locale.labels.minute')}}</option>
                                                    <option value="hour" {{ $server['quota_unit'] == 'hour' ? 'selected': null }}>  {{__('locale.labels.hour')}}</option>
                                                    <option value="day" {{ $server['quota_unit'] == 'day' ? 'selected': null }}>  {{__('locale.labels.day')}}</option>
                                                </select>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{--All Predefine features listed here--}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                {{__('locale.sending_servers.available_features')}}
                            </h4>
                        </div>
                        <div class="card-content">
                            <div class="card-body">

                                <div class="form-body">
                                    <div class="row">

                                        <div class="d-flex justify-content-start flex-wrap col-12">

                                            @if($server['type'] != 'whatsapp' && $server['type'] != 'viber' && $server['settings'] != SendingServer::TYPE_AUDIENCEONE)
                                                <div class="d-flex flex-column me-1">
                                                    <label class="form-check-label mb-50">{{__('locale.labels.plain')}}</label>
                                                    <div class="form-check form-switch form-check-primary">
                                                        <input type="hidden" value="0" name="plain">
                                                        <input type="checkbox" class="form-check-input" value="1"
                                                               name="plain"
                                                               id="plain" {{ $server['plain'] ? 'checked': null }}>
                                                        <label class="form-check-label" for="plain">
                                                            <span class='switch-icon-left'><i data-feather="check"></i> </span>
                                                            <span class='switch-icon-right'><i
                                                                        data-feather="x"></i> </span>
                                                        </label>
                                                    </div>
                                                </div>
                                            @endif


                                            <div class="d-flex flex-column me-1">
                                                <label class="form-check-label mb-50">{{__('locale.labels.schedule')}}</label>
                                                <div class="form-check form-switch form-check-primary">
                                                    <input type="hidden" value="0" name="schedule">
                                                    <input type="checkbox" class="form-check-input" value="1"
                                                           id="schedule"
                                                           name="schedule" {{ $server['schedule'] ? 'checked': null }}>
                                                    <label class="form-check-label" for="schedule">
                                                        <span class='switch-icon-left'><i
                                                                    data-feather="check"></i> </span>
                                                        <span class='switch-icon-right'><i data-feather="x"></i> </span>
                                                    </label>

                                                </div>
                                            </div>


                                            @if($server['settings'] == 'Twilio' || $server['settings'] == 'Plivo' || $server['settings'] == 'Vonage' || $server['settings'] == 'Infobip' || $server['settings'] == 'MessageBird' || $server['settings'] == SendingServer::TYPE_BULKSMSPROVIDERNG || $server['settings'] == 'Telnyx' || $server['settings'] == SendingServer::TYPE_AIRTELINDIA || $server['settings'] == SendingServer::TYPE_AUDIENCEONE)

                                                <div class="d-flex flex-column me-1">
                                                    <label class="form-check-label mb-50">{{__('locale.labels.voice')}}</label>
                                                    <div class="form-check form-switch form-check-primary">
                                                        <input type="hidden" value="0" name="voice">
                                                        <input type="checkbox" class="form-check-input" value="1"
                                                               id="voice"
                                                               name="voice" {{ $server['voice'] ? 'checked': null }}>
                                                        <label class="form-check-label" for="voice">
                                                            <span class='switch-icon-left'><i data-feather="check"></i> </span>
                                                            <span class='switch-icon-right'><i
                                                                        data-feather="x"></i> </span>
                                                        </label>
                                                    </div>
                                                </div>

                                            @endif

                                            @if($server['settings'] == 'Twilio' || $server['settings'] == 'TextLocal' || $server['settings'] == 'Plivo' || $server['settings'] == 'PlivoPowerpack' || $server['settings'] == 'SMSGlobal' || $server['settings'] == 'MessageBird' || $server['settings'] == 'WhatsAppChatApi' || $server['settings'] == 'SignalWire' || $server['settings'] == 'Telnyx' || $server['settings'] == 'TelnyxNumberPool' || $server['settings'] == 'Bandwidth' || $server['settings'] == 'Skyetel' || $server['settings'] == 'TxTria' || $server['settings'] == 'Whatsender' || $server['settings'] == SendingServer::TYPE_TELEAPI || $server['settings'] == SendingServer::TYPE_MMSCONSOLE || $server['settings'] == SendingServer::TYPE_BMSGLOBAL || $server['settings'] == SendingServer::TYPE_MESSAGGIO || $server['settings'] == SendingServer::TYPE_WAVIX)

                                                <div class="d-flex flex-column me-1">
                                                    <label class="form-check-label mb-50">{{__('locale.labels.mms')}}</label>
                                                    <div class="form-check form-switch form-check-primary">
                                                        <input type="hidden" value="0" name="mms">
                                                        <input type="checkbox" class="form-check-input" value="1"
                                                               name="mms"
                                                               id="mms" {{ $server['mms'] ? 'checked': null }}>
                                                        <label class="form-check-label" for="mms">
                                                            <span class='switch-icon-left'><i data-feather="check"></i> </span>
                                                            <span class='switch-icon-right'><i
                                                                        data-feather="x"></i> </span>
                                                        </label>
                                                    </div>
                                                </div>

                                            @endif

                                            @if($server['settings'] == 'Twilio' || $server['settings'] == 'TwilioCopilot' || $server['settings'] == 'ClickatellTouch' || $server['settings'] == 'ClickatellCentral' || $server['settings'] == 'TextLocal' || $server['settings'] == 'Plivo' || $server['settings'] == 'PlivoPowerpack' || $server['settings'] == 'BulkSMS' || $server['settings'] == 'SMSGlobal' || $server['settings'] == 'Vonage' || $server['settings'] == 'MessageBird' || $server['settings'] == 'WhatsAppChatApi' || $server['settings'] == 'SignalWire' || $server['settings'] == 'Telnyx' || $server['settings'] == 'TelnyxNumberPool' || $server['settings'] == 'Bandwidth' || $server['settings'] == 'Infobip' || $server['settings'] == 'Tyntec' || $server['settings'] == 'EasySendSMS' || $server['settings'] == 'Skyetel' || $server['settings'] == 'Callr' || $server['settings'] == 'CMCOM' || $server['settings'] == 'Whatsender' || $server['settings'] == 'Gatewayapi' || $server['settings'] == 'VisionUp' || $server['settings'] == 'Solucoesdigitais' || $server['settings'] == SendingServer::TYPE_TELEAPI || $server['settings'] == SendingServer::TYPE_CLICKSEND || $server['settings'] == SendingServer::TYPE_CHEAPGLOBALSMS || $server['settings'] == SendingServer::TYPE_SMSMODE || $server['settings'] == SendingServer::TYPE_VOXIMPLANT || $server['settings'] == 'Inteliquent' || $server['settings'] == SendingServer::TYPE_MESSAGGIO || $server['settings'] == SendingServer::TYPE_SIMPLETEXTING)

                                                <div class="d-flex flex-column me-1">
                                                    <label class="form-check-label mb-50">{{__('locale.labels.two_way')}}</label>
                                                    <div class="form-check form-switch form-check-primary">
                                                        <input type="hidden" value="0" name="two_way">
                                                        <input type="checkbox" class="form-check-input" value="1"
                                                               name="two_way"
                                                               id="two_way" {{ $server['two_way'] ? 'checked': null }}>
                                                        <label class="form-check-label" for="two_way">
                                                            <span class='switch-icon-left'><i data-feather="check"></i> </span>
                                                            <span class='switch-icon-right'><i
                                                                        data-feather="x"></i> </span>
                                                        </label>
                                                    </div>
                                                </div>

                                            @endif

                                            @if($server['settings'] == 'Twilio' || $server['settings'] == 'Clickatell_Touch' || $server['settings'] == 'MessageBird' || $server['settings'] == 'WhatsAppChatApi' || $server['settings'] == 'WaApi' || $server['settings'] == 'YooAPI' || $server['settings'] == 'Whatsender' || $server['settings'] == 'Xmsway' || $server['settings'] == 'MidasAppBr' || $server['type'] == 'whatsapp' || $server['settings'] == SendingServer::TYPE_BMSGLOBAL || $server['settings'] ==  SendingServer::TYPE_TERMII || $server['settings'] ==  SendingServer::TYPE_WA2SALES || $server['settings'] ==  SendingServer::TYPE_INTERAKT)
                                                <div class="d-flex flex-column">
                                                    <label class="form-check-label mb-50">{{__('locale.labels.whatsapp')}}</label>
                                                    <div class="form-check form-switch form-check-primary">
                                                        <input type="hidden" value="0" name="whatsapp">
                                                        <input type="checkbox" class="form-check-input" value="1"
                                                               id="whatsapp"
                                                               name="whatsapp" {{ $server['whatsapp'] ? 'checked': null }}>
                                                        <label class="form-check-label" for="whatsapp">
                                                            <span class='switch-icon-left'><i data-feather="check"></i> </span>
                                                            <span class='switch-icon-right'><i
                                                                        data-feather="x"></i> </span>
                                                        </label>
                                                    </div>
                                                </div>
                                            @endif

                                            @if($server['settings'] == SendingServer::TYPE_MESSAGGIO || $server['type'] == 'viber')
                                                <div class="d-flex flex-column">
                                                    <label class="form-check-label mb-50">{{__('locale.menu.Viber')}}</label>
                                                    <div class="form-check form-switch form-check-primary">
                                                        <input type="hidden" value="0" name="viber">
                                                        <input type="checkbox" class="form-check-input" value="1"
                                                               id="viber"
                                                               name="viber" {{ $server['viber'] ? 'checked': null }}>
                                                        <label class="form-check-label" for="viber">
                                                            <span class='switch-icon-left'><i data-feather="check"></i> </span>
                                                            <span class='switch-icon-right'><i
                                                                        data-feather="x"></i> </span>
                                                        </label>
                                                    </div>
                                                </div>
                                            @endif


                                            @if($server['settings'] == SendingServer::TYPE_MSG91 || $server['settings'] == SendingServer::TYPE_SMARTSMSSOLUTIONS || $server['settings'] == SendingServer::TYPE_VOICEANDTEXT  || $server['settings'] == SendingServer::TYPE_FAST2SMS || $server['type'] == 'otp')
                                                <div class="d-flex flex-column">
                                                    <label class="form-check-label mb-50">{{__('locale.menu.OTP')}}</label>
                                                    <div class="form-check form-switch form-check-primary">
                                                        <input type="hidden" value="0" name="otp">
                                                        <input type="checkbox" class="form-check-input" value="1"
                                                               id="otp"
                                                               name="otp" {{ $server['otp'] ? 'checked': null }}>
                                                        <label class="form-check-label" for="otp">
                                                            <span class='switch-icon-left'><i data-feather="check"></i> </span>
                                                            <span class='switch-icon-right'><i
                                                                        data-feather="x"></i> </span>
                                                        </label>
                                                    </div>
                                                </div>
                                            @endif


                                        </div>

                                        <div class="col-12 mt-2">
                                            <input type="hidden" name="settings" value="{{$server['settings']}}">
                                            <input type="hidden" name="type" value="{{$server['type']}}">
                                            <button type="submit" class="btn btn-primary mr-1 mb-1"><i
                                                        data-feather="save"></i> {{__('locale.buttons.save')}} </button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>


                </form>

            </div>
        </div>
    </section>
    <!-- // Basic Vertical form layout section end -->

@endsection
