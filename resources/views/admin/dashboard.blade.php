@php use App\Library\Tool;use App\Models\Campaigns;use App\Models\Plan;use App\Models\Reports;use App\Models\User; @endphp
@extends('layouts/contentLayoutMaster')

@section('title', __('locale.menu.Dashboard'))

{{--Vendor Css files--}}
@section('vendor-style')
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/charts/apexcharts.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/extensions/tether-theme-arrows.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/extensions/tether.min.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/extensions/shepherd.min.css')) }}">
@endsection


@section('content')

    <section>
        <div class="row match-height">

            <div class="col-lg-3 col-sm-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2 class="fw-bolder mb-0">{{ User::where('is_customer', 1)->count() }}</h2>
                            <p class="card-text">{{ __('locale.menu.Customers') }}</p>
                        </div>
                        <div class="avatar bg-light-primary p-50 m-0">
                            <div class="avatar-content">
                                <i data-feather="users" class="font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-sm-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2 class="fw-bolder mb-0">{{ Plan::count() }}</h2>
                            <p class="card-text">{{ __('locale.menu.Plan') }}</p>
                        </div>
                        <div class="avatar bg-light-success p-50 m-0">
                            <div class="avatar-content">
                                <i data-feather="credit-card" class="font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-sm-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2 class="fw-bolder mb-0">{{ Reports::count() }}</h2>
                            <p class="card-text">{{ __('locale.labels.sms_send') }}</p>
                        </div>
                        <div class="avatar bg-light-danger p-50 m-0">
                            <div class="avatar-content">
                                <i data-feather="message-square" class="font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-sm-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2 class="fw-bolder mb-0">{{ Campaigns::count() }}</h2>
                            <p class="card-text">{{ __('locale.labels.campaigns_send') }}</p>
                        </div>
                        <div class="avatar bg-light-info p-50 m-0">
                            <div class="avatar-content">
                                <i data-feather="send" class="font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>


        <div class="row">
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-end">
                        <h4 class="card-title text-uppercase">{{ __('locale.labels.customers_growth') }}</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body pb-0">
                            <div id="customer-growth"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-end">
                        <h4 class="card-title text-uppercase">{{ __('locale.labels.sms_reports') }}</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body pb-0">
                            <div id="sms-reports"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-end">
                        <h4 class="card-title text-uppercase">Reseller Stats</h4>
                        <div class="">
                            <select class="form-select" id="stats_filter">
                                <option value="all">{{ __('All') }}</option>
                                <option value="today">{{ __('Today') }}</option>
                                <option value="this_week">{{ __('This Week') }}</option>
                                <option value="this_month">{{ __('This Month') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="card-body pb-0">
                            <div id="sms-reports">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Username') }}</th>
                                            <th>{{ __('New Convesations') }}</th>
                                            <th>{{ __('Stop Count') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody  id="msg-stat-body">
                                        @foreach($msg_stat as $username => $counts)
                                            <tr>
                                                <td>{{ $username }}</td>
                                                <td>{{ $counts[0] }}</td>
                                                <td>{{ $counts[1] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-end">
                        <h4 class="card-title text-uppercase">{{ __('locale.labels.revenue_this_month') }}</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body pb-0">
                            <div id="revenue-chart"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">{{ __('locale.labels.recent_sender_id_requests') }}</h4>
                    </div>
                    <div class="card-content">
                        <div class="table-responsive mt-1">
                            <table class="table table-hover-animation mb-0">
                                <thead>
                                <tr>
                                    <th style="width: 15%">{{ __('locale.labels.sender_id') }}</th>
                                    <th>{{ __('locale.labels.name') }}</th>
                                    <th>{{ __('locale.menu.Customer') }}</th>
                                    <th>{{ __('locale.plans.price') }}</th>
                                    <th>{{ __('locale.plans.validity') }}</th>
                                </tr>
                                </thead>
                                <tbody>

                                @foreach($sender_ids as $senderid)
                                    <tr>
                                        <td><a href="{{ route('admin.senderid.show', $senderid->uid) }}">{{ $senderid->uid }}</a></td>
                                        <td>{{ $senderid->sender_id }}</td>
                                        <td><a href={{route('admin.customers.show', $senderid->user->uid)}}>{{ $senderid->user->displayName() }}</a></td>
                                        <td>{{ Tool::format_price($senderid->price, $senderid->currency->format) }}</td>
                                        <td>{{ $senderid->displayFrequencyTime() }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-end">
                        <h4 class="card-title text-uppercase">{{ __('locale.labels.outgoing_sms_history_of_current_month') }}</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body pb-0">
                            <div id="sms-outbound"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-end">
                        <h4 class="card-title text-uppercase">{{ __('locale.labels.incoming_sms_history_of_current_month') }}</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body pb-0">
                            <div id="sms-inbound"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-end">
                        <h4 class="card-title text-uppercase">{{ __('locale.labels.api_sms_history_of_current_month') }}</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body pb-0">
                            <div id="sms-api"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </section>
@endsection

@section('vendor-script')
    {{--     Vendor js files --}}
    <script src="{{ asset(mix('vendors/js/charts/apexcharts.min.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/extensions/tether.min.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/extensions/shepherd.min.js')) }}"></script>
@endsection


@section('page-script')
    <!-- Page js files -->


    <script>


        $(window).on("load", function () {

            let $primary = '#7367F0';
            let $strok_color = '#b9c3cd';
            let $label_color = '#e7eef7';
            let $purple = '#df87f2';

            // outbound sms
            // -----------------------------

            let smsOutboundOptions = {
                chart: {
                    height: 270,
                    toolbar: {show: false},
                    type: 'line',
                    dropShadow: {
                        enabled: true,
                        top: 20,
                        left: 2,
                        blur: 6,
                        opacity: 0.20
                    },
                },
                stroke: {
                    curve: 'smooth',
                    width: 4,
                },
                grid: {
                    borderColor: $label_color,
                },
                legend: {
                    show: false,
                },
                colors: [$purple],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'dark',
                        inverseColors: false,
                        gradientToColors: [$primary],
                        shadeIntensity: 1,
                        type: 'horizontal',
                        opacityFrom: 1,
                        opacityTo: 1,
                        stops: [0, 100, 100, 100]
                    },
                },
                markers: {
                    size: 0,
                    hover: {
                        size: 5
                    }
                },
                xaxis: {
                    labels: {
                        style: {
                            colors: $strok_color,
                        }
                    },
                    axisTicks: {
                        show: false,
                    },
                    categories: {!! $outgoing->xAxis() !!},
                    axisBorder: {
                        show: false,
                    },
                    tickPlacement: 'on',
                    type: 'string'
                },
                yaxis: {
                    tickAmount: 5,
                    labels: {
                        style: {
                            color: $strok_color,
                        },
                        formatter: function (val) {
                            return val > 999 ? (val / 1000).toFixed(1) + 'k' : val.toFixed(1);
                        }
                    }
                },
                tooltip: {
                    x: {show: false}
                },
                series: {!! $outgoing->dataSet() !!}

            }

            let smsOutbound = new ApexCharts(
                document.querySelector("#sms-outbound"),
                smsOutboundOptions
            );

            smsOutbound.render();


            // inbound sms
            // -----------------------------

            let smsInboundOptions = {
                chart: {
                    height: 270,
                    toolbar: {show: false},
                    type: 'line',
                    dropShadow: {
                        enabled: true,
                        top: 20,
                        left: 2,
                        blur: 6,
                        opacity: 0.20
                    },
                },
                stroke: {
                    curve: 'smooth',
                    width: 4,
                },
                grid: {
                    borderColor: $label_color,
                },
                legend: {
                    show: false,
                },
                colors: [$purple],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'dark',
                        inverseColors: false,
                        gradientToColors: [$primary],
                        shadeIntensity: 1,
                        type: 'horizontal',
                        opacityFrom: 1,
                        opacityTo: 1,
                        stops: [0, 100, 100, 100]
                    },
                },
                markers: {
                    size: 0,
                    hover: {
                        size: 5
                    }
                },
                xaxis: {
                    labels: {
                        style: {
                            colors: $strok_color,
                        }
                    },
                    axisTicks: {
                        show: false,
                    },
                    categories: {!! $incoming->xAxis() !!},
                    axisBorder: {
                        show: false,
                    },
                    tickPlacement: 'on',
                    type: 'string'
                },
                yaxis: {
                    tickAmount: 5,
                    labels: {
                        style: {
                            color: $strok_color,
                        },
                        formatter: function (val) {
                            return val > 999 ? (val / 1000).toFixed(1) + 'k' : val.toFixed(1);
                        }
                    }
                },
                tooltip: {
                    x: {show: false}
                },
                series: {!! $incoming->dataSet() !!}

            }

            let smsInbound = new ApexCharts(
                document.querySelector("#sms-inbound"),
                smsInboundOptions
            );

            smsInbound.render();

            // API sms
            // -----------------------------

            let smsAPIOptions = {
                chart: {
                    height: 270,
                    toolbar: {show: false},
                    type: 'line',
                    dropShadow: {
                        enabled: true,
                        top: 20,
                        left: 2,
                        blur: 6,
                        opacity: 0.20
                    },
                },
                stroke: {
                    curve: 'smooth',
                    width: 4,
                },
                grid: {
                    borderColor: $label_color,
                },
                legend: {
                    show: false,
                },
                colors: [$purple],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'dark',
                        inverseColors: false,
                        gradientToColors: [$primary],
                        shadeIntensity: 1,
                        type: 'horizontal',
                        opacityFrom: 1,
                        opacityTo: 1,
                        stops: [0, 100, 100, 100]
                    },
                },
                markers: {
                    size: 0,
                    hover: {
                        size: 5
                    }
                },
                xaxis: {
                    labels: {
                        style: {
                            colors: $strok_color,
                        }
                    },
                    axisTicks: {
                        show: false,
                    },
                    categories: {!! $api->xAxis() !!},
                    axisBorder: {
                        show: false,
                    },
                    tickPlacement: 'on',
                    type: 'string'
                },
                yaxis: {
                    tickAmount: 5,
                    labels: {
                        style: {
                            color: $strok_color,
                        },
                        formatter: function (val) {
                            return val > 999 ? (val / 1000).toFixed(1) + 'k' : val.toFixed(1);
                        }
                    }
                },
                tooltip: {
                    x: {show: false}
                },
                series: {!! $api->dataSet() !!}

            }

            let smsAPI = new ApexCharts(
                document.querySelector("#sms-api"),
                smsAPIOptions
            );

            smsAPI.render();


            // revenue chart
            // -----------------------------

            let revenueChartOptions = {
                chart: {
                    height: 270,
                    toolbar: {show: false},
                    type: 'line',
                    dropShadow: {
                        enabled: true,
                        top: 20,
                        left: 2,
                        blur: 6,
                        opacity: 0.20
                    },
                },
                stroke: {
                    curve: 'smooth',
                    width: 4,
                },
                grid: {
                    borderColor: $label_color,
                },
                legend: {
                    show: false,
                },
                colors: [$purple],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'dark',
                        inverseColors: false,
                        gradientToColors: [$primary],
                        shadeIntensity: 1,
                        type: 'horizontal',
                        opacityFrom: 1,
                        opacityTo: 1,
                        stops: [0, 100, 100, 100]
                    },
                },
                markers: {
                    size: 0,
                    hover: {
                        size: 5
                    }
                },
                xaxis: {
                    labels: {
                        style: {
                            colors: $strok_color,
                        }
                    },
                    axisTicks: {
                        show: false,
                    },
                    categories: {!! $incoming->xAxis() !!},
                    axisBorder: {
                        show: false,
                    },
                    tickPlacement: 'on',
                    type: 'string'
                },
                yaxis: {
                    tickAmount: 5,
                    labels: {
                        style: {
                            color: $strok_color,
                        },
                        formatter: function (val) {
                            return val > 999 ? (val / 1000).toFixed(1) + 'k' : val.toFixed(1);
                        }
                    }
                },
                tooltip: {
                    x: {show: false}
                },
                series: {!! $revenue_chart->dataSet() !!}

            }

            let revenueChart = new ApexCharts(
                document.querySelector("#revenue-chart"),
                revenueChartOptions
            );

            revenueChart.render();

        });


        // Client growth Chart
        // ----------------------------------

        let clientGrowthChartoptions = {
            chart: {
                stacked: true,
                type: 'bar',
                toolbar: {show: false},
                height: 290,
            },
            plotOptions: {
                bar: {
                    columnWidth: '70%'
                }
            },
            colors: ['#7367F0'],
            series: {!! $customer_growth->dataSet() !!},
            grid: {
                borderColor: '#e7eef7',
                padding: {
                    left: 0,
                    right: 0
                }
            },
            legend: {
                show: true,
                position: 'top',
                horizontalAlign: 'left',
                offsetX: 0,
                fontSize: '14px',
                markers: {
                    radius: 50,
                    width: 10,
                    height: 10,
                }
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                labels: {
                    style: {
                        colors: '#b9c3cd',
                    }
                },
                axisTicks: {
                    show: false,
                },
                categories: {!! $customer_growth->xAxis() !!},
                axisBorder: {
                    show: false,
                },
            },
            yaxis: {
                tickAmount: 5,
                labels: {
                    style: {
                        color: '#b9c3cd',
                    },
                    formatter: function (val) {
                        return val.toFixed(1)
                    }
                }
            },
            tooltip: {
                x: {show: false}
            },
        }

        let clientGrowthChart = new ApexCharts(
            document.querySelector("#customer-growth"),
            clientGrowthChartoptions
        );

        clientGrowthChart.render();


        // sms history Chart
        // -----------------------------

        let smsHistoryChartoptions = {
            chart: {
                type: 'pie',
                height: 325,
                dropShadow: {
                    enabled: false,
                    blur: 5,
                    left: 1,
                    top: 1,
                    opacity: 0.2
                },
                toolbar: {
                    show: false
                }
            },
            labels: ["{{ __('locale.labels.delivered') }}", "{{ __('locale.labels.failed') }}"],
            series: {!! $sms_history->dataSet() !!},
            dataLabels: {
                enabled: false
            },
            legend: {show: false},
            stroke: {
                width: 5
            },
            colors: ['#7367F0', '#EA5455'],
            fill: {
                type: 'gradient',
                gradient: {
                    gradientToColors: ['#9c8cfc', '#f29292']
                }
            }
        }

        let smsHistoryChart = new ApexCharts(
            document.querySelector("#sms-reports"),
            smsHistoryChartoptions
        );

        smsHistoryChart.render();
        
        
        $('#stats_filter').on('change', function () {
            // Send AJAX request to 'stats_time' route
            $.ajax({
                url: '{{ route('admin.stats_time') }}',
                type: 'GET',
                data: { period: $(this).val() },
                dataType: 'json',
                success: function (data) {
                    // Update the counts in the table
                    $('#msg-stat-body').html('');
                    $.each(data, function (username, counts) {
                        $('#msg-stat-body').append('<tr><td>' + username + '</td><td>' + counts[0] + '</td><td>' + counts[1] + '</td></tr>');
                    });
                },
                error: function (error) {
                    console.log(error);
                }
            });
        });


    </script>
@endsection

