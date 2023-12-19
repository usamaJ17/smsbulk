<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\DashboardRequest;
use App\Library\Tool;
use App\Models\Campaigns;
use App\Models\Reports;
use App\Models\SendingServer;
use App\Models\User;
use ArielMejiaDev\LarapexCharts\LarapexChart;
use Exception;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\NoReturn;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportsController extends Controller
{

    /**
     * sms reports
     *
     * @return Application|Factory|View
     * @throws AuthorizationException
     */
    public function reports(): Factory|View|Application
    {
        $this->authorize('view sms_history');


        $breadcrumbs = [
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Reports')],
                ['name' => __('locale.menu.SMS History')],
        ];

        $customers      = User::select('id', 'first_name', 'last_name')->get();
        $sendingServers = SendingServer::where('status', true)->select('id', 'name')->get();

        return view('admin.Reports.all_messages', compact('breadcrumbs', 'customers', 'sendingServers'));
    }

    /**
     * get all message reports
     *
     * @param  Request  $request
     *
     * @throws AuthorizationException
     */
    #[NoReturn] public function searchAllMessages(Request $request)
    {
        $this->authorize('view sms_history');
        $columns = [
                1  => 'uid',
                2  => 'created_at',
                3  => 'send_by',
                4  => 'sms_type',
                5  => 'from',
                6  => 'to',
                7  => 'sms_count',
                8  => 'cost',
                9  => 'status',
                10 => 'sending_server_id',
                11 => 'user_id',
        ];

        $limit            = $request->input('length');
        $start            = $request->input('start');
        $orderColumnIndex = $request->input('order.0.column');
        $orderColumnName  = $columns[$orderColumnIndex];
        $orderDirection   = $request->input('order.0.dir');

        $totalData = Reports::count();

        $reports = Reports::with(['user', 'sendingServer'])
                          ->filterByUser($request->input('user_id'))
                          ->filterBySendingServer($request->input('sending_server_id'))
                          ->filterByDirection($request->input('direction'))
                          ->filterByType($request->input('type'))
                          ->filterByStatus($request->input('status'))
                          ->filterByFrom($request->input('from'))
                          ->filterByTo($request->input('to'))
                          ->filterByInputDateRange($request->input('dateRange'));

        $totalFiltered = $reports->count();

        $sms_reports = $reports
                ->orderBy($orderColumnName, $orderDirection)
                ->limit($limit)
                ->offset($start)
                ->get();


        $data = [];
        if ( ! empty($sms_reports)) {
            foreach ($sms_reports as $report) {
                if ($report->created_at == null) {
                    $created_at = null;
                } else {
                    $created_at = Tool::customerDateTime($report->created_at);
                }

                $customer_profile = route('admin.customers.show', $report->user->uid);
                $customer_name    = $report->user->displayName();
                $user_id          = "<a href='$customer_profile' class='text-primary mr-1'>$customer_name</a>";

                if (isset($report->sendingServer)) {
                    $sending_server_url  = route('admin.sending-servers.show', $report->sendingServer->uid);
                    $sending_server_name = $report->sendingServer->name;
                } else {
                    $sending_server_url  = '#';
                    $sending_server_name = __('locale.sending_servers.sending_server_not_found');
                }

                $sending_server = "<a href='$sending_server_url' class='text-primary mr-1'>$sending_server_name</a>";

                $nestedData['responsive_id']     = '';
                $nestedData['uid']               = $report->uid;
                $nestedData['avatar']            = route('admin.customers.avatar', $report->user->uid);
                $nestedData['email']             = $report->user->email;
                $nestedData['created_at']        = $created_at;
                $nestedData['user_id']           = $user_id;
                $nestedData['send_by']           = $report->getSendBy();
                $nestedData['sms_type']          = $report->getSMSType();
                $nestedData['from']              = $report->from;
                $nestedData['to']                = $report->to;
                $nestedData['cost']              = $report->cost;
                $nestedData['sms_count']         = $report->sms_count;
                $nestedData['status']            = str_limit($report->status, 20);
                $nestedData['sending_server_id'] = $sending_server;
                $data[]                          = $nestedData;

            }
        }

        $json_data = [
                "draw"            => intval($request->input('draw')),
                "recordsTotal"    => $totalData,
                "recordsFiltered" => $totalFiltered,
                "data"            => $data,
        ];

        echo json_encode($json_data);
        exit();

    }

    /**
     * view single reports
     *
     * @param  Reports  $uid
     *
     * @return JsonResponse
     */
    public function viewReports(Reports $uid): JsonResponse
    {
        return response()->json([
                'status' => 'success',
                'data'   => $uid,
        ]);

    }

    /**
     * @param  Reports  $uid
     *
     * @return JsonResponse
     * @throws Exception|Exception
     */

    public function destroy(Reports $uid): JsonResponse
    {

        if (config('app.stage') == 'demo') {
            return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        if ( ! $uid->delete()) {
            return response()->json([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
            ]);
        }

        return response()->json([
                'status'  => 'success',
                'message' => __('locale.campaigns.sms_was_successfully_deleted'),
        ]);

    }

    /**
     * bulk sms delete
     *
     * @param  Request  $request
     *
     * @return JsonResponse
     */
    public function batchAction(Request $request): JsonResponse
    {

        if (config('app.stage') == 'demo') {
            return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $ids = $request->get('ids');

        if (Reports::whereIn('uid', $ids)->delete()) {
            return response()->json([
                    'status'  => 'success',
                    'message' => __('locale.campaigns.sms_was_successfully_deleted'),
            ]);
        }

        return response()->json([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
        ]);
    }

    /**
     * @param $request
     *
     * @return Generator
     */
    public function exportData($request): Generator
    {

        $reports = Reports::with(['user', 'sendingServer'])
                          ->filterByUser($request->input('user_id'))
                          ->filterBySendingServer($request->input('sending_server'))
                          ->filterByDirection($request->input('direction'))
                          ->filterByType($request->input('type'))
                          ->filterByStatus($request->input('status'))
                          ->filterByFrom($request->input('from'))
                          ->filterByTo($request->input('to'))
                          ->filterByDateRange($request->input('start_date'), $request->input('start_time'), $request->input('end_date'), $request->input('end_time'))
                          ->get();


        yield from $reports->map(function ($report) {

            return [
                    'created_at'     => Tool::customerDateTime($report->created_at),
                    'from'           => $report->from,
                    'to'             => $report->to,
                    'message'        => $report->message,
                    'cost'           => $report->cost,
                    'sms_count'      => $report->sms_count,
                    'username'       => $report->user->displayName(),
                    'company'        => $report->user->customer->company,
                    'email'          => $report->user->email,
                    'sending_server' => $report->sendingServer->name,
                    'media_url'      => $report->media_url,
                    'sms_type'       => $report->sms_type,
                    'status'         => $report->status,
                    'direction'      => $report->send_by,
            ];
        });

    }

    /**
     * @param  Request  $request
     *
     * @return RedirectResponse|BinaryFileResponse
     * @throws AuthorizationException
     */
    public function export(Request $request): BinaryFileResponse|RedirectResponse
    {

        if (config('app.stage') == 'demo') {
            return redirect()->route('admin.reports.index')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $this->authorize('view sms_history');

        Tool::resetMaxExecutionTime();

        try {
            $file_name = (new FastExcel($this->exportData($request)))->export(storage_path('Reports_'.time().'.xlsx'));

            return response()->download($file_name);

        } catch (IOException|InvalidArgumentException|UnsupportedTypeException|WriterNotOpenedException $e) {
            return redirect()->route('admin.reports.index')->with([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
            ]);
        }

    }

    /*
    |--------------------------------------------------------------------------
    | Version 3.7
    |--------------------------------------------------------------------------
    |
    | Reports Dashboard, Campaigns, Make more readable reports module
    |
    */

    /**
     * Reports Dashboard
     *
     * @return Application|Factory|View|\Illuminate\Foundation\Application
     * @throws AuthorizationException
     */
    public function dashboard()
    {
        $this->authorize('view sms_history');

        $breadcrumbs = [
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Reports')],
                ['name' => __('locale.menu.Dashboard')],
        ];

        $customers = User::select('id', 'first_name', 'last_name')->get();

        $reportQuery = Reports::select(
                'sms_type',
                DB::raw('SUM(CASE WHEN status LIKE "%Delivered%" THEN cost ELSE 0 END) as total_cost'),
                DB::raw('SUM(sms_count) as total_sms'),
                DB::raw('SUM(CASE WHEN status LIKE "%Delivered%" THEN 1 ELSE 0 END) as delivered_sms'),
                DB::raw('SUM(CASE WHEN status NOT LIKE "%Delivered%" THEN 1 ELSE 0 END) as not_delivered_sms')
        )->groupBy('sms_type')->whereDate('created_at', today());

        $reports = $reportQuery->get();

        $smsTypes = $reportQuery->pluck('sms_type')->unique();

        $chart = (new LarapexChart)->areaChart();

        foreach ($smsTypes as $smsType) {
            $data = $reports->where('sms_type', $smsType)->pluck('total_sms');
            $chart->addData(ucfirst($smsType), $data->toArray());
        }

        $chart->setXAxis([today()->format('j/n')]);

        return view('admin.Reports.dashboard', compact('breadcrumbs', 'customers', 'reports', 'chart'));
    }

    public function parseDates(string $dateRange): array
    {
        $dates     = array_map('trim', explode(' to ', $dateRange));
        $startDate = date('Y-m-d', strtotime($dates[0]));
        $endDate   = isset($dates[1]) ? date('Y-m-d', strtotime($dates[1])) : $startDate;

        return [$startDate, $endDate];
    }

    public function getReportsData(string $type, array $dates, int $user_id)
    {
        [$startDate, $endDate] = $dates;

        return Reports::select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(sms_count) as total_sms'))
                      ->where('sms_type', $type)
                      ->whereBetween('created_at', [$startDate, $endDate])
                      ->when($user_id, function ($query, $user_id) {
                          $query->where('user_id', $user_id);
                      })
                      ->groupBy('date')
                      ->get();
    }

    public function postDashboard(DashboardRequest $request)
    {
        $breadcrumbs = [
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Reports')],
                ['name' => __('locale.menu.Dashboard')],
        ];

        $dateRange = $request->input('dateRange');
        if ( ! $dateRange) {
            return back()->withInput('dateRange')->with([
                    'status'  => 'error',
                    'message' => 'Please select a valid date',
            ]);
        }

        $customers = User::select('id', 'first_name', 'last_name')->get();

        $dates   = $this->parseDates($dateRange);
        $user_id = $request->input('user_id');
        $reports = Reports::select('sms_type',
                DB::raw('SUM(CASE WHEN status LIKE "%Delivered%" THEN cost ELSE 0 END) as total_cost'),
                DB::raw('COUNT(sms_count) as total_sms'),
                DB::raw('SUM(CASE WHEN status LIKE "%Delivered%" THEN 1 ELSE 0 END) as delivered_sms'),
                DB::raw('SUM(CASE WHEN status NOT LIKE "%Delivered%" THEN 1 ELSE 0 END) as not_delivered_sms'))
                          ->whereBetween('created_at', $dates)
                          ->when($user_id, function ($query, $user_id) {
                              $query->where('user_id', $user_id);
                          })
                          ->groupBy('sms_type')
                          ->get();

        $getData  = [];
        $smsTypes = ['plain', 'voice', 'mms', 'whatsapp', 'unicode'];
        array_map(function ($type) use ($dates, $user_id, &$getData) {
            $getData[$type] = $this->getReportsData($type, $dates, $user_id);

            return $getData;
        }, $smsTypes);

        $chart = (new LarapexChart)->areaChart();
        $chart->setTitle('SMS Cost by Type');
        $chart->setXAxis($getData['plain']->pluck('date')->toArray());
        $chart->setLabels($smsTypes);

        foreach ($smsTypes as $type) {
            $chart->addData(ucfirst($type), $getData[$type]->pluck('total_sms')->toArray());
        }

        return view('admin.Reports.dashboard', compact('breadcrumbs', 'reports', 'request', 'chart', 'customers'));
    }

    /**
     * Campaigns
     *
     * @return Application|Factory|View|\Illuminate\Foundation\Application
     * @throws AuthorizationException
     */
    public function campaigns()
    {

        $this->authorize('view sms_history');

        $breadcrumbs = [
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Reports')],
                ['name' => __('locale.menu.Campaigns')],
        ];


        return view('admin.Reports.campaigns', compact('breadcrumbs'));

    }

    /**
     * search campaign data
     *
     * @param  Request  $request
     *
     * @throws AuthorizationException
     */
    #[NoReturn] public function searchCampaigns(Request $request)
    {

        $columns = [
                0 => 'responsive_id',
                1 => 'uid',
                2 => 'user_id',
                3 => 'campaign_name',
                4 => 'contacts',
                5 => 'sms_type',
                6 => 'schedule_type',
                7 => 'status',
        ];

        $totalData = Campaigns::count();

        $totalFiltered = $totalData;

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir   = $request->input('order.0.dir');

        if (empty($request->input('search.value'))) {
            $campaigns = Campaigns::offset($start)
                                  ->limit($limit)
                                  ->orderBy($order, $dir)
                                  ->get();
        } else {
            $search = $request->input('search.value');

            $campaigns = Campaigns::whereLike(['uid', 'campaign_name', 'sms_type', 'schedule_type', 'created_at', 'status', 'user.first_name', 'user.last_name', 'user.email'], $search)
                                  ->offset($start)
                                  ->limit($limit)
                                  ->orderBy($order, $dir)
                                  ->get();

            $totalFiltered = Campaigns::whereLike(['uid', 'campaign_name', 'sms_type', 'schedule_type', 'created_at', 'status', 'user.first_name', 'user.last_name', 'user.email'], $search)->count();
        }


        $data = [];
        if ( ! empty($campaigns)) {
            foreach ($campaigns as $campaign) {

                $customer_profile = route('admin.customers.show', $campaign->user->uid);
                $customer_name    = $campaign->user->displayName();
                $user_id          = "<a href='$customer_profile' class='text-primary mr-1'>$customer_name</a>";


                $nestedData['responsive_id'] = '';
                $nestedData['uid']           = $campaign->uid;
                $nestedData['campaign_name'] = "<div>
                                                        <p class='text-bold-600'> $campaign->campaign_name </p>
                                                        <p class='text-muted'>".__('locale.labels.created_at').': '.Tool::formatHumanTime($campaign->created_at)."</p>
                                                   </div>";

                $nestedData['avatar']        = route('admin.customers.avatar', $campaign->user->uid);
                $nestedData['email']         = $campaign->user->email;
                $nestedData['user_id']       = $user_id;
                $nestedData['contacts']      = Tool::number_with_delimiter($campaign->contactCount($campaign->cache));
                $nestedData['sms_type']      = $campaign->getSMSType();
                $nestedData['schedule_type'] = $campaign->getCampaignType();
                $nestedData['status']        = $campaign->getStatus();
                $nestedData['camp_status']   = str_limit($campaign->status, 30);
                $nestedData['camp_name']     = $campaign->campaign_name;

                $nestedData['overview']       = route('admin.reports.campaign.overview', $campaign->uid);
                $nestedData['overview_label'] = __('locale.menu.Overview');

                $nestedData['delete'] = __('locale.buttons.delete');
                $data[]               = $nestedData;

            }
        }

        $json_data = [
                "draw"            => intval($request->input('draw')),
                "recordsTotal"    => $totalData,
                "recordsFiltered" => $totalFiltered,
                "data"            => $data,
        ];

        echo json_encode($json_data);
        exit();

    }


    public function campaignOverview(Campaigns $campaign): Factory|\Illuminate\Foundation\Application|View|Application|RedirectResponse
    {
        $this->authorize('view sms_history');

        $breadcrumbs = [
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path')."/reports/campaigns"), 'name' => __('locale.menu.Reports')],
                ['name' => __('locale.menu.Campaigns')],
        ];


        $campaign = Campaigns::where('uid', $campaign->uid)->first();


        if ( ! $campaign) {
            return redirect()->route('admin.reports.campaigns')->with([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.invalid_action'),
            ]);
        }


        return view('admin.Reports.overview', compact('campaign', 'breadcrumbs'));
    }


    /**
     * view campaign reports
     *
     * @param  Campaigns  $campaign
     * @param  Request  $request
     *
     * @throws AuthorizationException
     */
    #[NoReturn] public function campaignReports(Campaigns $campaign, Request $request)
    {

        $columns = [
                0 => 'responsive_id',
                1 => 'uid',
                2 => 'uid',
                3 => 'created_at',
                6 => 'from',
                7 => 'to',
                8 => 'cost',
                9 => 'status',
        ];

        $totalData = Reports::where('campaign_id', $campaign->id)->count();

        $totalFiltered = $totalData;

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir   = $request->input('order.0.dir');

        if (empty($request->input('search.value'))) {
            $sms_reports = Reports::where('campaign_id', $campaign->id)->offset($start)
                                  ->limit($limit)
                                  ->orderBy($order, $dir)
                                  ->get();
        } else {
            $search = $request->input('search.value');

            $sms_reports = Reports::where('campaign_id', $campaign->id)->whereLike(['uid', 'from', 'to', 'cost', 'status', 'created_at'], $search)
                                  ->offset($start)
                                  ->limit($limit)
                                  ->orderBy($order, $dir)
                                  ->get();

            $totalFiltered = Reports::where('campaign_id', $campaign->id)->whereLike(['uid', 'from', 'to', 'cost', 'status', 'created_at'], $search)->count();
        }

        $data = [];
        if ( ! empty($sms_reports)) {

            foreach ($sms_reports as $report) {
                if ($report->created_at == null) {
                    $created_at = null;
                } else {
                    $created_at = Tool::customerDateTime($report->created_at);
                }

                $nestedData['responsive_id'] = '';
                $nestedData['uid']           = $report->uid;
                $nestedData['created_at']    = $created_at;
                $nestedData['from']          = $report->from;
                $nestedData['to']            = $report->to;
                $nestedData['cost']          = $report->cost;
                $nestedData['status']        = str_limit($report->status, 20);
                $data[]                      = $nestedData;

            }
        }

        $json_data = [
                "draw"            => intval($request->input('draw')),
                "recordsTotal"    => $totalData,
                "recordsFiltered" => $totalFiltered,
                "data"            => $data,
        ];

        echo json_encode($json_data);
        exit();
    }


    /**
     * delete campaign
     *
     * @param  Campaigns  $campaign
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function campaignDelete(Campaigns $campaign): JsonResponse
    {
        if (config('app.stage') == 'demo') {

            return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        if ( ! $campaign->delete()) {
            return response()->json([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
            ]);
        }

        return response()->json([
                'status'  => 'success',
                'message' => __('locale.campaigns.campaign_was_successfully_deleted'),
        ]);

    }


    /**
     * bulk campaign delete
     *
     * @param  Request  $request
     *
     * @return JsonResponse
     */
    public function campaignBatchAction(Request $request): JsonResponse
    {

        if (config('app.stage') == 'demo') {

            return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }


        $ids = $request->get('ids');

        if (Campaigns::whereIn('uid', $ids)->delete()) {
            return response()->json([
                    'status'  => 'success',
                    'message' => __('locale.campaigns.campaign_was_successfully_deleted'),
            ]);
        }

        return response()->json([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
        ]);
    }


    /**
     * @param $campaign_id
     *
     * @return Generator
     */

    public function campaignReportsGenerator($campaign_id): Generator
    {
        $reports = Reports::where('campaign_id', $campaign_id)->get();

        yield from $reports->map(function ($report) {

            return [
                    'created_at' => Tool::customerDateTime($report->created_at),
                    'from'       => $report->from,
                    'to'         => $report->to,
                    'message'    => $report->message,
                    'cost'       => $report->cost,
                    'media_url'  => $report->media_url,
                    'sms_type'   => $report->sms_type,
                    'status'     => $report->status,
                    'direction'  => $report->send_by,
            ];
        });
    }


    /**
     * @param  Campaigns  $campaign
     *
     * @return RedirectResponse|BinaryFileResponse
     * @throws AuthorizationException
     */
    public function exportCampaign(Campaigns $campaign): BinaryFileResponse|RedirectResponse
    {
        if (config('app.stage') == 'demo') {
            return redirect()->route('admin.reports.all')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $this->authorize('view sms_history');

        try {
            $file_name = (new FastExcel($this->campaignReportsGenerator($campaign->id)))->export(storage_path('Reports_'.time().'.xlsx'));

            return response()->download($file_name);
        } catch (IOException|InvalidArgumentException|UnsupportedTypeException|WriterNotOpenedException $e) {
            return redirect()->route('admin.reports.all')->with([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
            ]);
        }

    }


    /**
     * @return Generator
     */

    public function campaignGenerator(): Generator
    {
        foreach (Campaigns::all() as $report) {
            yield $report;
        }
    }

    /**
     * @return RedirectResponse|BinaryFileResponse
     * @throws AuthorizationException
     */
    public function campaignExport(): BinaryFileResponse|RedirectResponse
    {
        if (config('app.stage') == 'demo') {
            return redirect()->route('admin.reports.all')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $this->authorize('view sms_history');

        try {
            $file_name = (new FastExcel($this->campaignGenerator()))->export(storage_path('Campaign_'.time().'.xlsx'));

            return response()->download($file_name);
        } catch (IOException|InvalidArgumentException|UnsupportedTypeException|WriterNotOpenedException $e) {
            return redirect()->route('admin.reports.all')->with([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
            ]);
        }

    }

}
