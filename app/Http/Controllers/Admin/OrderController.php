<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Orders;
use App\Models\BidModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use App\Models\JobRequestModel;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user() ?? auth('admin')->user();
        $isCompany = $user && $user->is_company;

        if ($request->ajax()) {
            $orders = Orders::query()->with(['user', 'provider']);

            if ($isCompany) {
                $assignedProviderIds = DB::table('users')->where('company_id', $user->id)->pluck('id')->toArray();
                $orders->whereIn('provider_id', $assignedProviderIds);

                if ($request->filled('provider_id')) {
                    if (in_array($request->provider_id, $assignedProviderIds)) {
                        $orders->where('provider_id', $request->provider_id);
                    } else {
                        $orders->whereRaw('1 = 0');
                    }
                }

                if ($request->filled('user_id')) {
                    $orders->where('user_id', $request->user_id);
                }
            } else {
                if ($request->filled('user_id')) {
                    $orders->where('user_id', $request->user_id);
                }

                if ($request->filled('provider_id')) {
                    $orders->where('provider_id', $request->provider_id);
                }
            }

            return DataTables::of($orders)
                ->addIndexColumn()
                ->addColumn('user', function ($row) {
                    return $row->user ? $row->user->name : '-';
                })
                ->addColumn('provider', function ($row) {
                    return $row->provider ? $row->provider->name : '-';
                })
                ->editColumn('price', function ($row) {
                    return 'SAR ' . number_format($row->price ?? 0, 2);
                })
                ->editColumn('created_at', function ($row) {
                    return optional($row->created_at)->format('d F Y');
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
        if ($isCompany) {
            $providers = User::where(function ($q) {
                $q->where('role', '1')->orWhere('has_roles', '1')->orWhere('has_roles', 'like', '%1%');
            })->where('company_id', $user->id)->get();
        } else {
            $providers = User::where(function ($q) {
                $q->where('role', '1')->orWhere('has_roles', '1')->orWhere('has_roles', 'like', '%1%');
            })->get();
        }

        return view('admin.orders.index', compact('users', 'providers', 'isCompany'));
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
