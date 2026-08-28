<?php

namespace App\Http\Controllers;

use App\Providers\MailConfigServiceProvider;
use Illuminate\Support\Facades\Cache;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use App\Models\MailSetting;
use Illuminate\Support\Facades\Mail;
use App\Mail\QuotationSentMail;


class SettingController extends Controller
{
    public function index(){
        $title = 'Setting';
        $mailSetting = MailSetting::first();
        return view('profile.setting', compact('title', 'mailSetting'));
    }

    public function saveSettings(Request $request){
        $request->validate([
            'business_name'     => 'required|string|max:255',
            'currency_symbol'   => 'required|string|max:10',
            'currency_code'     => 'required|string|max:10',
            'default_email'     => 'nullable|email|max:255',
            'company_phone'     => 'nullable|string|max:20',
            'footer'            => 'nullable|string|max:255',
            'country'           => 'nullable|string|max:100',
            'state'             => 'nullable|string|max:100',
            'city'              => 'nullable|string|max:100',
            'postal_code'       => 'nullable|string|max:20',
            'address'           => 'nullable|string|max:500',
            'developed_by'      => 'nullable|string|max:100',
            'primary_color'     => 'nullable|string|max:7',
            'secondary_color'   => 'nullable|string|max:7',
            'logo'              => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $setting = Setting::first() ?? new Setting();

        // Basic details
        $setting->business_name     = $request->business_name;
        $setting->currency_symbol   = $request->currency_symbol;
        $setting->currency_code     = $request->currency_code;
        $setting->default_email     = $request->default_email;
        $setting->company_phone     = $request->company_phone;
        $setting->footer            = $request->footer;
        $setting->country           = $request->country;
        $setting->state             = $request->state;
        $setting->city              = $request->city;
        $setting->postal_code       = $request->postal_code;
        $setting->address           = $request->address;
        $setting->developed_by      = $request->developed_by;
        $setting->primary_color     = $request->primary_color;
        $setting->secondary_color   = $request->secondary_color;

        // Handle logo upload
        if ($request->hasFile('logo')) {
            if ($setting->logo_path && Storage::disk('public')->exists($setting->logo_path)) {
                Storage::disk('public')->delete($setting->logo_path);
            }

            $imagePath = $request->file('logo')->store('Logo', 'public');
            $setting->logo_path = $imagePath;
        }

        $setting->save();

        return redirect()->back()->with('success', 'Settings saved successfully.');
    }

    public function saveMailSettings(Request $request)
    {
        $validated = $request->validate([
            'mail_mailer'     => 'required|string|max:50',
            'mail_host'       => 'required|string|max:255',
            'mail_port'       => 'required|integer',
            'mail_username'   => 'required|string|max:255',
            'mail_password'   => 'required|string|max:255',
            'mail_encryption' => 'nullable|string|max:10',
            'sender_name'     => 'required|string|max:255',
        ]);

        $setting = MailSetting::first() ?? new MailSetting();
        $setting->fill($validated);
        $setting->save();

        // MailConfigServiceProvider caches this row, so the new settings only take
        // effect once that cache is dropped.
        Cache::forget(MailConfigServiceProvider::CACHE_KEY);

        return redirect()->back()->with('success', 'Mail settings updated successfully.');
    }

}
