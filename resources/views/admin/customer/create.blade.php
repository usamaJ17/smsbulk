@extends('layouts/contentLayoutMaster')

@section('title', __('locale.customer.add_new'))

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
                        <h4 class="card-title"> {{__('locale.customer.add_new')}} </h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <form class="form form-vertical" action="{{ route('admin.customers.store') }}" method="post" enctype="multipart/form-data">
                                @csrf
                                <div class="row">

                                    <div class="col-12">
                                        <div class="mb-1">
                                            <label for="email" class="required form-label">{{__('locale.labels.email')}}</label>
                                            <input type="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" name="email" required>
                                            @error('email')
                                            <div class="invalid-feedback">
                                                {{ $message }}
                                            </div>
                                            @enderror
                                        </div>
                                    </div>


                                    <div class="col-12">
                                        <div class="mb-1">
                                            <label class="form-label required" for="password">{{ __('locale.labels.password') }}</label>
                                            <div class="input-group input-group-merge form-password-toggle">
                                                <input type="password" id="password" class="form-control @error('password') is-invalid @enderror" value="{{ old('password') }}" name="password" required/>
                                                <span class="input-group-text cursor-pointer"><i data-feather="eye"></i></span>
                                            </div>

                                            @error('password')
                                            <div class="invalid-feedback">
                                                {{ $message }}
                                            </div>
                                            @enderror
                                        </div>

                                    </div>

                                    <div class="col-12">
                                        <div class="mb-1">
                                            <label class="form-label required" for="password_confirmation">{{ __('locale.labels.password_confirmation') }}</label>
                                            <div class="input-group input-group-merge form-password-toggle">
                                                <input type="password" id="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror"
                                                       value="{{ old('password_confirmation') }}"
                                                       name="password_confirmation" required/>
                                                <span class="input-group-text cursor-pointer"><i data-feather="eye"></i></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-1">
                                            <label for="first_name" class="required form-label">{{__('locale.labels.first_name')}}</label>
                                            <input type="text" id="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" name="first_name" required>
                                            @error('first_name')
                                            <div class="invalid-feedback">
                                                {{ $message }}
                                            </div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-1">
                                            <label for="last_name" class="form-label">{{__('locale.labels.last_name')}}</label>
                                            <input type="text" id="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}" name="last_name">
                                            @error('last_name')
                                            <div class="invalid-feedback">
                                                {{ $message }}
                                            </div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-1">
                                            <label for="phone" class="required form-label">{{__('locale.labels.phone')}}</label>
                                            <input type="number" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" name="phone" required>
                                            @error('phone')
                                            <div class="invalid-feedback">
                                                {{ $message }}
                                            </div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="mb-1">
                                            <label for="timezone" class="required form-label">{{__('locale.labels.timezone')}}</label>
                                            <select class="select2 w-100" id="timezone" name="timezone">
                                                @foreach(\App\Library\Tool::allTimeZones() as $timezone)
                                                    <option value="{{$timezone['zone']}}" {{ config('app.timezone') == $timezone['zone'] ? 'selected': null }}> {{ $timezone['text'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @error('timezone')
                                        <div class="text-danger">
                                            {{ $message }}
                                        </div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-1">
                                            <label for="locale" class="required form-label">{{__('locale.labels.language')}}</label>
                                            <select class="select2 w-100" id="locale" name="locale">
                                                @foreach($languages as $language)
                                                    <option value="{{ $language->code }}" {{old('locale') == $language->code ? 'selected': null }}> {{ $language->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @error('locale')
                                        <div class="text-danger">
                                            {{ $message }}
                                        </div>
                                        @enderror
                                    </div>


                                    <div class="col-12">
                                        <div class="mb-1">
                                            <label for="status" class="required form-label">{{ __('locale.labels.status') }}</label>
                                            <select class="form-select" name="status" id="status">
                                                <option value="1">{{ __('locale.labels.active') }}</option>
                                                <option value="0">{{ __('locale.labels.inactive')}} </option>
                                            </select>
                                            @error('status')
                                            <div class="text-danger">
                                                {{ $message }}
                                            </div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-1">
                                            <label for="image" class="form-label">{{__('locale.labels.image')}}</label>
                                            <input type="file" name="image" class="form-control" id="image" accept="image/*"/>
                                            @error('image')
                                            <div class="text-danger">
                                                {{ $message }}
                                            </div>
                                            @enderror
                                            <p><small class="text-primary"> {{__('locale.customer.profile_image_size')}} </small></p>
                                        </div>
                                    </div>
                                    <input type="hidden" name="admin_spam" id="admin_spam" value=0>
                                    <div class="col-12">
                                        <div class="mb-1">
                                            <div class="form-check form-check-inline">
                                                <input type="checkbox" id="admin_spam_c" class="form-check-input" name="admin_spam_c" {{ old('admin_spam_c') == true ? "checked" : null }}>
                                                <label class="form-check-label" for="admin_spam_c">Remove Reseller Spam?</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-1">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="welcome_message" value="checked" name="welcome_message">
                                                <label class="form-check-label" for="welcome_message">{{ __('locale.customer.send_welcome_email') }}</label>
                                            </div>
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
        var checkbox = $('#admin_spam_c');
        var inputHidden = $('#admin_spam');

        // Set initial value based on the checkbox state
        inputHidden.val(checkbox.prop('checked') ? 1 : 0);

        // Add an event listener to detect changes to the checkbox
        checkbox.on('change', function () {
            // Update the value of the hidden input based on the checkbox state
            inputHidden.val(checkbox.prop('checked') ? 1 : 0);
        });
    </script>
@endsection

