<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot giữa Payment ↔ Invoice (Out hoặc In).
 *
 * Mỗi row biểu diễn: "Payment P đã áp dụng số X vào InvoiceOut/InvoiceIn I lúc T".
 *
 * - (payment_id, invoice_out_id) là UNIQUE
 * - (payment_id, invoice_in_id)  là UNIQUE
 *
 * 1 Payment có nhiều row (nếu thanh toán nhiều invoice).
 * 1 Invoice có nhiều row (nếu nhận nhiều đợt thanh toán).
 */
class PaymentApplication extends Model
{
    use HasFactory;

    protected $table = 'payment_applications';

    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'invoice_out_id',
        'invoice_in_id',
        'amount_applied',
        'applied_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_applied' => 'decimal:2',
            'applied_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoiceOut(): BelongsTo
    {
        return $this->belongsTo(InvoiceOut::class);
    }

    public function invoiceIn(): BelongsTo
    {
        return $this->belongsTo(InvoiceIn::class);
    }
}
