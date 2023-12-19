<?php

    namespace App\Http\Controllers\Admin;

    use App\Exceptions\GeneralException;
    use App\Http\Requests\Customer\AddSendingServerRequest;
    use App\Http\Requests\Customer\AddUnitRequest;
    use App\Http\Requests\Customer\DeleteSendingServerRequest;
    use App\Http\Requests\Customer\PermissionRequest;
    use App\Http\Requests\Customer\StoreCustomerRequest;
    use App\Http\Requests\Customer\UpdateAvatarRequest;
    use App\Http\Requests\Customer\UpdateCustomerRequest;
    use App\Http\Requests\Customer\UpdateInformationRequest;
    use App\Http\Requests\Plan\AddCoverageRequest;
    use App\Http\Requests\Plan\UpdateCoverageRequest;
    use App\Library\Tool;
    use App\Models\Blacklists;
    use App\Models\Campaigns;
    use App\Models\ChatBox;
    use App\Models\ContactGroups;
    use App\Models\Country;
    use App\Models\Customer;
    use App\Models\CustomerBasedPricingPlan;
    use App\Models\CustomerBasedSendingServer;
    use App\Models\Invoices;
    use App\Models\Keywords;
    use App\Models\Language;
    use App\Models\Notifications;
    use App\Models\PhoneNumbers;
    use App\Models\Plan;
    use App\Models\Reports;
    use App\Models\Senderid;
    use App\Models\SendingServer;
    use App\Models\Subscription;
    use App\Models\SubscriptionTransaction;
    use App\Models\Templates;
    use App\Models\User;
    use App\Repositories\Contracts\CustomerRepository;
    use Exception;
    use Generator;
    use Illuminate\Auth\Access\AuthorizationException;
    use Illuminate\Contracts\Foundation\Application;
    use Illuminate\Contracts\View\Factory;
    use Illuminate\Database\Eloquent\ModelNotFoundException;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\RedirectResponse;
    use Illuminate\Http\Request;
    use Illuminate\View\View;
    use Intervention\Image\Exception\NotReadableException;
    use Intervention\Image\Facades\Image;
    use JetBrains\PhpStorm\NoReturn;
    use OpenSpout\Common\Exception\InvalidArgumentException;
    use OpenSpout\Common\Exception\IOException;
    use OpenSpout\Common\Exception\UnsupportedTypeException;
    use OpenSpout\Writer\Exception\WriterNotOpenedException;
    use Rap2hpoutre\FastExcel\FastExcel;
    use Symfony\Component\HttpFoundation\BinaryFileResponse;

    class CustomerController extends AdminBaseController
    {
        /**
         * @var CustomerRepository
         */
        protected CustomerRepository $customers;

        /**
         * Create a new controller instance.
         *
         * @param CustomerRepository $customers
         */
        public function __construct(CustomerRepository $customers)
        {
            $this->customers = $customers;
        }


        /**
         * @return Application|Factory|View
         * @throws AuthorizationException
         */

        public function index(): Factory|View|Application
        {
            
            $this->authorize('view customer');

            $breadcrumbs = [
                ['link' => url(config('app.admin_path') . "/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path') . "/dashboard"), 'name' => __('locale.menu.Customer')],
                ['name' => __('locale.menu.Customers')],
            ];


            return view('admin.customer.index', compact('breadcrumbs'));
        }


        /**
         * view all customers
         *
         * @param Request $request
         *
         * @return void
         * @throws AuthorizationException
         */
        #[NoReturn] public function search(Request $request): void
        {

            $this->authorize('view customer');

            $columns = [
                0 => 'responsive_id',
                1 => 'uid',
                2 => 'uid',
                3 => 'name',
                4 => 'subscription',
                5 => 'status',
                6 => 'actions',
            ];
            $t='is_customer';
            $a='is_admin';
            if(auth()->user()->is_admin){
                $t='is_reseller';
                $a='is_admin';
            }
            $totalData = User::where($t, 1)->where($a, 0)->where('admin_id',auth()->user()->id)->count();

            $totalFiltered = $totalData;

            $limit = $request->input('length');
            $start = $request->input('start');
            $order = $columns[$request->input('order.0.column')];
            $dir   = $request->input('order.0.dir');

            if (empty($request->input('search.value'))) {
                $users = User::where($t, 1)->where($a, 0)->where('admin_id',auth()->user()->id)->offset($start)
                    ->limit($limit)
                    ->orderBy($order, $dir)
                    ->get();
            } else {
                $search = $request->input('search.value');

                $users = User::where($t, 1)->where($a, 0)->where('admin_id',auth()->user()->id)->whereLike(['uid', 'first_name', 'last_name', 'status', 'email'], $search)
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy($order, $dir)
                    ->get();

                $totalFiltered = User::where($t, 1)->where($a, 0)->where('admin_id',auth()->user()->id)->whereLike(['uid', 'first_name', 'last_name', 'status', 'email'], $search)->count();
            }

            $data = [];
            if ( ! empty($users)) {
                foreach ($users as $user) {
                    $show        = route('admin.customers.show', $user->uid);
                    $assign_plan = route('admin.subscriptions.create', ['customer_id' => $user->id]);
                    $login_as    = route('admin.customers.login_as', $user->uid);

                    $assign_plan_label = __('locale.customer.assign_plan');
                    $login_as_label    = __('locale.customer.login_as_customer');
                    $edit              = __('locale.buttons.edit');
                    $delete            = __('locale.buttons.delete');

                    if ($user->status === true) {
                        $status = 'checked';
                    } else {
                        $status = '';
                    }

                    if (isset($user->customer) && $user->customer->currentPlanName()) {
                        $subscription = $user->customer->currentPlanName();
                    } else {
                        $subscription = __('locale.subscription.no_active_subscription');
                    }


                    $super_user = true;
                    if ($user->id != 1) {
                        $super_user = false;
                    }


                    $nestedData['responsive_id'] = '';
                    $nestedData['uid']           = $user->uid;
                    $nestedData['avatar']        = route('admin.customers.avatar', $user->uid);
                    $nestedData['email']         = $user->email;
                    $nestedData['name']          = $user->first_name . ' ' . $user->last_name;
                    $nestedData['created_at']    = __('locale.labels.created_at') . ': ' . Tool::formatDate($user->created_at);

                    $nestedData['subscription'] = "<div>
                                                        <p class='text-bold-600'>$subscription </p>
                                                   </div>";

                    $nestedData['status'] = "<div class='form-check form-switch form-check-primary'>
                <input type='checkbox' class='form-check-input get_status' id='status_$user->uid' data-id='$user->uid' name='status' $status>
                <label class='form-check-label' for='status_$user->uid'>
                  <span class='switch-icon-left'><i data-feather='check'></i> </span>
                  <span class='switch-icon-right'><i data-feather='x'></i> </span>
                </label>
              </div>";

                    $nestedData['assign_plan']       = $assign_plan;
                    $nestedData['assign_plan_label'] = $assign_plan_label;
                    $nestedData['login_as']          = $login_as;
                    $nestedData['login_as_label']    = $login_as_label;
                    $nestedData['show']              = $show;
                    $nestedData['show_label']        = $edit;
                    $nestedData['delete']            = $user->uid;
                    $nestedData['delete_label']      = $delete;
                    $nestedData['super_user']        = $super_user;

                    $data[] = $nestedData;

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
         * create new customer
         *
         * @return Application|Factory|View
         * @throws AuthorizationException
         */
        public function create(): Factory|View|Application
        {
            $this->authorize('create customer');

            $breadcrumbs = [
                ['link' => url(config('app.admin_path') . "/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path') . "/customers"), 'name' => __('locale.menu.Customers')],
                ['name' => __('locale.customer.add_new')],
            ];

            $languages = Language::where('status', 1)->get();

            return view('admin.customer.create', compact('breadcrumbs', 'languages'));
        }

        /**
         *
         * add new customer
         *
         * @param StoreCustomerRequest $request
         *
         * @return RedirectResponse
         */
        public function store(StoreCustomerRequest $request): RedirectResponse
        {

            if (config('app.stage') == 'demo') {
                return redirect()->route('admin.customers.index')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $customer = $this->customers->store($request->input());

            // Upload and save image
            if ($request->hasFile('image')) {
                if ($request->file('image')->isValid()) {
                    $customer->image = $customer->uploadImage($request->file('image'));
                    $customer->save();
                }
            }

            return redirect()->route('admin.customers.show', $customer->uid)->with([
                'status'  => 'success',
                'message' => __('locale.customer.customer_successfully_added'),
            ]);
        }

        /**
         * View customer for edit
         *
         * @param User $customer
         *
         * @return Application|Factory|View
         *
         * @throws AuthorizationException
         */

        public function show(User $customer): Factory|View|Application
        {
            $this->authorize('edit customer');

            $breadcrumbs = [
                ['link' => url(config('app.admin_path') . "/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path') . "/customers"), 'name' => __('locale.menu.Customers')],
                ['name' => $customer->displayName()],
            ];


            $languages = Language::where('status', 1)->get();

            $categories = collect(config('customer-permissions'))->map(function ($value, $key) {
                $value['name'] = $key;

                return $value;
            })->groupBy('category');

            $permissions = $categories->keys()->map(function ($key) use ($categories) {
                return [
                    'title'       => $key,
                    'permissions' => $categories[$key],
                ];
            });

            $existing_permission = json_decode($customer->customer->permissions, true);

            $sending_servers = SendingServer::where('status', 1)->get();

            return view('admin.customer.show', compact('breadcrumbs', 'customer', 'languages', 'permissions', 'existing_permission', 'sending_servers'));
        }

        /**
         * get customer avatar
         *
         * @param User $customer
         *
         * @return mixed
         */
        public function avatar(User $customer): mixed
        {

            if ( ! empty($customer->imagePath())) {

                try {
                    $image = Image::make($customer->imagePath());
                } catch (NotReadableException) {
                    $customer->image = null;
                    $customer->save();

                    $image = Image::make(public_path('images/profile/profile.jpg'));
                }
            } else {
                $image = Image::make(public_path('images/profile/profile.jpg'));
            }

            return $image->response();
        }

        /**
         * update avatar
         *
         * @param User                $customer
         * @param UpdateAvatarRequest $request
         *
         * @return RedirectResponse
         */
        public function updateAvatar(User $customer, UpdateAvatarRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('admin.customers.show', $customer->uid)->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            try {
                // Upload and save image
                if ($request->hasFile('image')) {
                    if ($request->file('image')->isValid()) {

                        // Remove old images
                        $customer->removeImage();
                        $customer->image = $customer->uploadImage($request->file('image'));
                        $customer->save();

                        return redirect()->route('admin.customers.show', $customer->uid)->with([
                            'status'  => 'success',
                            'message' => __('locale.customer.avatar_update_successful'),
                        ]);
                    }

                    return redirect()->route('admin.customers.show', $customer->uid)->with([
                        'status'  => 'error',
                        'message' => __('locale.exceptions.invalid_image'),
                    ]);
                }

                return redirect()->route('admin.customers.show', $customer->uid)->with([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.invalid_image'),
                ]);

            } catch (Exception $exception) {
                return redirect()->route('admin.customers.show', $customer->uid)->with([
                    'status'  => 'error',
                    'message' => $exception->getMessage(),
                ]);
            }
        }


        /**
         * remove avatar
         *
         * @param User $customer
         *
         * @return JsonResponse
         */
        public function removeAvatar(User $customer): JsonResponse
        {

            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            // Remove old images
            $customer->removeImage();
            $customer->image = null;
            $customer->save();

            return response()->json([
                'status'  => 'success',
                'message' => __('locale.customer.avatar_remove_successful'),
            ]);
        }


        /**
         * update customer basic account information
         *
         * @param User                  $customer
         * @param UpdateCustomerRequest $request
         *
         * @return RedirectResponse
         */

        public function update(User $customer, UpdateCustomerRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('admin.customers.show', $customer->uid)->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $this->customers->update($customer, $request->input());

            return redirect()->route('admin.customers.show', $customer->uid)->withInput(['tab' => 'account'])->with([
                'status'  => 'success',
                'message' => __('locale.customer.customer_successfully_updated'),
            ]);
        }


        /**
         * update customer detail information
         *
         * @param User                     $customer
         * @param UpdateInformationRequest $request
         *
         * @return RedirectResponse
         */
        public function updateInformation(User $customer, UpdateInformationRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('admin.customers.show', $customer->uid)->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $this->customers->updateInformation($customer, $request->except('_token'));

            return redirect()->route('admin.customers.show', $customer->uid)->withInput(['tab' => 'information'])->with([
                'status'  => 'success',
                'message' => __('locale.customer.customer_successfully_updated'),
            ]);
        }


        /**
         * update user permission
         *
         * @param User              $customer
         * @param PermissionRequest $request
         *
         * @return RedirectResponse
         */
        public function permissions(User $customer, PermissionRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('admin.customers.show', $customer->uid)->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $this->customers->permissions($customer, $request->only('permissions'));

            return redirect()->route('admin.customers.show', $customer->uid)->withInput(['tab' => 'permission'])->with([
                'status'  => 'success',
                'message' => __('locale.customer.customer_successfully_updated'),
            ]);
        }


        /**
         * change customer status
         *
         * @param User $customer
         *
         * @return JsonResponse
         * @throws AuthorizationException
         * @throws GeneralException
         */
        public function activeToggle(User $customer): JsonResponse
        {
            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }
            try {
                $this->authorize('edit customer');

                if ($customer->update(['status' => ! $customer->status])) {
                    return response()->json([
                        'status'  => 'success',
                        'message' => __('locale.customer.customer_successfully_change'),
                    ]);
                }

                throw new GeneralException(__('locale.exceptions.something_went_wrong'));
            } catch (ModelNotFoundException $exception) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $exception->getMessage(),
                ]);
            }

        }


        /**
         * Bulk Action with Enable, Disable
         *
         * @param Request $request
         *
         * @return JsonResponse
         * @throws AuthorizationException
         */

        public function batchAction(Request $request): JsonResponse
        {

            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $action = $request->get('action');
            $ids    = $request->get('ids');

            switch ($action) {

                case 'enable':

                    $this->authorize('edit customer');

                    $this->customers->batchEnable($ids);

                    return response()->json([
                        'status'  => 'success',
                        'message' => __('locale.customer.customers_enabled'),
                    ]);

                case 'disable':

                    $this->authorize('edit customer');

                    $this->customers->batchDisable($ids);

                    return response()->json([
                        'status'  => 'success',
                        'message' => __('locale.customer.customers_disabled'),
                    ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => __('locale.exceptions.invalid_action'),
            ]);

        }

        /**
         * destroy customer
         *
         * @param User $customer
         *
         * @return JsonResponse
         * @throws AuthorizationException
         * @throws Exception
         */
        public function destroy(User $customer): JsonResponse
        {
            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $this->authorize('delete customer');

            PhoneNumbers::where('user_id', $customer->id)->update([
                'status'  => 'available',
                'user_id' => 1,
            ]);

            Blacklists::where('user_id', $customer->id)->delete();
            Campaigns::where('user_id', $customer->id)->delete();
            ChatBox::where('user_id', $customer->id)->delete();
            ContactGroups::where('customer_id', $customer->id)->delete();
            Customer::where('user_id', $customer->id)->delete();
            Invoices::where('user_id', $customer->id)->delete();
            Keywords::where('user_id', $customer->id)->delete();
            Notifications::where('user_id', $customer->id)->delete();
            Plan::where('user_id', $customer->id)->delete();
            Reports::where('user_id', $customer->id)->delete();
            Senderid::where('user_id', $customer->id)->delete();
            SendingServer::where('user_id', $customer->id)->delete();
            Subscription::where('user_id', $customer->id)->delete();
            Templates::where('user_id', $customer->id)->delete();


            if ( ! $customer->delete()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
                ]);
            }

            return response()->json([
                'status'  => 'success',
                'message' => __('locale.customer.customer_successfully_deleted'),
            ]);

        }


        /**
         * @return Generator
         */
        public function customerGenerator(): Generator
        {
            foreach (User::where($t, 1)->where($a, 0)->where('admin_id',auth()->user()->id)->join('customers', 'user_id', '=', 'users.id')->cursor() as $customer) {
                yield $customer;
            }
        }


        /**
         * @return RedirectResponse|BinaryFileResponse
         * @throws AuthorizationException
         */
        public function export(): BinaryFileResponse|RedirectResponse
        {

            if (config('app.stage') == 'demo') {
                return redirect()->route('admin.customers.index')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $this->authorize('edit customer');

            try {

                $file_name = (new FastExcel($this->customerGenerator()))->export(storage_path('Customers_' . time() . '.xlsx'));

                return response()->download($file_name);

            } catch (IOException|InvalidArgumentException|WriterNotOpenedException|UnsupportedTypeException $e) {
                return redirect()->route('admin.customers.index')->with([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ]);
            }
        }

        /**
         * add custom unit
         *
         * @param User           $customer
         * @param AddUnitRequest $request
         *
         * @return RedirectResponse
         * @throws GeneralException
         */
        public function addUnit(User $customer, AddUnitRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('admin.customers.index')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            try {

                if ($customer->sms_unit != '-1') {

                    $balance = $customer->sms_unit + $request->input('add_unit');

                    if ($customer->update(['sms_unit' => $balance])) {

                        $subscription = $customer->customer->activeSubscription();

                        $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                            'end_at'                 => $subscription->end_at,
                            'current_period_ends_at' => $subscription->current_period_ends_at,
                            'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                            'title'                  => 'Add ' . $request->input('add_unit') . ' sms units',
                            'amount'                 => $request->input('add_unit') . ' sms units',
                        ]);

                        return redirect()->route('admin.customers.show', $customer->uid)->with([
                            'status'  => 'success',
                            'message' => __('locale.customer.add_unit_successful'),
                        ]);
                    }

                    throw new GeneralException(__('locale.exceptions.something_went_wrong'));
                }

                return redirect()->route('admin.customers.show', $customer->uid)->with([
                    'status'  => 'info',
                    'message' => 'You are already in unlimited plan',
                ]);

            } catch (ModelNotFoundException $exception) {
                return redirect()->route('admin.customers.show', $customer->uid)->with([
                    'status'  => 'error',
                    'message' => $exception->getMessage(),
                ]);
            }

        }

        /**
         * remove custom unit
         *
         * @param User           $customer
         * @param AddUnitRequest $request
         *
         * @return RedirectResponse
         * @throws GeneralException
         */
        public function removeUnit(User $customer, AddUnitRequest $request): RedirectResponse
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('admin.customers.index')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            try {

                if ($customer->sms_unit != '-1') {

                    $balance = $customer->sms_unit - $request->input('add_unit');

                    if ($balance < 0) {
                        return redirect()->route('admin.customers.show', $customer->uid)->with([
                            'status'  => 'error',
                            'message' => 'Sorry! You can remove maximum ' . $customer->sms_unit . ' unit',
                        ]);
                    }

                    if ($customer->update(['sms_unit' => $balance])) {

                        $subscription = $customer->customer->activeSubscription();

                        $subscription->addTransaction(SubscriptionTransaction::TYPE_SUBSCRIBE, [
                            'end_at'                 => $subscription->end_at,
                            'current_period_ends_at' => $subscription->current_period_ends_at,
                            'status'                 => SubscriptionTransaction::STATUS_SUCCESS,
                            'title'                  => 'Remove ' . $request->input('add_unit') . ' sms units',
                            'amount'                 => $request->input('add_unit') . ' sms units',
                        ]);

                        return redirect()->route('admin.customers.show', $customer->uid)->with([
                            'status'  => 'success',
                            'message' => __('locale.customer.add_unit_successful'),
                        ]);
                    }

                    throw new GeneralException(__('locale.exceptions.something_went_wrong'));
                }

                return redirect()->route('admin.customers.show', $customer->uid)->with([
                    'status'  => 'info',
                    'message' => 'You are already in unlimited plan',
                ]);

            } catch (ModelNotFoundException $exception) {
                return redirect()->route('admin.customers.show', $customer->uid)->with([
                    'status'  => 'error',
                    'message' => $exception->getMessage(),
                ]);
            }

        }

        /*
        |--------------------------------------------------------------------------
        | Version 3.3
        |--------------------------------------------------------------------------
        |
        | Logged in as a customer option
        |
        */

        /**
         * @param User $customer
         *
         * @return mixed
         * @throws AuthorizationException
         */
        public function impersonate(User $customer): mixed
        {
            $this->authorize('edit customer');

            return $this->customers->impersonate($customer);

        }


        /**
         * Show Pricing
         *
         * @param User    $customer
         * @param Request $request
         * @return void
         * @throws AuthorizationException
         */

        #[NoReturn] public function pricing(User $customer, Request $request)
        {

            $this->authorize('edit customer');


            if ($customer->customer->activeSubscription() !== null) {

                $columns = [
                    0 => 'responsive_id',
                    1 => 'uid',
                    2 => 'uid',
                    3 => 'name',
                    4 => 'iso_code',
                    5 => 'country_code',
                    6 => 'status',
                    7 => 'actions',
                ];

                $limit = $request->input('length');
                $start = $request->input('start');
                $order = $columns[$request->input('order.0.column')];
                $dir   = $request->input('order.0.dir');


                $totalData = CustomerBasedPricingPlan::where('user_id', $customer->id)->count();


                $totalFiltered = $totalData;

                if (empty($request->input('search.value'))) {
                    $countries = CustomerBasedPricingPlan::where('user_id', $customer->id)->offset($start)
                        ->limit($limit)
                        ->orderBy($order, $dir)
                        ->get();
                } else {
                    $search = $request->input('search.value');

                    $countries = CustomerBasedPricingPlan::where('user_id', $customer->id)->whereLike(['uid', 'country.name', 'country.iso_code', 'country.country_code'], $search)
                        ->offset($start)
                        ->limit($limit)
                        ->orderBy($order, $dir)
                        ->get();

                    $totalFiltered = CustomerBasedPricingPlan::where('user_id', $customer->id)->whereLike(['uid', 'country.name', 'country.iso_code', 'country.country_code'], $search)->count();
                }

                $data = [];
                if ( ! empty($countries)) {
                    foreach ($countries as $country) {

                        if ($country->status === true) {
                            $status = 'checked';
                        } else {
                            $status = '';
                        }

                        $nestedData['responsive_id'] = '';
                        $nestedData['uid']           = $country->uid;
                        $nestedData['name']          = $country->country->name;
                        $nestedData['country_code']  = $country->country->country_code;
                        $nestedData['iso_code']      = $country->country->iso_code;
                        $nestedData['status']        = "<div class='form-check form-switch form-check-primary'>
                <input type='checkbox' class='form-check-input get_coverage_status' id='status_$country->uid' data-id='$country->uid' name='status' $status>
                <label class='form-check-label' for='status_$country->uid'>
                  <span class='switch-icon-left'><i data-feather='check'></i> </span>
                  <span class='switch-icon-right'><i data-feather='x'></i> </span>
                </label>
              </div>";
                        $nestedData['edit']          = route('admin.customers.edit_coverage', ['customer' => $customer->uid, 'coverage' => $country->uid]);
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
        }


        /**
         * Add Coverage
         *
         * @param User $customer
         * @return Application|Factory|\Illuminate\Contracts\View\View|\Illuminate\Foundation\Application
         */

        public function coverage(User $customer)
        {
            $breadcrumbs = [
                ['link' => url(config('app.admin_path') . "/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path') . "/customers"), 'name' => __('locale.menu.Customer')],
                ['name' => __('locale.buttons.add_coverage')],
            ];

            $countries       = Country::where('status', 1)->get();
            $sending_servers = SendingServer::where('status', true)->get();

            return view('admin.customer._coverage', compact('breadcrumbs', 'countries', 'customer', 'sending_servers'));

        }


        /**
         *
         * Post Coverage
         *
         * @param User               $customer
         * @param AddCoverageRequest $request
         * @return RedirectResponse
         * @throws AuthorizationException
         */
        public function postCoverage(User $customer, AddCoverageRequest $request)
        {
            if (config('app.stage') == 'demo') {
                return redirect()->route('admin.customers.show', $customer->uid)->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $this->authorize('edit customer');

            $options = $request->except('_token', 'country', 'sending_server', 'voice_sending_server', 'mms_sending_server', 'whatsapp_sending_server', 'viber_sending_server', 'otp_sending_server');

            foreach ($request->input('country') as $country) {
                $exist = CustomerBasedPricingPlan::where('user_id', $customer->id)->where('country_id', $country)->first();
                if ($exist) {
                    continue;
                }

                CustomerBasedPricingPlan::create([
                    'user_id'                 => $customer->id,
                    'country_id'              => $country,
                    'plan_id'                 => $customer->customer->activeSubscription()->plan_id,
                    'options'                 => json_encode($options),
                    'sending_server'          => $request->input('sending_server'),
                    'voice_sending_server'    => $request->input('voice_sending_server'),
                    'mms_sending_server'      => $request->input('mms_sending_server'),
                    'whatsapp_sending_server' => $request->input('whatsapp_sending_server'),
                    'viber_sending_server'    => $request->input('viber_sending_server'),
                    'otp_sending_server'      => $request->input('otp_sending_server'),
                ]);

            }

            return redirect()->route('admin.customers.show', $customer->uid)->withInput(['tab' => 'usms_pricing'])->with([
                'status'  => 'success',
                'message' => __('locale.plans.coverage_was_successfully_added'),
            ]);
        }

        /**
         *
         * Edit Coverage
         *
         * @param User                     $customer
         * @param CustomerBasedPricingPlan $coverage
         * @return Application|Factory|\Illuminate\Contracts\View\View|\Illuminate\Foundation\Application
         * @throws AuthorizationException
         */

        public function editCoverage(User $customer, CustomerBasedPricingPlan $coverage)
        {

            $this->authorize('edit customer');

            $breadcrumbs = [
                ['link' => url(config('app.admin_path') . "/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path') . "/customers"), 'name' => __('locale.menu.Customer')],
                ['name' => __('locale.buttons.update_coverage')],
            ];

            $options         = json_decode($coverage->options, true);
            $sending_servers = SendingServer::where('status', true)->get();

            return view('admin.customer._coverage', compact('breadcrumbs', 'customer', 'options', 'coverage', 'sending_servers'));
        }

        /**
         *Update coverage post
         *
         *
         * @param User                     $customer
         * @param CustomerBasedPricingPlan $coverage
         * @param UpdateCoverageRequest    $request
         * @return RedirectResponse
         * @throws AuthorizationException
         */

        public function editCoveragePost(User $customer, CustomerBasedPricingPlan $coverage, UpdateCoverageRequest $request): RedirectResponse
        {

            if (config('app.stage') == 'demo') {
                return redirect()->route('admin.customers.edit_coverage', ['customer' => $customer->uid, 'coverage' => $coverage->uid])->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            $this->authorize('edit customer');

            $get_options = json_decode($coverage->options, true);
            $output      = array_replace($get_options, $request->except('_token', 'country', 'sending_server', 'voice_sending_server', 'mms_sending_server', 'whatsapp_sending_server', 'viber_sending_server', 'otp_sending_server'));

            if ( ! $coverage->update([
                'options'                 => $output,
                'plan_id'                 => $customer->customer->activeSubscription()->plan_id,
                'sending_server'          => $request->input('sending_server'),
                'voice_sending_server'    => $request->input('voice_sending_server'),
                'mms_sending_server'      => $request->input('mms_sending_server'),
                'whatsapp_sending_server' => $request->input('whatsapp_sending_server'),
                'viber_sending_server'    => $request->input('viber_sending_server'),
                'otp_sending_server'      => $request->input('otp_sending_server'),
            ])) {
                return redirect()->route('admin.customers.edit_coverage', ['customer' => $customer->uid, 'coverage' => $coverage->uid])->with([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
                ]);
            }

            return redirect()->route('admin.customers.show', $customer->uid)->withInput(['tab' => 'usms_pricing'])->with([
                'status'  => 'success',
                'message' => 'Coverage was successfully updated',
            ]);
        }


        /**
         * @param User                     $customer
         * @param CustomerBasedPricingPlan $coverage
         *
         * @return JsonResponse
         * @throws GeneralException|AuthorizationException
         */
        public function activeCoverageToggle(User $customer, CustomerBasedPricingPlan $coverage): JsonResponse
        {
            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            try {
                $this->authorize('edit customer');

                if ($coverage->where('user_id', $customer->id)->find($coverage->id)->update(['status' => ! $coverage->status])) {
                    return response()->json([
                        'status'  => 'success',
                        'message' => __('locale.settings.status_successfully_change'),
                    ]);
                }

                throw new GeneralException(__('locale.exceptions.something_went_wrong'));

            } catch (ModelNotFoundException $exception) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        /**
         * @param User                     $customer
         * @param CustomerBasedPricingPlan $coverage
         *
         * @return JsonResponse
         * @throws GeneralException|AuthorizationException
         */
        public function deleteCoverage(User $customer, CustomerBasedPricingPlan $coverage): JsonResponse
        {
            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            try {
                $this->authorize('edit customer');

                if ($coverage->where('user_id', $customer->id)->find($coverage->id)->delete()) {
                    return response()->json([
                        'status'  => 'success',
                        'message' => __('locale.plans.plan_successfully_deleted'),
                    ]);
                }

                throw new GeneralException(__('locale.exceptions.something_went_wrong'));

            } catch (ModelNotFoundException $exception) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $exception->getMessage(),
                ]);
            }
        }


        /*Version 3.8*/

        /**
         * Add Sending Server
         *
         * @param User                    $customer
         * @param AddSendingServerRequest $request
         * @return RedirectResponse
         */

        public function addSendingServer(User $customer, AddSendingServerRequest $request)
        {

            if (config('app.stage') == 'demo') {
                return redirect()->route('admin.customers.show', $customer->uid)->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }


            foreach ($request->input('sending_servers') as $server) {
                $exist = CustomerBasedSendingServer::where('user_id', $customer->id)->where('sending_server', $server)->first();
                if ($exist) {
                    continue;
                }

                CustomerBasedSendingServer::create([
                    'user_id'        => $customer->id,
                    'sending_server' => $server,
                ]);

            }

            return redirect()->route('admin.customers.show', $customer->uid)->withInput(['tab' => 'usms_sending_server'])->with([
                'status'  => 'success',
                'message' => __('locale.sending_servers.sending_server_successfully_added'),
            ]);
        }


        /**
         * Delete Sending Server
         *
         * @param User                       $customer
         * @param DeleteSendingServerRequest $request
         * @return JsonResponse
         */

        public function deleteSendingServer(User $customer, DeleteSendingServerRequest $request): JsonResponse
        {
            if (config('app.stage') == 'demo') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
                ]);
            }

            try {

                $status = CustomerBasedSendingServer::where('user_id', $customer->id)->where('sending_server', $request->server_id)->delete();

                if ($status) {
                    return response()->json([
                        'status'  => 'success',
                        'message' => __('locale.sending_servers.sending_server_successfully_deleted'),
                    ]);
                }

                return response()->json([
                    'status'  => 'success',
                    'message' => __('locale.plans.plan_successfully_deleted'),
                ]);

            } catch (ModelNotFoundException $exception) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $exception->getMessage(),
                ]);
            }
        }


    }
