<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CampaignController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'campaign_image' => 'required|file',
            'title' => 'required|string',
            'subtitle' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'product_id' => 'required|integer|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $campaignImagePath = $request->file('campaign_image')->store('campaign_images', 'public');

        $campaign = Campaign::create([
            'campaign_image' => $campaignImagePath,
            'title' => $request->title,
            'subtitle' => $request->subtitle,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => 'inactive',
            'product_id' => $request->product_id,
        ]);

        Product::where('id', $request->product_id)->update([
            'is_campaign' => true
        ]);

        return response()->json([
            'message' => 'Campaign created successfully',
            'campaign' => $campaign
        ], 201);
    }

    public function index()
    {
        $userId = auth()->id();

        $campaigns = Campaign::with('product')
            ->whereHas('product', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->get()
            ->map(function ($campaign) {
                return [
                    'id' => $campaign->id,
                    'campaign_image' => $campaign->campaign_image,
                    'title' => $campaign->title,
                    'subtitle' => $campaign->subtitle,
                    'start_date' => $campaign->start_date,
                    'end_date' => $campaign->end_date,
                    'status' => $campaign->status,
                    'product_id' => $campaign->product_id,
                    'product' => $campaign->product,
                    'total_visits_on_product_via_campaign' => ProductView::query()
                        ->where('campaign_id', $campaign->id)
                        ->where('is_through_campaign', true)
                        ->sum('view_count'),
                    'total_buy_count_via_campaign' => $campaign->total_buy_count_via_campaign ?? 0,
                    'total_sale_amount_via_campaign' => $campaign->total_sale_amount_via_campaign ?? 0,
                ];
            });

        return response()->json(['success' => true, 'data' => $campaigns]);
    }

    public function activeCampaigns()
    {
        $campaigns = Campaign::with(['product.seller.marketplaceProfile'])
            ->where('status', 'active')
            ->get()
            ->map(function ($campaign) {
                $product = $campaign->product;
                if ($product) {
                    $seller = $product->seller;
                    if ($seller) {
                        $shop = $seller->marketplaceProfile;
                        if ($shop) {
                            if ($shop->shop_logo && !str_starts_with($shop->shop_logo, 'http')) {
                                $shop->shop_logo = asset('uploads/shop_logos/' . $shop->shop_logo);
                            }
                            if ($shop->shop_banner_image && !str_starts_with($shop->shop_banner_image, 'http')) {
                                $shop->shop_banner_image = asset('uploads/shop_banners/' . $shop->shop_banner_image);
                            }
                        }
                        $product->setAttribute('shop', $shop);
                    } else {
                        $product->setAttribute('shop', null);
                    }

                    if ($product->banner_image && !str_starts_with($product->banner_image, 'http')) {
                        $product->banner_image = asset('storage/' . $product->banner_image);
                    }
                    if ($product->product_images) {
                        $images = is_array($product->product_images) ? $product->product_images : json_decode($product->product_images, true);
                        if (is_array($images)) {
                            $formattedImages = [];
                            foreach ($images as $img) {
                                $formattedImages[] = !str_starts_with($img, 'http') ? asset('storage/' . $img) : $img;
                            }
                            $product->product_images = $formattedImages;
                        }
                    }
                }

                $campaignImage = $campaign->campaign_image;
                if ($campaignImage && !str_starts_with($campaignImage, 'http')) {
                    $campaignImage = asset('storage/' . $campaignImage);
                }

                return [
                    'id' => $campaign->id,
                    'campaign_image' => $campaignImage,
                    'title' => $campaign->title,
                    'subtitle' => $campaign->subtitle,
                    'start_date' => $campaign->start_date,
                    'end_date' => $campaign->end_date,
                    'status' => $campaign->status,
                    'product_id' => $campaign->product_id,
                    'product' => $product,
                    'total_visits_on_product_via_campaign' => ProductView::query()
                        ->where('campaign_id', $campaign->id)
                        ->where('is_through_campaign', true)
                        ->sum('view_count'),
                    'total_buy_count_via_campaign' => $campaign->total_buy_count_via_campaign ?? 0,
                    'total_sale_amount_via_campaign' => $campaign->total_sale_amount_via_campaign ?? 0,
                ];
            });

        return response()->json(['success' => true, 'data' => $campaigns]);
    }
}
