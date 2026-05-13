<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\SupportItemModel;
use App\Http\Controllers\Controller;

class SupportItemController extends Controller
{
    public function index()
    {
        $items = SupportItemModel::orderBy('sort_order')->orderByDesc('id')->get();
        return view('admin.support_items.index', compact('items'));
    }

    public function create()
    {
        return view('admin.support_items.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'value' => 'required|string',
            'type' => 'nullable|string|max:50',
            'icon' => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:2048',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $iconName = null;
        if ($request->hasFile('icon')) {
            $file = $request->file('icon');
            $uploadPath = public_path('uploads/support_items/');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            $iconName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $file->move($uploadPath, $iconName);
        }

        SupportItemModel::create([
            'title' => $request->title,
            'value' => $request->value,
            'type' => $request->type,
            'icon' => $iconName,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => (bool) $request->is_active,
        ]);

        return redirect()->route('support_items.index')->with('success', 'Support item created successfully.');
    }

    public function edit($id)
    {
        $item = SupportItemModel::findOrFail($id);
        return view('admin.support_items.edit', compact('item'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'value' => 'required|string',
            'type' => 'nullable|string|max:50',
            'icon' => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:2048',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $item = SupportItemModel::findOrFail($id);
        $iconName = $item->icon;

        if ($request->hasFile('icon')) {
            $file = $request->file('icon');
            $uploadPath = public_path('uploads/support_items/');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            $iconName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $file->move($uploadPath, $iconName);

            if (!empty($item->icon)) {
                $oldPath = public_path('uploads/support_items/' . $item->icon);
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
        }

        $item->update([
            'title' => $request->title,
            'value' => $request->value,
            'type' => $request->type,
            'icon' => $iconName,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => (bool) $request->is_active,
        ]);

        return redirect()->route('support_items.index')->with('success', 'Support item updated successfully.');
    }

    public function destroy($id)
    {
        $item = SupportItemModel::findOrFail($id);

        if (!empty($item->icon)) {
            $iconPath = public_path('uploads/support_items/' . $item->icon);
            if (file_exists($iconPath)) {
                unlink($iconPath);
            }
        }

        $item->delete();

        return redirect()->route('support_items.index')->with('success', 'Support item deleted successfully.');
    }
}
