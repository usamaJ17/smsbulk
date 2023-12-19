<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request; 
use App\Models\QuickResponse;
use Validator;
class QuickResponseController extends Controller
{
   
    public function index()
    {

        $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.Sending')],
                ['name' => __('locale.menu.Quick Replies')],
        ];
        $quickReplies = QuickResponse::where('user_id', \Auth::id())->get();
        return view('customer.QuickResponse.index', compact('breadcrumbs', 'quickReplies'));
    }



    /**
     * create new template
     *
     * @return Application|Factory|View
     * @throws AuthorizationException
     */

    public function create()
    {

        $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.Sending')],
                ['name' => __('locale.menu.Quick Replies')],
        ];
        return view('customer.QuickResponse.create', compact('breadcrumbs'));
    }
    public function edit($id)
    {

        $breadcrumbs = [
                ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
                ['link' => url('dashboard'), 'name' => __('locale.menu.Sending')],
                ['name' => __('locale.menu.Quick Replies')],
        ];
        $reply = QuickResponse::find($id);
        return view('customer.QuickResponse.create', compact('breadcrumbs', 'reply'));
    }
    public function store(Request $request)
    {

        if (config('app.stage') == 'demo') {
            return redirect()->route('customer.quick-replies.index')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        // if(QuickResponse::where('user_id', \Auth::id())->count() >= 3){
        //     return redirect()->route('customer.quick-replies.index')->with([
        //         'status'  => 'error',
        //         'message' => __('locale.quick_replies.quick_reply_max_added'),
        //     ]);
        // }
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator->errors());
        }

        QuickResponse::create($request->all());

        return redirect()->route('customer.quick-replies.index')->with([
                'status'  => 'success',
                'message' => __('locale.quick_replies.quick_reply_successfully_added'),
        ]);

    }


    /**
     * update template
     *
     * @param  Templates  $template
     * @param  UpdateTemplate  $request
     *
     * @return RedirectResponse
     */

    public function update(Request $request, $id)
    {

        if (config('app.stage') == 'demo') {
            return redirect()->route('customer.quick-replies.index')->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $validator = Validator::make($request->all(), [
                'name' => 'required'
        ]);
        if ($validator->fails()) {
            return redirect()->route('customer.quick-replies.create')->withErrors($validator->errors());
        }
        $reply = QuickResponse::find($id);
        $reply->name = $request->name;
        $reply->save();
        return redirect()->route('customer.quick-replies.index')->with([
                'status'  => 'success',
                'message' => __('locale.quick_replies.quick_reply_successfully_updated'),
        ]);
    }

    /**
     * remove existing template
     *
     * @param  Templates  $template
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy($id){

        if (config('app.stage') == 'demo') {
            return response()->json([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }
        $reply = QuickResponse::find($id);
        $reply->delete();

        return response()->json([
                'status'  => 'success',
                'message' => __('locale.quick_replies.quick_reply_successfully_deleted'),
        ]);

    }

}
