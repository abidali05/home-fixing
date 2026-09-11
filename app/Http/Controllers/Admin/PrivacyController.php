<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Privacy;

class PrivacyController extends Controller
{
    public function index()
    {
        $privacyPolicies = Privacy::all();
        return view('admin.privacy.index', compact('privacyPolicies'));
    }

    public function create()
    {
        return view('admin.privacy.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'role' => 'required|in:0,1,2',
            'content' => 'required|string',
        ]);

        Privacy::create($request->all());

        return redirect()->route('admin.privacy.index')->with('success', 'Privacy policy created successfully.');
    }

    public function edit(Privacy $privacy)
    {
        return view('admin.privacy.edit', compact('privacy'));
    }

    public function update(Request $request, Privacy $privacy)
    {
        $request->validate([
            'role' => 'required|in:0,1,2',
            'content' => 'required|string',
        ]);

        $privacy->update($request->all());

        return redirect()->route('admin.privacy.index')->with('success', 'Privacy policy updated successfully.');
    }

    public function destroy(Privacy $privacy)
    {
        $privacy->delete();

        return redirect()->route('admin.privacy.index')->with('success', 'Privacy policy deleted successfully.');
    }
}
