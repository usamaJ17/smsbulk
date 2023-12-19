<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerBasedSendingServer;
use App\Models\PlansSendingServer;
use App\Models\SendingServer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeveloperController extends Controller
{

    /**
     * update developer settings
     *
     * @return Application|Factory|View|RedirectResponse
     * @throws AuthorizationException
     */
    public function settings(): View|Factory|RedirectResponse|Application
    {
        $this->authorize('developers');

        $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['name' => __('locale.menu.Developers')],
        ];

        if ( ! Auth::user()->customer->activeSubscription()) {
            return redirect()->route('customer.subscriptions.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.customer.no_active_subscription'),
            ]);
        }

        $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->get();

        return view('customer.Developers.settings', compact('breadcrumbs', 'sendingServers'));
    }

    /**
     * generate new token
     *
     * @return JsonResponse
     */
    public function generate(): JsonResponse
    {

        if (config('app.stage') == 'demo') {
            return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }


        $user = Auth::user();

        $user->tokens()->delete();

        $permissions = json_decode($user->customer->permissions, true);
        $token       = $user->createToken($user->email, $permissions)->plainTextToken;

        $user->update([
                'api_token' => $token,
        ]);


        return response()->json([
                'status'  => 'success',
                'token'   => $token,
                'message' => __('locale.customer.token_successfully_regenerate'),
        ]);
    }


    /**
     * @return Factory|View|Application
     */
    public function docs(): Factory|View|Application
    {
        $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('developers'), 'name' => __('locale.menu.Developers')],
                ['name' => __('locale.developers.api_documents')],
        ];

        return view('customer.Developers.documentation', compact('breadcrumbs'));
    }

    public function sendingServer(Request $request): RedirectResponse
    {

        if (config('app.stage') == 'demo') {
            return redirect()->route('customer.developer.settings')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        if ( ! isset($request->sending_server)) {
            return redirect()->route('customer.developer.settings')->with([
                    'status'  => 'error',
                    'message' => __('locale.campaigns.sending_server_not_available'),
            ]);
        }

        $status = Auth::user()->update([
                'api_sending_server' => $request->sending_server,
        ]);

        if ($status) {
            return redirect()->route('customer.developer.settings')->with([
                    'status'  => 'success',
                    'message' => __('locale.settings.settings_successfully_updated'),
            ]);
        }

        return redirect()->route('customer.developer.settings')->with([
                'status'  => 'error',
                'message' => __('locale.exceptions.something_went_wrong'),
        ]);
    }


    /*Version 3.8*/

    /**
     * @return Factory|View|Application
     */
    public function httpDocs(): Factory|View|Application
    {
        $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('developers'), 'name' => __('locale.menu.Developers')],
                ['name' => __('locale.developers.api_documents')],
        ];

        return view('customer.Developers.http-documentation', compact('breadcrumbs'));
    }
}
