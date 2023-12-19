/*=========================================================================================
    File Name: data-list-view.js
    Description: List View
==========================================================================================*/
$(document).ready(function () {
    "use strict"
    // init list view datatable

    var dataListView = $(".data-list-view").DataTable({

        "processing": true,
        "columns": [
            {"data": "id", orderable: false, searchable: false},
            {"data": "name"},
            {"data": "type"},
            {"data": "action", orderable: false, searchable: false}
        ],
        responsive: false,
        columnDefs: [
            {
                orderable: false,
                searchable: false,
                targets: 0,
            }
        ],
        dom:
            '<"top"<"actions action-btns"B><"action-filters"lf>><"clear">rt<"bottom"<"actions">p>',
        oLanguage: {
            sLengthMenu: "_MENU_",
            sZeroRecords: "{{ __('locale.datatables.no_results') }}",
            sSearch: "",
            sProcessing: "{{ __('locale.datatables.processing') }}",
            oPaginate: {
                sFirst: "{{ __('locale.datatables.first') }}",
                sPrevious: "{{ __('locale.datatables.previous') }}",
                sNext: "{{ __('locale.datatables.next') }}",
                sLast: "{{ __('locale.datatables.last') }}"
            }
        },
        aLengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],
        order: [[0, "asc"]],
        bInfo: false,
        pageLength: 10,
        buttons: [],
        initComplete: function (settings, json) {
            $(".dt-buttons .btn").removeClass("btn-secondary")
        }
    });

    // To append actions dropdown before add new button
    let actionDropdown = $(".add-new-div")
    actionDropdown.insertBefore($(".top .actions .dt-buttons"))

    // Scrollbar
    if ($(".data-items").length > 0) {
        new PerfectScrollbar(".data-items", {wheelPropagation: false})
    }

});
