<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Admin\ServiceCategoryModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['cityname', 'total_orders', 'service', 'rating', 'total_earnings', 'payment_due'];
    protected $guarded = [];

    protected $table = 'users';


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'service_category' => 'array',
            'delivery_charges' => 'float',
        ];
    }

    public function getCitynameAttribute()
    {
        return CityModel::find($this->city_id)->name ?? 'No City';
    }

    public function reviews()
    {
        return $this->hasMany(Reviews::class, 'provider_id')->orderBy('id', 'desc');
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategoryModel::class, 'service_category');
    }


    public function skills()
    {
        return $this->hasMany(ProviderSkills::class, 'user_id');
    }

    public function providerProfile()
    {
        return $this->hasOne(ProviderProfile::class);
    }

    public function marketplaceProfile()
    {
        return $this->hasOne(MarketplaceProfile::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'user_id');
    }

    public function marketplaceOrders()
    {
        return $this->hasMany(MarketplaceOrder::class, 'user_id');
    }

    public function hasStoredRole(string $role): bool
    {
        $roles = array_filter(array_map('trim', explode(',', (string) ($this->has_roles ?? ''))));

        return (string) $this->role === $role || in_array($role, $roles, true);
    }

    public function getWorkHourStartAttribute($value)
    {
        return date('h:i A', strtotime($value));
    }

    public function getWorkHourEndAttribute($value)
    {
        return date('h:i A', strtotime($value));
    }

    public function getTotalOrdersAttribute()
    {
        if ((string) $this->role === '2') {
            return \App\Models\MarketplaceOrderItem::where('shop_id', $this->id)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'completed');
                })->count();
        }

        return Orders::where('provider_id', $this->id)->where('status', 'completed')->count();
    }

    public function getServiceAttribute()
    {
        if (is_array($this->service_category)) {
            return ServiceCategoryModel::whereIn('id', $this->service_category)->get();
        }

        return $this->service_category
            ? ServiceCategoryModel::find($this->service_category)
            : null;
    }

    public function getRatingAttribute()
    {
        $reviews = $this->reviews;
        if ($reviews->isEmpty()) {
            return 0;
        }
        $totalRating = $reviews->sum('rating');
        return round($totalRating / $reviews->count(), 1);
    }

    public function getTotalEarningsAttribute()
    {
        $settings = Admin\SystemSettingModel::first();
        $azhlPercentage = (float) ($settings->azhl_percentage ?? 10.00);
        $customerAppFee = (float) ($settings->customer_app_fee ?? 3.00);
        $gatewayFeePct = (float) ($settings->payment_gateway_fee_percentage ?? 2.50);
        $gatewayFixedFee = (float) ($settings->payment_gateway_fixed_fee ?? 1.00);
        $gatewayVatPct = (float) ($settings->payment_gateway_vat_percentage ?? 15.00);

        if ((string) $this->role === '2') {
            $items = \App\Models\MarketplaceOrderItem::where('shop_id', $this->id)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'completed');
                })->get();

            $total = 0.0;
            foreach ($items as $item) {
                $itemPrice = (float) ($item->total_price ?? 0);
                if ($itemPrice <= 0) {
                    $itemPrice = (float) ($item->base_price ?? 0) * (int) ($item->quantity ?? 1);
                }
                $total += $itemPrice;
            }
            return (float) number_format($total, 2, '.', '');
        }

        $completedOrders = Orders::where('provider_id', $this->id)->where('status', 'completed')->get();
        $orderEarnings = 0.0;

        foreach ($completedOrders as $ord) {
            $repairPrice = (float) ($ord->price ?? 0);
            if (!empty($ord->job_id)) {
                $bid = BidModel::where('job_id', $ord->job_id)->whereIn('status', ['accepted', 'completed', 'hired'])->first();
                if ($bid && (float) $bid->price > 0) {
                    $repairPrice = (float) $bid->price;
                }
            }

            if ($repairPrice > 103) {
                $approxSubtotal = ($repairPrice - $gatewayFixedFee * (1 + $gatewayVatPct / 100)) / (1 + ($gatewayFeePct / 100) * (1 + $gatewayVatPct / 100));
                $estimatedRepair = max(0, $approxSubtotal - $customerAppFee);
                $repairPrice = abs($estimatedRepair - round($estimatedRepair)) < 0.1 ? (float) round($estimatedRepair) : (float) round($estimatedRepair, 2);
            }

            $commission = $repairPrice * ($azhlPercentage / 100);
            $net = max(0, $repairPrice - $commission);
            $orderEarnings += $net;
        }

        $referralEarnings = (float) ReferralReward::where('referrer_id', $this->id)->sum('reward_amount');
        $totalEarnings = $orderEarnings + $referralEarnings;

        return (float) number_format($totalEarnings, 2, '.', '');
    }

    /**
     * Deprecated: paid_to_system Commission Hold system is obsolete.
     * All payments are collected online by AZHL and net earnings are credited to user wallets.
     */
    public function getPaymentDueAttribute()
    {
        return 0;
    }

    public function routeNotificationForFcm(): ?string
    {
        return !empty($this->fcm_token) ? $this->fcm_token : null;
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(JobNotification::class, 'notifiable')->latest();
    }

    public function readNotifications(): MorphMany
    {
        return $this->notifications()->whereNotNull('read_at');
    }

    public function unreadNotifications(): MorphMany
    {
        return $this->notifications()->whereNull('read_at');
    }
}
