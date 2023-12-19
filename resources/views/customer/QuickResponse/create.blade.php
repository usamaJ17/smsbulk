@extends('layouts/contentLayoutMaster')
@if(isset($reply))
    @section('title', __('locale.quick_replies.update_quick_reply'))
@else
    @section('title', __('locale.quick_replies.new_quick_reply'))
@endif

@section('content')

    <!-- Basic Vertical form layout section start -->
    <section id="basic-vertical-layouts">
        <div class="row match-height">
            <div class="col-md-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">@if(isset($reply)) {{ __('locale.quick_replies.update_quick_reply') }} @else {{ __('locale.quick_replies.new_quick_reply') }} @endif </h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">

                            <form class="form form-vertical" @if(isset($reply)) action="{{ route('customer.quick-replies.update',  $reply->id) }}" @else action="{{ route('customer.quick-replies.store') }}" @endif method="post">
                                @if(isset($reply))
                                    {{ method_field('PUT') }}
                                @endif
                                @csrf
                                <div class="form-body">
                                    <div class="row">

                                        <div class="col-12">
                                            <div class="mb-1">
                                                <label for="name" class="form-label required">{{ __('locale.labels.text') }}</label>
                                                <input type="text" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name',  $reply->name ?? null) }}" name="name" required placeholder="{{__('locale.labels.required')}}" autofocus>
                                                @error('name')
                                                <p><small class="text-danger">{{ $message }}</small></p>
                                                @enderror
                                            </div>
                                        </div>
                                        <input type="hidden" name="user_id" value="{{\Auth::id()}}">
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary mb-1"><i data-feather="save"></i> {{ __('locale.buttons.save') }}</button>
                                        </div>

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
