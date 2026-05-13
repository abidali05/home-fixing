<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceCampaignController extends Controller
{
    public function index(Request $request)
    {
        $query = Campaign::query()->with(['product.seller.marketplaceProfile']);

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . trim($request->search) . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('end_date', '<=', $request->date_to);
        }

        $campaigns = $query->latest()->paginate(15)->withQueryString();

        return view('admin.marketplace.campaigns.index', [
            'campaigns' => $campaigns,
            'statuses' => ['active', 'inactive'],
        ]);
    }

    public function create()
    {
        return view('admin.marketplace.campaigns.create', [
            'campaign' => new Campaign(),
            'products' => Product::query()->with('seller.marketplaceProfile')->orderBy('product_name')->get(),
            'statuses' => ['active', 'inactive'],
        ]);
    }

    public function show($id)
    {
        $campaign = Campaign::query()
            ->with(['product.category', 'product.seller.marketplaceProfile'])
            ->findOrFail($id);

        return view('admin.marketplace.campaigns.show', [
            'campaign' => $campaign,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCampaign($request);

        DB::beginTransaction();

        try {
            $campaign = new Campaign();
            $this->fillCampaign($campaign, $request, $validated);
            $campaign->save();

            Product::where('id', $campaign->product_id)->update(['is_campaign' => true]);

            DB::commit();

            return redirect()->route('marketplace.campaigns.index')->with('success', 'Campaign created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to create campaign.')->withInput();
        }
    }

    public function edit($id)
    {
        return view('admin.marketplace.campaigns.edit', [
            'campaign' => Campaign::findOrFail($id),
            'products' => Product::query()->with('seller.marketplaceProfile')->orderBy('product_name')->get(),
            'statuses' => ['active', 'inactive'],
        ]);
    }

    public function update(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);
        $oldProductId = $campaign->product_id;
        $validated = $this->validateCampaign($request, $campaign->id);

        DB::beginTransaction();

        try {
            $this->fillCampaign($campaign, $request, $validated);
            $campaign->save();

            Product::where('id', $campaign->product_id)->update(['is_campaign' => true]);

            if ($oldProductId !== $campaign->product_id) {
                $this->syncProductCampaignFlag($oldProductId);
            }

            DB::commit();

            return redirect()->route('marketplace.campaigns.index')->with('success', 'Campaign updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update campaign.')->withInput();
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $campaign->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Campaign status updated successfully.');
    }

    public function destroy($id)
    {
        $campaign = Campaign::findOrFail($id);
        $productId = $campaign->product_id;
        $campaign->delete();
        $this->syncProductCampaignFlag($productId);

        return back()->with('success', 'Campaign deleted successfully.');
    }

    private function validateCampaign(Request $request, ?int $campaignId = null): array
    {
        return $request->validate([
            'campaign_image' => $campaignId ? 'nullable|image|mimes:jpeg,png,jpg,gif|max:8192' : 'required|image|mimes:jpeg,png,jpg,gif|max:8192',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:active,inactive',
            'product_id' => 'required|exists:products,id',
        ]);
    }

    private function fillCampaign(Campaign $campaign, Request $request, array $validated): void
    {
        if ($request->hasFile('campaign_image')) {
            $campaign->campaign_image = $request->file('campaign_image')->store('campaign_images', 'public');
        }

        $campaign->fill([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
            'product_id' => $validated['product_id'],
        ]);
    }

    private function syncProductCampaignFlag(?int $productId): void
    {
        if (!$productId) {
            return;
        }

        $hasCampaign = Campaign::query()->where('product_id', $productId)->exists();

        Product::where('id', $productId)->update([
            'is_campaign' => $hasCampaign,
        ]);
    }
}
