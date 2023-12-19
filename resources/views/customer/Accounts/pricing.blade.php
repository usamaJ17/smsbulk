@extends('layouts/contentLayoutMaster')

@section('title', __('locale.plans.pricing'))

@section('vendor-style')
    <!-- vendor css files -->
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/dataTables.bootstrap5.min.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/responsive.bootstrap5.min.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/buttons.bootstrap5.min.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/extensions/sweetalert2.min.css')) }}">
@endsection


@section('content')
    <!-- Basic table -->
    <section id="datatables-basic">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <table class="table pricing_table datatables-basic">
                        <thead>
                        <tr>
                            <th></th>
                            <th>{{ __('locale.labels.id') }}</th>
                            <th>{{__('locale.labels.name')}} </th>
                            <th>{{__('locale.labels.iso_code')}}</th>
                            <th>{{__('locale.labels.country_code')}}</th>
                            <th>{{__('locale.labels.actions')}}</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </section>
    <!--/ Basic table -->

@endsection

@section('vendor-script')

    <script src="{{ asset(mix('vendors/js/tables/datatable/jquery.dataTables.min.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/tables/datatable/dataTables.bootstrap5.min.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/tables/datatable/dataTables.responsive.min.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/tables/datatable/responsive.bootstrap5.min.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/tables/datatable/datatables.buttons.min.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/tables/datatable/buttons.html5.min.js')) }}"></script>

    <script src="{{ asset(mix('vendors/js/extensions/sweetalert2.all.min.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/extensions/polyfill.min.js')) }}"></script>
@endsection


@section('page-script')

    <script>

        $(document).ready(function () {
            "use strict"


            $('.datatables-basic').DataTable({

                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('user.account.pricing') }}",
                    "dataType": "json",
                    "type": "POST",
                    "data": {_token: "{{csrf_token()}}"}
                },
                "columns": [
                    {"data": 'responsive_id', orderable: false, searchable: false},
                    {"data": "uid"},
                    {"data": "name", orderable: false},
                    {"data": "iso_code", orderable: false},
                    {"data": "country_code", orderable: false},
                    {"data": "action", orderable: false, searchable: false}
                ],

                searchDelay: 1500,
                columnDefs: [
                    {
                        // For Responsive
                        className: 'control',
                        orderable: false,
                        responsivePriority: 2,
                        targets: 0
                    },
                    {
                        targets: 1,
                        visible: false
                    },
                    {
                        // Actions
                        targets: -1,
                        title: '{{ __('locale.labels.actions') }}',
                        orderable: false,
                        render: function (data, type, full) {
                            return (
                                '<span class="action-view text-primary cursor-pointer" data-value=' + full['by_plan'] + ' data-id=' + full['uid'] + '>' +
                                feather.icons['tag'].toSvg({class: 'font-medium-4'}) +
                                '</span>'

                            );
                        }
                    }
                ],
                dom: '<"d-flex justify-content-between align-items-center mx-0 row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"d-flex justify-content-between mx-0 row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',

                language: {
                    paginate: {
                        // remove previous & next text from pagination
                        previous: '&nbsp;',
                        next: '&nbsp;'
                    },
                    sLengthMenu: "_MENU_",
                    sZeroRecords: "{{ __('locale.datatables.no_results') }}",
                    sSearch: "{{ __('locale.datatables.search') }}",
                    sProcessing: "{{ __('locale.datatables.processing') }}",
                    sInfo: "{{ __('locale.datatables.showing_entries', ['start' => '_START_', 'end' => '_END_', 'total' => '_TOTAL_']) }}"
                },
                responsive: {
                    details: {
                        display: $.fn.dataTable.Responsive.display.modal({
                            header: function (row) {
                                let data = row.data();
                                return 'Details of ' + data['name'];
                            }
                        }),
                        type: 'column',
                        renderer: function (api, rowIdx, columns) {
                            let data = $.map(columns, function (col) {
                                return col.title !== '' // ? Do not show row in modal popup if title is blank (for check box)
                                    ? '<tr data-dt-row="' +
                                    col.rowIdx +
                                    '" data-dt-column="' +
                                    col.columnIndex +
                                    '">' +
                                    '<td>' +
                                    col.title +
                                    ':' +
                                    '</td> ' +
                                    '<td>' +
                                    col.data +
                                    '</td>' +
                                    '</tr>'
                                    : '';
                            }).join('');

                            return data ? $('<table class="table pricing_table"/>').append('<tbody>' + data + '</tbody>') : false;
                        }
                    }
                },
                aLengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],
                select: {
                    style: "multi"
                },
                order: [[1, "asc"]],
                displayLength: 10,
            });

        });

        $('table').delegate(".action-view", "click", function (e) {
            e.stopPropagation();
            let id = $(this).data('id'),
                value = $(this).data('value'),
                dataType;

            if (value === 'no') {
                dataType = 'customer';
            } else {
                dataType = 'plan';
            }

            $.ajax({
                url: "{{ route('user.account.pricing-view') }}",
                type: "POST",
                data: {
                    _token: "{{csrf_token()}}",
                    dataType: dataType,
                    uid: id
                },
                success: function (data) {
                    let ViewData = $.parseJSON(data.data);
                    let CurrencyCode = "{{ str_replace('{PRICE}', '', Auth::user()->customer->subscription->plan->currency->format) }}";
                    let html = `
            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <td width="70%">{{ __('locale.labels.plain_sms') }}</td>
                            <td>${CurrencyCode + ViewData.plain_sms}</td>
                        </tr>
                        <tr>
                            <td width="70%">{{ __('locale.labels.voice_sms') }}</td>
                            <td>${CurrencyCode + ViewData.voice_sms}</td>
                        </tr>
                        <tr>
                            <td width="70%">{{ __('locale.labels.mms_sms') }}</td>
                            <td>${CurrencyCode + ViewData.mms_sms}</td>
                        </tr>
                        <tr>
                            <td width="70%">{{ __('locale.labels.whatsapp_sms') }}</td>
                            <td>${CurrencyCode + ViewData.whatsapp_sms}</td>
                        </tr>
                        <tr>
                            <td width="70%">{{ __('locale.labels.viber_sms') }}</td>
                            <td>${CurrencyCode + ViewData.viber_sms}</td>
                        </tr>
                        <tr>
                            <td width="70%">{{ __('locale.labels.otp_sms') }}</td>
                            <td>${CurrencyCode + ViewData.otp_sms}</td>
                        </tr>
                        <tr>
                            <td width="70%">{{ __('locale.labels.receive') }} {{ __('locale.labels.plain_sms') }}</td>
                            <td>${CurrencyCode + ViewData.receive_plain_sms}</td>
                        </tr>

                        <tr>
                            <td width="70%">{{ __('locale.labels.receive') }} {{ __('locale.labels.voice_sms') }}</td>
                            <td>${CurrencyCode + ViewData.receive_voice_sms}</td>
                        </tr>
                        <tr>
                            <td width="70%">{{ __('locale.labels.receive') }} {{ __('locale.labels.mms_sms') }}</td>
                            <td>${CurrencyCode + ViewData.receive_mms_sms}</td>
                        </tr>
                        <tr>
                            <td width="70%">{{ __('locale.labels.receive') }} {{ __('locale.labels.whatsapp_sms') }}</td>
                            <td>${CurrencyCode + ViewData.receive_whatsapp_sms}</td>
                        </tr>
                        <tr>
                            <td width="70%">{{ __('locale.labels.receive') }} {{ __('locale.labels.viber_sms') }}</td>
                            <td>${CurrencyCode + ViewData.receive_viber_sms}</td>
                        </tr>
                        <tr>
                            <td width="70%">{{ __('locale.labels.receive') }} {{ __('locale.labels.otp_sms') }}</td>
                            <td>${CurrencyCode + ViewData.receive_otp_sms}</td>
                        </tr>
                    </tbody>
                </table>
            </div>`;

                    Swal.fire({
                        html: html
                    });
                },
                error: function (reject) {
                    handleAjaxError(reject);
                }
            });

            function handleAjaxError(reject) {
                let errorMessage = reject.responseJSON.message || "{{__('locale.labels.attention')}}";
                let toastrOptions = {
                    closeButton: true,
                    positionClass: 'toast-top-right',
                    progressBar: true,
                    newestOnTop: true,
                    rtl: isRtl
                };

                if (reject.status === 422) {
                    let errors = reject.responseJSON.errors;
                    $.each(errors, function (key, value) {
                        toastr['warning'](value[0], "{{__('locale.labels.attention')}}", toastrOptions);
                    });
                } else {
                    toastr['warning'](errorMessage, "{{__('locale.labels.attention')}}", toastrOptions);
                }
            }


        });

    </script>

@endsection
