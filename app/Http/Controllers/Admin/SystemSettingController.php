<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\Admin\MobileBanners;
use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSettingModel;


class SystemSettingController extends Controller
{
    public function index(Request $request)
    {
        $settings = SystemSettingModel::first();
        $images = MobileBanners::all();
        return view('admin.system_setting.index', compact('settings', 'images'));
    }


    public function update(Request $request)
    {
        $request->validate([
            'system_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'currency' => 'required|string|max:10',
            'payment_method' => 'required|string|max:50',
        ]);

        $settings = SystemSettingModel::first();
        if (!$settings) {
            $settings = new SystemSettingModel();
        }

        $settings->system_name = $request->system_name;
        $settings->currency = $request->currency;
        $settings->payment_method = $request->payment_method;

        if ($request->hasFile('logo')) {
            if ($settings->logo && file_exists(public_path('uploads/system_settings/' . $settings->logo))) {
                unlink(public_path('uploads/system_settings/' . $settings->logo));
            }

            $file = $request->file('logo');
            $extension = $file->getClientOriginalExtension();
            $filename = 'logo.' . $extension;
            $file->move(public_path('uploads/system_settings/'), $filename);
            $settings->logo = $filename;
        }

        $settings->save();

        return redirect()->back()->with('success', 'System settings updated successfully.');
    }

    public function mobile_banners(Request $request)
    {
        $request->validate([
            'banners' => 'required|array',
            'banners.*' => 'image|max:8192',
        ]);

        if ($request->hasFile('banners')) {
            foreach ($request->file('banners') as $banner) {
                $filename = uniqid('', true) . '.' . $banner->getClientOriginalExtension();

                $banner->move(public_path('uploads/mobile_banners/'), $filename);
                MobileBanners::create([
                    'path' => $filename,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Banners updated successfully.');
    }

    public function delete_mobile_banners($id)
    {
        try {
            $banner = MobileBanners::findOrFail($id);
            if ($banner->path && !str_contains($banner->path, '..')) {
                $imagePath = public_path('uploads/mobile_banners/' . $banner->path);

                if (file_exists($imagePath) && is_file($imagePath)) {
                    unlink($imagePath);
                }
            }

            $banner->delete();

            return redirect()->back()->with('success', 'Banner deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete banner: ' . $e->getMessage());
        }
    }
}
