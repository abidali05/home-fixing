<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TermsCondition;

class TermsConditionController extends Controller
{
    public function index()
    {
        $termsConditions = TermsCondition::all();
        return view('admin.terms_conditions.index', compact('termsConditions'));
    }

    public function create()
    {
        return view('admin.terms_conditions.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'role'    => 'required|in:0,1,2',
            'content' => 'required|string',
        ]);

        TermsCondition::create($request->all());

        return redirect()->route('admin.terms_conditions.index')->with('success', 'Terms & Conditions created successfully.');
    }

    public function edit(TermsCondition $terms_condition)
    {
        return view('admin.terms_conditions.edit', compact('terms_condition'));
    }

    public function update(Request $request, TermsCondition $terms_condition)
    {
        $request->validate([
            'role'    => 'required|in:0,1,2',
            'content' => 'required|string',
        ]);

        $terms_condition->update($request->all());

        return redirect()->route('admin.terms_conditions.index')->with('success', 'Terms & Conditions updated successfully.');
    }

    public function destroy(TermsCondition $terms_condition)
    {
        $terms_condition->delete();

        return redirect()->route('admin.terms_conditions.index')->with('success', 'Terms & Conditions deleted successfully.');
    }
}
