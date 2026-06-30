<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Khách hàng - Cá nhân hoặc doanh nghiệp.
 *
 * @property int $id
 * @property string $code Mã khách hàng
 * @property string $name Tên khách hàng
 * @property string $customer_type Loại: INDIVIDUAL | COMPANY
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $tax_code Mã số thuế
 * @property string $credit_limit Hạn mức công nợ (DECIMAL 15,2)
 * @property string $current_ar Công nợ hiện tại (DECIMAL 15,2)
 * @property int $payment_term_days Số ngày được nợ
 * @property string $status Trạng thái: ACTIVE | INACTIVE | BLOCKED | PENDING
 * @property array<int, string>|null $tags
 */
class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;

    protected $table = 'customers';

    protected $fillable = [
        'code',
        'name',
        'customer_type',
        'email',
        'phone',
        'website',
        'tax_code',
        'business_reg_num',
        'billing_address',
        'shipping_address',
        'credit_limit',
        'current_ar',
        'payment_term_days',
        'status',
        'blocked_reason',
        'notes',
        'tags',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'current_ar' => 'decimal:2',
        'payment_term_days' => 'integer',
        'tags' => 'array',
    ];
}