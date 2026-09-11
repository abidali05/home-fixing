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

        // 1. Determine rates: prefer snapshotted order values over global settings
        $customerAppFee = $this->customer_app_fee !== null ? (float) $this->customer_app_fee : (float) ($settings->customer_app_fee ?? 3.00);
        $azhlFeeSetting = $this->azhl_fee !== null ? (float) $this->azhl_fee : (float) ($settings->azhl_fee ?? $settings->azhl_percentage ?? 5.00);
        $azhlPctSetting = $this->azhl_percentage !== null ? (float) $this->azhl_percentage : (float) ($settings->azhl_percentage ?? 5.00);
        $gatewayFeePct = ($this->gateway_fee_percentage !== null && (float) $this->gateway_fee_percentage > 0)
            ? (float) $this->gateway_fee_percentage
            : (float) ($settings->payment_gateway_fee_percentage ?? 2.50);
        $gatewayVatPct = ($this->gateway_vat_percentage !== null && (float) $this->gateway_vat_percentage > 0)
            ? (float) $this->gateway_vat_percentage
            : (float) ($settings->payment_gateway_vat_percentage ?? 15.00);

        // 2. Base repair price
        $repairPrice = (float) ($this->price ?? 0);
        if (!empty($this->job_id)) {
            $acceptedBid = \App\Models\BidModel::where('job_id', $this->job_id)->whereIn('status', ['accepted', 'completed', 'hired'])->first();
            if ($acceptedBid && (float) $acceptedBid->price > 0) {
                $repairPrice = (float) $acceptedBid->price;
            }
        }

        if ($repairPrice > 103) {
            $approxSubtotal = $repairPrice / (1 + ($gatewayFeePct / 100) * (1 + $gatewayVatPct / 100));
            $estimatedRepair = max(0, $approxSubtotal - $customerAppFee);
            $repairPrice = abs($estimatedRepair - round($estimatedRepair)) < 0.1 ? (float) round($estimatedRepair) : (float) round($estimatedRepair, 2);
        }

        // 3. Extra charges (applicable unless explicitly rejected)
        $extraAmount = (float) ($this->extra_amount ?? 0);
        $applicableExtra = ($this->extra_amount_status !== 'rejected' && $extraAmount > 0) ? $extraAmount : 0.00;
        $finalBase = $repairPrice + $applicableExtra;
        $subtotal = $finalBase + $customerAppFee;

        // 4. Gateway Fees & VAT (No fixed fee)
        $gatewaySubtotal = $subtotal * ($gatewayFeePct / 100);
        $gatewayVat = $gatewaySubtotal * ($gatewayVatPct / 100);
        $totalGatewayFee = $gatewaySubtotal + $gatewayVat;

        // 5. Provider Net Payout
        $azhlFee = $azhlFeeSetting;
        $netAmount = max(0, $finalBase - $azhlFee - $totalGatewayFee);

        // 6. Assign snapshotted fields on Order
        $this->price = number_format($repairPrice, 2, '.', '');
        $this->customer_app_fee = number_format($customerAppFee, 2, '.', '');
        $this->azhl_percentage = number_format($azhlPctSetting, 2, '.', '');
        $this->azhl_fee = number_format($azhlFee, 2, '.', '');
        $this->gateway_fee_percentage = number_format($gatewayFeePct, 2, '.', '');
        $this->gateway_vat_percentage = number_format($gatewayVatPct, 2, '.', '');
        $this->gateway_fee = number_format($totalGatewayFee, 2, '.', '');
        $this->gateway_vat = number_format($gatewayVat, 2, '.', '');
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
            'gateway_fee' => (float) number_format($totalGatewayFee, 2, '.', ''),
            'gateway_vat' => (float) number_format($gatewayVat, 2, '.', ''),
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
}
