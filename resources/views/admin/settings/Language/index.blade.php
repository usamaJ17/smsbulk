@extends('layouts/contentLayoutMaster')

@section('title', __('locale.menu.Language'))

@section('vendor-style')
    {{-- vendor css files --}}
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/dataTables.bootstrap5.min.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/responsive.bootstrap5.min.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/tables/datatable/buttons.bootstrap5.min.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/extensions/sweetalert2.min.css')) }}">

@endsection

@section('content')

    <!-- Basic table -->
    <section id="datatables-basic">
        <div class="mb-3 mt-2">

            @can('new languages')
                <div class="btn-group">
                    <a href="{{route('admin.languages.create')}}" class="btn btn-success waves-light waves-effect fw-bold mx-1"> {{__('locale.buttons.add_new')}} <i data-feather="plus-circle"></i></a>
                </div>
            @endcan
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <table class="table datatables-basic">
                        <thead>
                        <tr>
                            <th></th>
                            <th>{{__('locale.labels.name')}}</th>
                            <th>{{__('locale.currencies.code')}}</th>
                            <th>{{__('locale.labels.status')}}</th>
                            <th>{{__('locale.labels.actions')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($languages as $lang)
                            <tr>
                                <td></td>
                                <td>{{ $lang->name }}</td>
                                <td>{{ strtoupper($lang->code) }}</td>
                                <td>
                                    @if($lang->code != 'en')
                                        <div class='form-check form-switch form-check-primary'>
                                            <input type="checkbox" class="form-check-input get_status" id="{{ $lang->id }}" data-id="{{ $lang->id }}" name='status' @if($lang->status == true) checked @endif>
                                            <label class="form-check-label" for="{{ $lang->id }}">
                                                <span class="switch-icon-left"><i data-feather="check"></i> </span>
                                                <span class="switch-icon-right"><i data-feather="x"></i> </span>
                                            </label>
                                        </div>
                                    @else
                                        <span class="badge bg-success">{{ __('locale.labels.active') }}</span>
                                    @endif
                                </td>
                                <td>

                                    <a href="{{ route('admin.languages.upload', $lang->id) }}" class='text-info me-1' data-bs-toggle='tooltip' data-bs-placement='top' title="{{__('locale.labels.upload')}}"> <i data-feather="upload" class="feather-20"></i></a>
                                    <a href="{{route('admin.languages.download', $lang->id)}}" class='text-success me-1 action-download' data-bs-toggle='tooltip' data-bs-placement='top' title="{{__('locale.labels.download')}}"> <i data-feather="download" class="feather-20"></i></a>
                                    @if($lang->code != 'en')
                                        <a href="{{ route('admin.languages.show', $lang->id) }}" class='text-primary me-1' data-bs-toggle='tooltip' data-bs-placement='top' title="{{__('locale.labels.translate')}}"> <i data-feather="external-link" class="feather-20"></i></a>
                                        <span class='action-delete text-danger cursor-pointer' data-id="{{$lang->id}}" data-bs-toggle='tooltip' data-bs-placement='top' title="{{__('locale.buttons.delete')}}"><i data-feather="trash" class="feather-20"></i></span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
    <!--/ Basic table -->

@endsection

@section('vendor-script')
    {{-- vendor files --}}
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
    {{-- Page js files --}}
    <script>
        $(document).ready(function () {
            "use strict"

            let Table = $("table");

            //show response message
            function showResponseMessage(data) {

                if (data.status === 'success') {
                    toastr['success'](data.message, '{{__('locale.labels.success')}}!!', {
                        closeButton: true,
                        positionClass: 'toast-top-right',
                        progressBar: true,
                        newestOnTop: true,
                        rtl: isRtl
                    });
                    dataListView.draw();
                } else if (data.status === 'error') {
                    toastr['error'](data.message, '{{ __('locale.labels.opps') }}!', {
                        closeButton: true,
                        positionClass: 'toast-top-right',
                        progressBar: true,
                        newestOnTop: true,
                        rtl: isRtl
                    });
                    dataListView.draw();
                } else {
                    toastr['warning']("{{__('locale.exceptions.something_went_wrong')}}", '{{ __('locale.labels.warning') }}!', {
                        closeButton: true,
                        positionClass: 'toast-top-right',
                        progressBar: true,
                        newestOnTop: true,
                        rtl: isRtl
                    });
                }
            }


            let dataListView = $('.datatables-basic').DataTable({

                "processing": true,
                "columns": [
                    {"data": "id", orderable: false, searchable: false},
                    {"data": "name"},
                    {"data": "code"},
                    {"data": "status"},
                    {"data": "action", orderable: false, searchable: false}
                ],
                columnDefs: [
                    {
                        // For Responsive
                        className: 'control',
                        orderable: false,
                        responsivePriority: 2,
                        targets: 0
                    },
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

                            return data ? $('<table class="table"/>').append('<tbody>' + data + '</tbody>') : false;
                        }
                    }
                },
                aLengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],

                order: [[0, "desc"]],
                displayLength: 20,
            });

            //change status
            Table.delegate(".get_status", "click", function () {
                let language_id = $(this).data('id');
                $.ajax({
                    url: "{{ url(config('app.admin_path').'/languages')}}" + '/' + language_id + '/active',
                    type: "POST",
                    data: {
                        _token: "{{csrf_token()}}"
                    },
                    success: function (data) {
                        showResponseMessage(data);
                    }
                });
            });


            // On Delete
            Table.delegate(".action-delete", "click", function (e) {
                e.stopPropagation();
                let id = $(this).data('id');
                Swal.fire({
                    title: "{{ __('locale.labels.are_you_sure') }}",
                    text: "{{ __('locale.labels.able_to_revert') }}",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: "{{ __('locale.labels.delete_it') }}",
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-outline-danger ms-1'
                    },
                    buttonsStyling: false,
                }).then(function (result) {
                    if (result.value) {
                        $.ajax({
                            url: "{{ url(config('app.admin_path').'/languages')}}" + '/' + id,
                            type: "POST",
                            data: {
                                _method: 'DELETE',
                                _token: "{{csrf_token()}}"
                            },
                            success: function (data) {
                                showResponseMessage(data);
                            },
                            error: function (reject) {
                                if (reject.status === 422) {
                                    let errors = reject.responseJSON.errors;
                                    $.each(errors, function (key, value) {
                                        toastr['warning'](value[0], "{{__('locale.labels.attention')}}", {
                                            closeButton: true,
                                            positionClass: 'toast-top-right',
                                            progressBar: true,
                                            newestOnTop: true,
                                            rtl: isRtl
                                        });
                                    });
                                } else {
                                    toastr['warning'](reject.responseJSON.message, "{{__('locale.labels.attention')}}", {
                                        positionClass: 'toast-top-right',
                                        containerId: 'toast-top-right',
                                        progressBar: true,
                                        closeButton: true,
                                        newestOnTop: true
                                    });
                                }
                            }
                        })
                    }
                })
            });

        });
    </script>
@endsection
