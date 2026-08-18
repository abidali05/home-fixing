<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function index()
    {
        $appVersion = AppVersion::query()->latest()->first();

        return view('admin.app_versions.index', compact('appVersion'));
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'android_version' => 'nullable|string|max:255',
            'playstore_link' => 'nullable|url|max:2048',
            'ios_version' => 'nullable|string|max:255',
            'app_store_link' => 'nullable|url|max:2048',
        ]);

        $appVersion = AppVersion::query()->latest()->first();

        if ($appVersion) {
            $appVersion->update($validated);

            return redirect()->route('admin.app_versions.index')->with('success', 'App version updated successfully.');
        }

        AppVersion::create($validated);

        return redirect()->route('admin.app_versions.index')->with('success', 'App version created successfully.');
    }
}
