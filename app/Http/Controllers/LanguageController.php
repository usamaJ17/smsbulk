<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    /**
     *
     * set localization
     *
     * @param $locale
     *
     * @return RedirectResponse
     */
    public function swap($locale): RedirectResponse
    {

        if (config('app.stage') == 'demo') {
            return redirect()->back()->with([
                    'status'  => 'error',
                    'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $availLocale = Session::get('availableLocale');

        if ( ! isset($availLocale)) {
            $availLocale = Language::where('status', 1)->select('code')->cursor()->map(function ($name) {
                return $name->code;
            })->toArray();

            Session::put('availableLocale', $availLocale);
        }

        $localeCount = Language::where('status', 1)->count();

        if ($localeCount != count($availLocale)) {
            $availLocale = Language::where('status', 1)->select('code')->cursor()->map(function ($name) {
                return $name->code;
            })->toArray();

            Session::put('availableLocale', $availLocale);
        }

        // check for existing language
        if (in_array($locale, $availLocale)) {
            Session::put('locale', $locale);
        }

        Auth::user()->update([
                'locale' => $locale,
        ]);

        if (Auth::user()->active_portal == 'customer' && Auth::user()->is_customer == 1){
            return redirect()->route('user.home');
        }
        return redirect()->route('admin.home');
    }

    public function languages()
    {

        $availLocale = Session::get('available_languages');

        if ( ! isset($availLocale)) {
            $availLocale = Language::where('status', 1)->cursor()->map(function ($lang) {
                return [
                        'name' => $lang->name,
                        'code' => $lang->code,
                ];
            })->toArray();

            Session::put('available_languages', $availLocale);
        }

        $localeCount = Language::where('status', 1)->count();

        if ($localeCount != count($availLocale)) {
            $availLocale = Language::where('status', 1)->select('code')->cursor()->map(function ($name) {
                return $name->code;
            })->toArray();

            Session::put('available_languages', $availLocale);
        }

        return $availLocale;
    }
}
