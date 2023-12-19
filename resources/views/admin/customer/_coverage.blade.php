@extends('layouts/contentLayoutMaster')

@if(isset($coverage))
    @section('title', __('locale.buttons.update_coverage'))
@else
    @section('title', __('locale.buttons.add_coverage'))
@endif

@section('vendor-style')
    <!-- vendor css files -->
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/forms/select/select2.min.css')) }}">
@endsection

@section('content')
    <!-- Basic Vertical form layout section start -->
    <section id="basic-vertical-layouts">
        <div class="row match-height">
            <div class="col-md-6 col-12">

                <div class="card">
                    <div class="card-header">

                        <h4 class="card-title">@if(isset($coverage))
                                {{ __('locale.buttons.update_coverage') }}
                            @else
                                {{ __('locale.buttons.add_coverage') }}
                            @endif </h4>
                    </div>

                    <div class="card-content">
                        <div class="card-body">
                            <p>{!! __('locale.description.pricing_intro') !!}</p>
                            <div class="form-body">
                                <form class="form form-vertical"
                                      @if(isset($coverage)) action="{{ route('admin.customers.edit_coverage', ['customer' => $customer->uid, 'coverage' => $coverage->uid]) }}"
                                      @else action="{{ route('admin.', $customer->uid) }}"
                                      @endif method="post">
                                    @csrf
                                    <div class="row">

                                        @if(isset($coverage))
                                            <input type="hidden" value="{{ $coverage->country_id }}" name="country">
                                        @else
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label for="country"
                                                           class="form-label required">{{__('locale.labels.country')}}</label>
                                                    <select data-placeholder="{{ __('locale.labels.choose_your_option') }}"
                                                            class="form-select select2" id="country" name="country[]"
                                                            multiple>
                                                        @foreach($countries as $country)
                                                            <option value="{{$country->id}}"> {{ $country->name }}
                                                                (+{{$country->country_code}})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                @error('country')
                                                <p><small class="text-danger">{{ $message }}</small></p>
                                                @enderror
                                            </div>
                                        @endif


                                        <div class="divider divider-start divider-info mt-2">
                                            <div class="divider-text text-uppercase fw-bold text-primary">{{ __('locale.labels.plain') }}</div>
                                        </div>

                                        <div class="col-12">
                                            <div class="mb-1">
                                                <label for="sending_server"
                                                       class="form-label required">{{__('locale.plans.sending_server_for_sms', ['sms_type' => __('locale.labels.plain')])}}</label>
                                                <select data-placeholder="{{ __('locale.labels.choose_your_option') }}"
                                                        class="form-select select2" id="sending_server"
                                                        name="sending_server">
                                                    @foreach($sending_servers as $server)
                                                        @if($server->plain)
                                                            <option value="{{$server->id}}"
                                                                    @if(isset($coverage) && $coverage->sending_server == $server->id) selected @endif> {{ $server->name }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                            @error('sending_server')
                                            <p><small class="text-danger">{{ $message }}</small></p>
                                            @enderror
                                        </div>

                                        <div class="col-md-6 col-12">
                                            <div class="mb-1">
                                                <label for="plain_sms"
                                                       class="required form-label">{{__('locale.labels.plain_sms')}}</label>
                                                <input type="text" id="plain_sms"
                                                       class="form-control @error('plain_sms') is-invalid @enderror"
                                                       value="{{ old('plain_sms',  $options['plain_sms'] ?? null) }}"
                                                       name="plain_sms" required>
                                                @error('plain_sms')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-6 col-12">
                                            <div class="mb-1">
                                                <label for="receive_plain_sms"
                                                       class="required form-label">{{__('locale.labels.receive')}} {{__('locale.labels.plain_sms')}}</label>
                                                <input type="text" id="receive_plain_sms"
                                                       class="form-control @error('receive_plain_sms') is-invalid @enderror"
                                                       value="{{ old('receive_plain_sms',  $options['receive_plain_sms'] ?? null) }}"
                                                       name="receive_plain_sms" required>
                                                @error('receive_plain_sms')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="divider divider-start divider-info mt-2">
                                            <div class="divider-text text-uppercase fw-bold text-primary">{{ __('locale.labels.voice') }}</div>
                                        </div>


                                        <div class="col-12">
                                            <div class="mb-1">
                                                <label for="voice_sending_server"
                                                       class="form-label required">{{__('locale.plans.sending_server_for_sms', ['sms_type' => __('locale.labels.voice')])}}</label>
                                                <select data-placeholder="{{ __('locale.labels.choose_your_option') }}"
                                                        class="form-select select2" id="voice_sending_server"
                                                        name="voice_sending_server">
                                                    @foreach($sending_servers as $server)
                                                        @if($server->voice)
                                                            <option value="{{$server->id}}"
                                                                    @if(isset($coverage) && $coverage->voice_sending_server == $server->id) selected @endif> {{ $server->name }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                            @error('voice_sending_server')
                                            <p><small class="text-danger">{{ $message }}</small></p>
                                            @enderror
                                        </div>


                                        <div class="col-md-6 col-12">
                                            <div class="mb-1">
                                                <label for="voice_sms"
                                                       class="required form-label">{{__('locale.labels.voice_sms')}}</label>
                                                <input type="text" id="voice_sms"
                                                       class="form-control @error('voice_sms') is-invalid @enderror"
                                                       value="{{ old('voice_sms',  $options['voice_sms'] ?? null) }}"
                                                       name="voice_sms" required>
                                                @error('voice_sms')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="col-md-6 col-12">
                                            <div class="mb-1">
                                                <label for="receive_voice_sms"
                                                       class="required form-label">{{__('locale.labels.receive')}} {{__('locale.labels.voice_sms')}}</label>
                                                <input type="text" id="receive_voice_sms"
                                                       class="form-control @error('receive_voice_sms') is-invalid @enderror"
                                                       value="{{ old('receive_voice_sms',  $options['receive_voice_sms'] ?? null) }}"
                                                       name="receive_voice_sms" required>
                                                @error('receive_voice_sms')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="divider divider-start divider-info mt-2">
                                            <div class="divider-text text-uppercase fw-bold text-primary">{{ __('locale.labels.mms') }}</div>
                                        </div>


                                        <div class="col-12">
                                            <div class="mb-1">
                                                <label for="mms_sending_server"
                                                       class="form-label required">{{__('locale.plans.sending_server_for_sms', ['sms_type' => __('locale.labels.mms')])}}</label>
                                                <select data-placeholder="{{ __('locale.labels.choose_your_option') }}"
                                                        class="form-select select2" id="mms_sending_server"
                                                        name="mms_sending_server">
                                                    @foreach($sending_servers as $server)
                                                        @if($server->mms)
                                                            <option value="{{$server->id}}"
                                                                    @if(isset($coverage) && $coverage->mms_sending_server == $server->id) selected @endif> {{ $server->name }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                            @error('mms_sending_server')
                                            <p><small class="text-danger">{{ $message }}</small></p>
                                            @enderror
                                        </div>


                                        <div class="col-md-6 col-12">
                                            <div class="mb-1">
                                                <label for="mms_sms"
                                                       class="required form-label">{{__('locale.labels.mms_sms')}}</label>
                                                <input type="text" id="mms_sms"
                                                       class="form-control @error('mms_sms') is-invalid @enderror"
                                                       value="{{ old('mms_sms',  $options['mms_sms'] ?? null) }}"
                                                       name="mms_sms" required>
                                                @error('mms_sms')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="col-md-6 col-12">
                                            <div class="mb-1">
                                                <label for="receive_mms_sms"
                                                       class="required form-label">{{__('locale.labels.receive')}} {{__('locale.labels.mms_sms')}}</label>
                                                <input type="text" id="receive_mms_sms"
                                                       class="form-control @error('receive_mms_sms') is-invalid @enderror"
                                                       value="{{ old('receive_mms_sms',  $options['receive_mms_sms'] ?? null) }}"
                                                       name="receive_mms_sms" required>
                                                @error('receive_mms_sms')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="divider divider-start divider-info mt-2">
                                            <div class="divider-text text-uppercase fw-bold text-primary">{{ __('locale.labels.whatsapp') }}</div>
                                        </div>


                                        <div class="col-12">
                                            <div class="mb-1">
                                                <label for="whatsapp_sending_server"
                                                       class="form-label required">{{__('locale.plans.sending_server_for_sms', ['sms_type' => __('locale.labels.whatsapp')])}}</label>
                                                <select data-placeholder="{{ __('locale.labels.choose_your_option') }}"
                                                        class="form-select select2" id="whatsapp_sending_server"
                                                        name="whatsapp_sending_server">
                                                    @foreach($sending_servers as $server)
                                                        @if($server->whatsapp)
                                                            <option value="{{$server->id}}"
                                                                    @if(isset($coverage) && $coverage->whatsapp_sending_server == $server->id) selected @endif> {{ $server->name }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                            @error('whatsapp_sending_server')
                                            <p><small class="text-danger">{{ $message }}</small></p>
                                            @enderror
                                        </div>


                                        <div class="col-md-6 col-12">
                                            <div class="mb-1">
                                                <label for="whatsapp_sms"
                                                       class="required form-label">{{__('locale.labels.whatsapp_sms')}}</label>
                                                <input type="text" id="whatsapp_sms"
                                                       class="form-control @error('whatsapp_sms') is-invalid @enderror"
                                                       value="{{ old('whatsapp_sms',  $options['whatsapp_sms'] ?? null) }}"
                                                       name="whatsapp_sms" required>
                                                @error('whatsapp_sms')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="col-md-6 col-12">
                                            <div class="mb-1">
                                                <label for="receive_whatsapp_sms"
                                                       class="required form-label">{{__('locale.labels.receive')}} {{__('locale.labels.whatsapp_sms')}}</label>
                                                <input type="text" id="receive_whatsapp_sms"
                                                       class="form-control @error('receive_whatsapp_sms') is-invalid @enderror"
                                                       value="{{ old('receive_whatsapp_sms',  $options['receive_whatsapp_sms'] ?? null) }}"
                                                       name="receive_whatsapp_sms" required>
                                                @error('receive_whatsapp_sms')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="divider divider-start divider-info mt-2">
                                            <div class="divider-text text-uppercase fw-bold text-primary">{{ __('locale.menu.Viber') }}</div>
                                        </div>


                                        <div class="col-12">
                                            <div class="mb-1">
                                                <label for="viber_sending_server"
                                                       class="form-label required">{{__('locale.plans.sending_server_for_sms', ['sms_type' => __('locale.menu.Viber')])}}</label>
                                                <select data-placeholder="{{ __('locale.labels.choose_your_option') }}"
                                                        class="form-select select2" id="viber_sending_server"
                                                        name="viber_sending_server">
                                                    @foreach($sending_servers as $server)
                                                        @if($server->viber)
                                                            <option value="{{$server->id}}"
                                                                    @if(isset($coverage) && $coverage->viber_sending_server == $server->id) selected @endif> {{ $server->name }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                            @error('viber_sending_server')
                                            <p><small class="text-danger">{{ $message }}</small></p>
                                            @enderror
                                        </div>


                                        <div class="col-md-6 col-12">
                                            <div class="mb-1">
                                                <label for="viber_sms"
                                                       class="required form-label">{{__('locale.labels.viber_sms')}}</label>
                                                <input type="text" id="viber_sms"
                                                       class="form-control @error('viber_sms') is-invalid @enderror"
                                                       value="{{ old('viber_sms',  $options['viber_sms'] ?? null) }}"
                                                       name="viber_sms" required>
                                                @error('viber_sms')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="col-md-6 col-12">
                                            <div class="mb-1">
                                                <label for="receive_viber_sms"
                                                       class="required form-label">{{__('locale.labels.receive')}} {{__('locale.labels.viber_sms')}}</label>
                                                <input type="text" id="receive_viber_sms"
                                                       class="form-control @error('receive_viber_sms') is-invalid @enderror"
                                                       value="{{ old('receive_viber_sms',  $options['receive_viber_sms'] ?? null) }}"
                                                       name="receive_viber_sms" required>
                                                @error('receive_viber_sms')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="divider divider-start divider-info mt-2">
                                            <div class="divider-text text-uppercase fw-bold text-primary">{{ __('locale.menu.OTP') }}</div>
                                        </div>


                                        <div class="col-12">
                                            <div class="mb-1">
                                                <label for="otp_sending_server"
                                                       class="form-label required">{{__('locale.plans.sending_server_for_sms', ['sms_type' => __('locale.menu.OTP')])}}</label>
                                                <select data-placeholder="{{ __('locale.labels.choose_your_option') }}"
                                                        class="form-select select2" id="otp_sending_server"
                                                        name="otp_sending_server">
                                                    @foreach($sending_servers as $server)
                                                        @if($server->otp)
                                                            <option value="{{$server->id}}"
                                                                    @if(isset($coverage) && $coverage->otp_sending_server == $server->id) selected @endif> {{ $server->name }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                            @error('otp_sending_server')
                                            <p><small class="text-danger">{{ $message }}</small></p>
                                            @enderror
                                        </div>


                                        <div class="col-md-6 col-12">
                                            <div class="mb-1">
                                                <label for="otp_sms"
                                                       class="required form-label">{{__('locale.labels.otp_sms')}}</label>
                                                <input type="text" id="otp_sms"
                                                       class="form-control @error('otp_sms') is-invalid @enderror"
                                                       value="{{ old('otp_sms',  $options['otp_sms'] ?? null) }}"
                                                       name="otp_sms" required>
                                                @error('otp_sms')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="col-md-6 col-12">
                                            <div class="mb-1">
                                                <label for="receive_otp_sms"
                                                       class="required form-label">{{__('locale.labels.receive')}} {{__('locale.labels.otp_sms')}}</label>
                                                <input type="text" id="receive_otp_sms"
                                                       class="form-control @error('receive_otp_sms') is-invalid @enderror"
                                                       value="{{ old('receive_otp_sms',  $options['receive_otp_sms'] ?? null) }}"
                                                       name="receive_otp_sms" required>
                                                @error('receive_otp_sms')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                                @enderror
                                            </div>
                                        </div>


                                        <div class="col-12 mt-2">
                                            <button type="submit" class="btn btn-primary mr-1 mb-1">
                                                <i data-feather="save"></i> {{__('locale.buttons.save')}}
                                            </button>
                                        </div>

                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>


            </div>
        </div>
    </section>
    <!-- // Basic Vertical form layout section end -->

@endsection

@section('vendor-script')
    <!-- vendor files -->
    <script src="{{ asset(mix('vendors/js/forms/select/select2.full.min.js')) }}"></script>
@endsection


@section('page-script')

    <script>

        let firstInvalid = $('form').find('.is-invalid').eq(0);

        if (firstInvalid.length) {
            $('body, html').stop(true, true).animate({
                'scrollTop': firstInvalid.offset().top - 200 + 'px'
            }, 200);
        }

        // Basic Select2 select
        $(".select2").each(function () {
            let $this = $(this);
            $this.wrap('<div class="position-relative"></div>');
            $this.select2({
                // the following code is used to disable x-scrollbar when click in select input and
                // take 100% width in responsive also
                dropdownAutoWidth: true,
                width: '100%',
                dropdownParent: $this.parent()
            });
        });

    </script>
@endsection
