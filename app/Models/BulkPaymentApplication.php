<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chi tiết BulkPayment → các Invoice được gom.
 */
class BulkPaymentApplication extends Model
{
    use HasFactory;

    protected $table = 'bulk_payment_applications';

    public $timestamps = false;

    protected $fillable = [
        'bulk_payment_id',
        'invoice_out_id',
        'invoice_in_id',
        'amount_applied',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_applied' => 'decimal:2',
        ];
    }

    public function bulkPayment(): BelongsTo
    {
        return $this->belongsTo(BulkPayment::class);
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
