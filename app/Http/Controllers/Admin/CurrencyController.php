<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\GeneralException;
use App\Http\Requests\Currency\StoreCurrencyRequest;
use App\Http\Requests\Currency\UpdateCurrencyRequest;
use App\Models\Currency;
use App\Repositories\Contracts\CurrencyRepository;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use JetBrains\PhpStorm\NoReturn;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CurrencyController extends AdminBaseController
{
    protected CurrencyRepository $currencies;


    /**
     * CurrencyController constructor.
     *
     * @param  CurrencyRepository  $currencies
     */

    public function __construct(CurrencyRepository $currencies)
    {
        $this->currencies = $currencies;
    }

    /**
     * @return Application|Factory|View
     * @throws AuthorizationException
     */

    public function index(): Factory|View|Application
    {

        $this->authorize('manage currencies');

        $breadcrumbs = [
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Plan')],
                ['name' => __('locale.menu.Currencies')],
        ];


        return view('admin.currency.index', compact('breadcrumbs'));
    }


    /**
     * @param  Request  $request
     *
     * @return void
     * @throws AuthorizationException
     */
    #[NoReturn] public function search(Request $request): void
    {

        $this->authorize('manage currencies');

        $columns = [
                0 => 'responsive_id',
                1 => 'uid',
                2 => 'uid',
                3 => 'name',
                4 => 'code',
                5 => 'format',
                6 => 'status',
                7 => 'actions',
        ];

        $totalData = Currency::count();

        $totalFiltered = $totalData;

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir   = $request->input('order.0.dir');

        if (empty($request->input('search.value'))) {
            $currencies = Currency::offset($start)
                                  ->limit($limit)
                                  ->orderBy($order, $dir)
                                  ->get();
        } else {
            $search = $request->input('search.value');

            $currencies = Currency::whereLike(['uid', 'name', 'code', 'format'], $search)
                                  ->offset($start)
                                  ->limit($limit)
                                  ->orderBy($order, $dir)
                                  ->get();

            $totalFiltered = Currency::whereLike(['uid', 'name', 'code', 'format'], $search)->count();
        }

        $data = [];
        if ( ! empty($currencies)) {
            foreach ($currencies as $currency) {
                $show = route('admin.currencies.show', $currency->uid);

                if ($currency->status === true) {
                    $status = 'checked';
                } else {
                    $status = '';
                }

                $edit   = null;
                $delete = null;


                if (Auth::user()->can('edit currencies')) {
                    $edit .= $show;
                }

                if (Auth::user()->can('delete currencies')) {
                    $delete .= $currency->uid;
                }


                $nestedData['responsive_id'] = '';
                $nestedData['uid']           = $currency->uid;
                $nestedData['name']          = $currency->name;
                $nestedData['code']          = $currency->code;
                $nestedData['format']        = $currency->format;
                $nestedData['status']        = "<div class='form-check form-switch form-check-primary'>
                <input type='checkbox' class='form-check-input get_status' id='status_$currency->uid' data-id='$currency->uid' name='status' $status>
                <label class='form-check-label' for='status_$currency->uid'>
                  <span class='switch-icon-left'><i data-feather='check'></i> </span>
                  <span class='switch-icon-right'><i data-feather='x'></i> </span>
                </label>
              </div>";
                $nestedData['edit']          = $edit;
                $nestedData['delete']        = $delete;
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
     * @return Application|Factory|View
     * @throws AuthorizationException
     */

    public function create(): Factory|View|Application
    {
        $this->authorize('create currencies');

        $breadcrumbs = [
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path')."/currencies"), 'name' => __('locale.menu.Currencies')],
                ['name' => __('locale.currencies.add_new_currency')],
        ];

        return view('admin.currency.create', compact('breadcrumbs'));
    }


    /**
     * View currency for edit
     *
     * @param  Currency  $currency
     *
     * @return Application|Factory|View
     *
     * @throws AuthorizationException
     */

    public function show(Currency $currency): Factory|View|Application
    {
        $this->authorize('edit currencies');

        $breadcrumbs = [
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path')."/currencies"), 'name' => __('locale.menu.Currencies')],
                ['name' => __('locale.currencies.update_currency')],
        ];

        return view('admin.currency.create', compact('breadcrumbs', 'currency'));
    }


    /**
     * @param  StoreCurrencyRequest  $request
     *
     * @return RedirectResponse
     */

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        if (config('app.stage') == 'demo') {
            return redirect()->route('admin.currencies.index')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $this->currencies->store($request->input());

        return redirect()->route('admin.currencies.index')->with([
                'status'  => 'success',
                'message' => __('locale.currencies.currency_successfully_added'),
        ]);

    }


    /**
     * @param  Currency  $currency
     * @param  UpdateCurrencyRequest  $request
     *
     * @return RedirectResponse
     */

    public function update(Currency $currency, UpdateCurrencyRequest $request): RedirectResponse
    {
        if (config('app.stage') == 'demo') {
            return redirect()->route('admin.currencies.index')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $this->currencies->update($currency, $request->input());

        return redirect()->route('admin.currencies.index')->with([
                'status'  => 'success',
                'message' => __('locale.currencies.currency_successfully_updated'),
        ]);
    }

    /**
     * @param  Currency  $currency
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */
    public function destroy(Currency $currency): JsonResponse
    {
        if (config('app.stage') == 'demo') {
            return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }
        $this->authorize('delete currencies');

        $this->currencies->destroy($currency);

        return response()->json([
                'status'  => 'success',
                'message' => __('locale.currencies.currency_successfully_deleted'),
        ]);

    }

    /**
     * change currency status
     *
     * @param  Currency  $currency
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     * @throws GeneralException
     */
    public function activeToggle(Currency $currency): JsonResponse
    {
        if (config('app.stage') == 'demo') {
            return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        try {
            $this->authorize('edit currencies');

            if ($currency->update(['status' => ! $currency->status])) {
                return response()->json([
                        'status'  => 'success',
                        'message' => __('locale.currencies.currency_successfully_change'),
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
     * Bulk Action with Enable, Disable and Delete
     *
     * @param  Request  $request
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
            case 'destroy':
                $this->authorize('delete currencies');

                $this->currencies->batchDestroy($ids);

                return response()->json([
                        'status'  => 'success',
                        'message' => __('locale.currencies.currencies_deleted'),
                ]);

            case 'enable':
                $this->authorize('edit currencies');

                $this->currencies->batchActive($ids);

                return response()->json([
                        'status'  => 'success',
                        'message' => __('locale.currencies.currencies_enabled'),
                ]);

            case 'disable':

                $this->authorize('edit currencies');

                $this->currencies->batchDisable($ids);

                return response()->json([
                        'status'  => 'success',
                        'message' => __('locale.currencies.currencies_disabled'),
                ]);
        }

        return response()->json([
                'status'  => 'error',
                'message' => __('locale.exceptions.invalid_action'),
        ]);

    }


    /**
     * @return Generator
     */

    public function currencyGenerator(): Generator
    {
        foreach (Currency::cursor() as $currency) {
            yield $currency;
        }
    }

    /**
     * @return RedirectResponse|BinaryFileResponse
     * @throws AuthorizationException
     */
    public function export(): BinaryFileResponse|RedirectResponse
    {
        if (config('app.stage') == 'demo') {
            return redirect()->route('admin.currencies.index')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $this->authorize('manage currencies');

        try {
            $file_name = (new FastExcel($this->currencyGenerator()))->export(storage_path('Currency_'.time().'.xlsx'));

            return response()->download($file_name);
        } catch (IOException|InvalidArgumentException|WriterNotOpenedException|UnsupportedTypeException $e) {
            return redirect()->route('admin.currencies.index')->with([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
            ]);
        }


    }

}
