<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $table = 'refunds';

    protected $fillable = [
        'refund_no',
        'order_id',
        'marketplace_order_id',
        'payment_id',
        'customer_id',
        'bank_account_id',
        'amount',
        'currency',
        'status',
        'gateway',
        'gateway_refund_id',
        'bank_reference',
        'failure_reason',
        'admin_notes',
        'requested_at',
        'refunded_at',
        'failed_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'requested_at' => 'datetime',
        'refunded_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function order()
    {
        return $this->belongsTo(Orders::class, 'order_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
