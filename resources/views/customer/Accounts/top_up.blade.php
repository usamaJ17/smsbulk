@extends('layouts/contentLayoutMaster')

@section('title', __('locale.labels.top_up'))

@section('content')
    <!-- Basic Vertical form layout section start -->
    <section id="basic-vertical-layouts">
        <div class="row match-height">
            <div class="col-md-6 col-12">

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"> {{__('locale.customer.add_unit_to_your_account')}} </h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <div class="form-body">
                                <form class="form form-vertical" action="{{ route('user.account.top_up') }}" method="post">
                                    @csrf
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="col-12">
                                                <div class="mb-1">
                                                    <label for="add_unit" class="form-label required">{{ __('locale.labels.amount') }}</label>
                                                    <div class="input-group input-group-merge mb-2">
                                                        <input type="text" id="add_unit" class="form-control text-end @error('add_unit') is-invalid @enderror" name="add_unit" required>
                                                        <span class="input-group-text ">{{ str_replace('{PRICE}', '', Auth::user()->customer->subscription->plan->currency->format) }}</span>

                                                        @error('add_unit')
                                                        <p><small class="text-danger">{{ $message }}</small></p>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary mb-1">
                                                <i data-feather="shopping-cart"></i> {{__('locale.labels.checkout')}}
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


@section('page-script')

    <script>
        let firstInvalid = $('form').find('.is-invalid').eq(0);

        if (firstInvalid.length) {
            $('body, html').stop(true, true).animate({
                'scrollTop': firstInvalid.offset().top - 200 + 'px'
            }, 200);
        }

    </script>
@endsection
