<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    protected $table = 'orders';
    protected $guarded = [];

    protected $casts = [
        'extra_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'customer_app_fee' => 'decimal:2',
        'azhl_percentage' => 'decimal:2',
        'azhl_fee' => 'decimal:2',
        'gateway_fee_percentage' => 'decimal:2',
        'gateway_vat_percentage' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'gateway_vat' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    /**
     * Compute and snapshot financial breakdown for this order.
     * Uses snapshotted order rates if already saved, otherwise pulls current system settings.
     */
    public function calculateAndSyncFinancials(bool $save = false): array
    {
        $settings = \App\Models\Admin\SystemSettingModel::first();

        // 1. Determine rates: live settings in system_settings take priority, fallback to order snapshot or 0.00
        $customerAppFee = ($settings && $settings->customer_app_fee !== null)
            ? (float) $settings->customer_app_fee
            : ($this->customer_app_fee !== null ? (float) $this->customer_app_fee : 0.00);
        $azhlPctSetting = ($settings && $settings->azhl_percentage !== null)
            ? (float) $settings->azhl_percentage
            : ($this->azhl_percentage !== null ? (float) $this->azhl_percentage : 10.00);

        // 2. Base repair price
        $repairPrice = (float) ($this->price ?? 0);
        if (!empty($this->job_id)) {
            $acceptedBid = \App\Models\BidModel::where('job_id', $this->job_id)->whereIn('status', ['accepted', 'completed', 'hired'])->first();
            if ($acceptedBid && (float) $acceptedBid->price > 0) {
                $repairPrice = (float) $acceptedBid->price;
            }
        }

        // 3. Extra charges (applicable unless explicitly rejected)
        $extraAmount = (float) ($this->extra_amount ?? 0);
        $applicableExtra = ($this->extra_amount_status !== 'rejected' && $extraAmount > 0) ? $extraAmount : 0.00;
        $finalBase = $repairPrice + $applicableExtra;
        $subtotal = $finalBase + $customerAppFee;

        // 4. Taxes & Gateway Fees removed (Set to 0)
        $totalGatewayFee = 0.00;
        $gatewayVat = 0.00;

        // 5. Provider Net Payout: Final Base minus 1 percentage (Azhl Commission)
        $azhlFee = round($finalBase * ($azhlPctSetting / 100), 2);
        $netAmount = max(0, round($finalBase - $azhlFee, 2));

        // 6. Assign snapshotted fields on Order
        $this->price = number_format($repairPrice, 2, '.', '');
        $this->customer_app_fee = number_format($customerAppFee, 2, '.', '');
        $this->azhl_percentage = number_format($azhlPctSetting, 2, '.', '');
        $this->azhl_fee = number_format($azhlFee, 2, '.', '');
        $this->gateway_fee_percentage = '0.00';
        $this->gateway_vat_percentage = '0.00';
        $this->gateway_fee = '0.00';
        $this->gateway_vat = '0.00';
        $this->net_amount = number_format($netAmount, 2, '.', '');
        $this->total_amount = number_format($subtotal, 2, '.', '');

        if ($save) {
            $this->save();
        }

        return [
            'repair_price' => (float) number_format($repairPrice, 2, '.', ''),
            'extra_amount' => (float) number_format($extraAmount, 2, '.', ''),
            'extra_amount_status' => (string) ($this->extra_amount_status ?? 'none'),
            'accepted_extra' => (float) number_format($applicableExtra, 2, '.', ''),
            'final_base_price' => (float) number_format($finalBase, 2, '.', ''),
            'customer_app_fee' => (float) number_format($customerAppFee, 2, '.', ''),
            'subtotal' => (float) number_format($subtotal, 2, '.', ''),
            'gateway_fee' => 0.00,
            'gateway_vat' => 0.00,
            'customer_total' => (float) number_format($subtotal, 2, '.', ''),
            'azhl_percentage' => (float) number_format($azhlPctSetting, 2, '.', ''),
            'azhl_fee' => (float) number_format($azhlFee, 2, '.', ''),
            'net_amount' => (float) number_format($netAmount, 2, '.', ''),
        ];
    }

    /**
     * Get final base price (original price + accepted extra amount)
     */
    public function getFinalBasePriceAttribute(): float
    {
        $base = (float) ($this->price ?? 0);
        if ($this->extra_amount_status !== 'rejected') {
            $base += (float) ($this->extra_amount ?? 0);
        }
        return round($base, 2);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }
    public function job()
    {
        return $this->belongsTo(JobRequestModel::class, 'job_id');
    }

    public function tracking()
    {
        return $this->hasMany(OrderTracking::class, 'order_id');
    }
}
