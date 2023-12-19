<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Models\Invoices;
use App\Models\User;
use App\Models\Reports;
use App\Models\ChatBox;
use App\Models\ChatBoxMessage;
use App\Models\Senderid;
use ArielMejiaDev\LarapexCharts\LarapexChart;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Carbon\Carbon;

class AdminBaseController extends Controller
{
    /**
     * Show admin home.
     *
     * @return Application|Factory|\Illuminate\Contracts\View\View|View
     */
    public function index()
    {
        $breadcrumbs = [
                ['link' => "/dashboard", 'name' => __('locale.menu.Dashboard')],
                ['name' => Auth::user()->displayName()],
        ];

        $sms_outgoing = Reports::currentMonth()
                ->selectRaw('Day(created_at) as day, count(send_by) as outgoing,send_by')
                ->where('send_by', "from")
                ->groupBy('day')->pluck('day', 'outgoing')->flip()->sortKeys();
        $users = User::where('admin_id', auth()->user()->id)->get();
        $msg_stat = [];
        
        foreach ($users as $user) {
            $ids = [$user->id];
            $res_c = User::where('admin_id', $user->id)->pluck('id')->toArray();
            $mergedArray = array_merge($ids, $res_c);
            $cb = ChatBox::whereIn('user_id', $mergedArray)->pluck('id')->toArray();
            $cb_count = count($cb);
            
            $stop_count = ChatBoxMessage::whereIn('box_id', $cb)
                ->where(function ($query) {
                    $query->where('message', 'Stop')
                          ->orWhere('message', 'STOP')
                          ->orWhere('message', 'stop');
                })->count();
            $u_n = $user->first_name .' '. $user->last_name;
            $msg_stat[$u_n] = [$cb_count, $stop_count];
        }
        $sms_outgoing = Reports::currentMonth()
                ->selectRaw('Day(created_at) as day, count(send_by) as outgoing,send_by')
                ->where('send_by', "from")
                ->groupBy('day')->pluck('day', 'outgoing')->flip()->sortKeys();

        $sms_incoming = Reports::currentMonth()
                ->selectRaw('Day(created_at) as day, count(send_by) as incoming,send_by')
                ->where('send_by', "to")
                ->groupBy('day')->pluck('day', 'incoming')->flip()->sortKeys();

        $sms_api = Reports::currentMonth()
                ->selectRaw('Day(created_at) as day, count(send_by) as api,send_by')
                ->where('send_by', "api")
                ->groupBy('day')->pluck('day', 'api')->flip()->sortKeys();


        $outgoing = (new LarapexChart)->lineChart()
                ->addData(__('locale.labels.outgoing'), $sms_outgoing->values()->toArray())
                ->setXAxis($sms_outgoing->keys()->toArray());


        $incoming = (new LarapexChart)->lineChart()
                ->addData(__('locale.labels.incoming'), $sms_incoming->values()->toArray())
                ->setXAxis($sms_incoming->keys()->toArray());


        $api = (new LarapexChart)->lineChart()
                ->addData(__('locale.labels.api'), $sms_api->values()->toArray())
                ->setXAxis($sms_api->keys()->toArray());


        $revenue = Invoices::CurrentMonth()
                ->selectRaw('Day(created_at) as day, sum(amount) as revenue')
                ->groupBy('day')
                ->pluck('revenue', 'day');

        $revenue_chart = (new LarapexChart)->lineChart()
                ->addData(__('locale.labels.revenue'), $revenue->values()->toArray())
                ->setXAxis($revenue->keys()->toArray());

        $customers = Customer::thisYear()
                ->selectRaw('DATE_FORMAT(created_at, "%m-%Y") as month, count(uid) as customer')
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('customer', 'month');


        $customer_growth = (new LarapexChart)->barChart()
                ->addData(__('locale.labels.customers_growth'), $customers->values()->toArray())
                ->setXAxis($customers->keys()->toArray());

        $sms_history = (new LarapexChart)->pieChart()
                ->addData([
                        Reports::where('status', 'like', "%Delivered%")->count(),
                        Reports::where('status', 'not like', "%Delivered%")->count(),
                ]);

        $sender_ids = Senderid::where('status', 'pending')->latest()->take(10)->cursor();

        return view('admin.dashboard', compact('breadcrumbs', 'sms_incoming','msg_stat', 'sms_outgoing', 'outgoing', 'incoming', 'revenue_chart', 'customer_growth', 'sms_history','sender_ids','sms_api', 'api'));
    }


    public function stats_time(Request $request)
    {
        // Get the selected period from the request
        $period = $request->input('period');
    
        // Initialize $startDate and $endDate based on the selected period
        $endDate = Carbon::now();
        switch ($period) {
            case 'today':
                $startDate = Carbon::today();
                break;
            case 'this_week':
                $startDate = Carbon::now()->startOfWeek();
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                break;
            default:
                // 'all' or any unsupported value
                $startDate = null;
                break;
        }
    
       $users = User::where('admin_id', auth()->user()->id)->get();
    
        $msg_stat = [];
        foreach ($users as $user) {
            $ids = [$user->id];
            $res_c = User::where('admin_id', $user->id)->pluck('id')->toArray();
            $mergedArray = array_merge($ids, $res_c);
            $cb = ChatBox::whereIn('user_id', $mergedArray);
            if ($startDate && $endDate) {
                $cb->whereBetween('created_at', [$startDate, $endDate]);
            }
            $cb = $cb->pluck('id')->toArray();
            $cb_count = count($cb);
    
            $stop_count = ChatBoxMessage::whereIn('box_id', $cb)
                ->where(function ($query) {
                    $query->where('message', 'Stop')
                        ->orWhere('message', 'STOP')
                        ->orWhere('message', 'stop');
                });
    
            // Apply date filtering
            if ($startDate && $endDate) {
                $stop_count->whereBetween('created_at', [$startDate, $endDate]);
            }
    
            $stop_count = $stop_count->count();
    
            $u_n = $user->first_name .' '. $user->last_name;
            $msg_stat[$u_n] = [$cb_count, $stop_count];
        }
    
        // Return the data in JSON format
        return response()->json($msg_stat);
    }

    /**
     * @param  Request  $request
     * @param $message
     * @param  string  $type
     *
     * @return JsonResponse|RedirectResponse
     */
    protected function redirectResponse(Request $request, $message, string $type = 'success'): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                    'status'  => $type,
                    'message' => $message,
            ]);
        }

        return redirect()->back()->with("flash_{$type}", $message);
    }

}
