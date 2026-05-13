<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Orders;
use App\Models\BidModel;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use App\Models\JobRequestModel;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            // Start with an empty query builder
            $orders = Orders::query();

            // Only add relationships and conditions if we're filtering
            if ($request->filled('user_id') || $request->filled('provider_id')) {
                $orders->with(['user', 'provider']);

                if ($request->filled('user_id')) {
                    $orders->where('user_id', $request->user_id);
                }

                if ($request->filled('provider_id')) {
                    $orders->where('provider_id', $request->provider_id);
                }
            } else {
                // Return empty results if no filters are applied
                return DataTables::of([])->make(true);
            }

            return DataTables::of($orders)
                ->addIndexColumn()
                ->addColumn('user', function ($row) {
                    return $row->user ? $row->user->name : '-';
                })
                ->addColumn('provider', function ($row) {
                    return $row->user ? $row->provider->name : '-';
                })
                ->editColumn('created_at', function ($row) {
                    return $row->created_at->format('d F Y');
                })
                ->editColumn('source', function ($row) {
                    return $row->source == 'bid' ? 'Bid' : 'Direct Hiring';
                })
                ->editColumn('status', function ($row) {
                    return ucfirst($row->status);
                })
                ->addColumn('action', function ($row) {
                    return '<a href="' . route('orders.details', $row->id) . '" class="btn btn-sm btn-info">View</a>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $users = User::where('role', '0')->where('status', 'active')->get();
        $providers = User::where('role', '1')->where('status', 'active')->get();

        return view('admin.orders.index', compact('users', 'providers'));
    }

    public function details($id)
    {
        $order = Orders::with('user', 'provider')->where('id', $id)->first();
        if(!$order) {
            return back()->with('error', 'Order not found.');
        }
        $job = null;
        if($order->source == 'bid') {
            $job = JobRequestModel::with('user','category')->where('id', $order->job_id)->first();
        }

        return view('admin.orders.details', compact('order', 'job'));
    }
}
