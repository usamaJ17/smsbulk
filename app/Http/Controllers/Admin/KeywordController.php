<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Keywords\StoreKeywordsRequest;
use App\Http\Requests\Keywords\UpdateKeywordsRequest;
use App\Library\Tool;
use App\Models\Currency;
use App\Models\Keywords;
use App\Models\User;
use App\Repositories\Contracts\KeywordRepository;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JetBrains\PhpStorm\NoReturn;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KeywordController extends AdminBaseController
{

    protected KeywordRepository $keywords;


    /**
     * KeywordController constructor.
     *
     * @param  KeywordRepository  $keywords
     */

    public function __construct(KeywordRepository $keywords)
    {
        $this->keywords = $keywords;
    }

    /**
     * @return Application|Factory|View
     * @throws AuthorizationException
     */

    public function index(): Factory|View|Application
    {

        $this->authorize('view keywords');

        $breadcrumbs = [
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Sending')],
                ['name' => __('locale.menu.Keywords')],
        ];


        return view('admin.keywords.index', compact('breadcrumbs'));
    }


    /**
     * @param  Request  $request
     *
     * @return void
     * @throws AuthorizationException
     */
    #[NoReturn] public function search(Request $request): void
    {

        $this->authorize('view keywords');

        $columns = [
                0 => 'responsive_id',
                1 => 'uid',
                2 => 'uid',
                3 => 'title',
                4 => 'keyword_name',
                5 => 'user_id',
                6 => 'price',
                7 => 'status',
                8 => 'actions',
        ];

        $totalData = Keywords::count();

        $totalFiltered = $totalData;

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir   = $request->input('order.0.dir');

        if (empty($request->input('search.value'))) {
            $keywords = Keywords::offset($start)
                                ->limit($limit)
                                ->orderBy($order, $dir)
                                ->get();
        } else {
            $search = $request->input('search.value');

            $keywords = Keywords::whereLike(['uid', 'title', 'keyword_name', 'user.first_name', 'user.last_name'], $search)
                                ->offset($start)
                                ->limit($limit)
                                ->orderBy($order, $dir)
                                ->get();

            $totalFiltered = Keywords::whereLike(['uid', 'title', 'keyword_name', 'user.first_name', 'user.last_name'], $search)->count();
        }

        $data = [];
        if ( ! empty($keywords)) {
            foreach ($keywords as $keyword) {
                $show = route('admin.keywords.show', $keyword->uid);

                $edit       = __('locale.buttons.edit');
                $delete     = __('locale.buttons.delete');
                $remove_mms = __('locale.buttons.remove_mms');


                if ($keyword->user->is_admin) {
                    $assign_to = $keyword->user->displayName();
                } else {

                    $customer_profile = route('admin.customers.show', $keyword->user->uid);
                    $customer_name    = $keyword->user->displayName();
                    $assign_to        = "<a href='$customer_profile' class='text-primary mr-1'>$customer_name</a>";
                }

                if ($keyword->status == 'available') {
                    $status = '<span class="badge badge-light-primary text-uppercase">'.__('locale.labels.available').'</span>';
                } elseif ($keyword->status == 'assigned') {
                    $status = '<span class="badge badge-light-success text-uppercase">'.__('locale.labels.assigned').'</span>';
                } else {
                    $status = '<span class="badge badge-light-danger text-uppercase">'.__('locale.labels.expired').'</span>';
                }


                $reply_mms = false;

                if ($keyword->reply_mms) {
                    $reply_mms = true;
                }

                $nestedData['responsive_id'] = '';
                $nestedData['avatar']        = route('admin.customers.avatar', $keyword->user->uid);
                $nestedData['email']         = $keyword->user->email;
                $nestedData['uid']           = $keyword->uid;
                $nestedData['title']         = $keyword->title;
                $nestedData['keyword_name']  = $keyword->keyword_name;
                $nestedData['user_id']       = $assign_to;
                $nestedData['price']         = "<div>
                                                        <p class='text-bold-600'>".Tool::format_price($keyword->price, $keyword->currency->format)." </p>
                                                        <p class='text-muted'>".$keyword->displayFrequencyTime()."</p>
                                                   </div>";
                $nestedData['reply_mms']     = $reply_mms;
                $nestedData['remove_mms']    = $remove_mms;
                $nestedData['show_label']    = $edit;
                $nestedData['show']          = $show;
                $nestedData['delete']        = $delete;
                $nestedData['status']        = $status;
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
        $this->authorize('create keywords');

        $breadcrumbs = [
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path')."/keywords"), 'name' => __('locale.menu.Keywords')],
                ['name' => __('locale.keywords.create_new_keyword')],
        ];

        $customers  = User::where('status', true)->get();
        $currencies = Currency::where('status', true)->get();

        return view('admin.keywords.create', compact('breadcrumbs', 'customers', 'currencies'));
    }

    /**
     * @param  StoreKeywordsRequest  $request
     *
     * @param  Keywords  $keyword
     *
     * @return RedirectResponse
     * @throws AuthorizationException
     */

    public function store(StoreKeywordsRequest $request, Keywords $keyword): RedirectResponse
    {
        if (config('app.stage') == 'demo') {
            return redirect()->route('admin.keywords.create')->withInput($request->except('_token'))->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $this->authorize('create keywords');

        $this->keywords->store($request->except('_token'), $keyword::billingCycleValues());

        return redirect()->route('admin.keywords.index')->with([
                'status'  => 'success',
                'message' => __('locale.keywords.keyword_successfully_added'),
        ]);

    }


    /**
     * View currency for edit
     *
     * @param  Keywords  $keyword
     *
     * @return Application|Factory|View
     *
     * @throws AuthorizationException
     */

    public function show(Keywords $keyword): Factory|View|Application
    {
        $this->authorize('edit keywords');

        $breadcrumbs = [
                ['link' => url(config('app.admin_path')."/dashboard"), 'name' => __('locale.menu.Dashboard')],
                ['link' => url(config('app.admin_path')."/keywords"), 'name' => __('locale.menu.Keywords')],
                ['name' => __('locale.keywords.update_keyword')],
        ];
        $customers   = User::where('status', true)->get();
        $currencies  = Currency::where('status', true)->get();

        return view('admin.keywords.create', compact('breadcrumbs', 'keyword', 'customers', 'currencies'));
    }


    /**
     * @param  Keywords  $keyword
     * @param  UpdateKeywordsRequest  $request
     *
     * @return RedirectResponse
     */

    public function update(Keywords $keyword, UpdateKeywordsRequest $request): RedirectResponse
    {
        if (config('app.stage') == 'demo') {
            return redirect()->route('admin.keywords.show', $keyword->uid)->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $this->keywords->update($keyword, $request->all(), $keyword::billingCycleValues());

        return redirect()->route('admin.keywords.show', $keyword->uid)->with([
                'status'  => 'success',
                'message' => __('locale.keywords.keyword_successfully_updated'),
        ]);
    }

    /**
     * remove mms file
     *
     * @param  Keywords  $keyword
     *
     * @return JsonResponse
     */

    public function removeMMS(Keywords $keyword): JsonResponse
    {
        if (config('app.stage') == 'demo') {
            return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        if ( ! $keyword->update(['reply_mms' => null])) {
            return response()->json([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
            ]);
        }

        return response()->json([
                'status'  => 'success',
                'message' => __('locale.keywords.keyword_mms_file_removed'),
        ]);
    }


    /**
     * @param  Keywords  $keyword
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */
    public function destroy(Keywords $keyword): JsonResponse
    {
        if (config('app.stage') == 'demo') {
            return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $this->authorize('delete keywords');

        $this->keywords->destroy($keyword);

        return response()->json([
                'status'  => 'success',
                'message' => __('locale.keywords.keyword_successfully_deleted'),
        ]);

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
                $this->authorize('delete keywords');

                $this->keywords->batchDestroy($ids);

                return response()->json([
                        'status'  => 'success',
                        'message' => __('locale.keywords.keywords_deleted'),
                ]);

            case 'available':
                $this->authorize('edit keywords');

                $this->keywords->batchAvailable($ids);

                return response()->json([
                        'status'  => 'success',
                        'message' => __('locale.keywords.available_keywords'),
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

    public function keywordGenerator(): Generator
    {
        foreach (Keywords::cursor() as $keyword) {
            yield $keyword;
        }
    }


    /**
     * @return RedirectResponse|BinaryFileResponse
     * @throws AuthorizationException
     */
    public function export(): BinaryFileResponse|RedirectResponse
    {
        if (config('app.stage') == 'demo') {
            return redirect()->route('admin.keywords.index')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $this->authorize('view keywords');

        try {
            $file_name = (new FastExcel($this->keywordGenerator()))->export(storage_path('Keyword_'.time().'.xlsx'));

            return response()->download($file_name);
        } catch (IOException|InvalidArgumentException|UnsupportedTypeException|WriterNotOpenedException $e) {
            return redirect()->route('admin.keywords.index')->with([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
            ]);
        }

    }

}
