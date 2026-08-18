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
        return Orders::where('provider_id', $this->id)->where('status', 'completed')->sum('price') ?? 0;
    }
    public function getPaymentDueAttribute()
    {
        $count = Orders::where('provider_id', $this->id)->where('status', 'completed')->where('paid_to_system', '0')->count();
        return $count * 5;
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
