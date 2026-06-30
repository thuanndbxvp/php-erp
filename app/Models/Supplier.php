<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Nhà cung cấp - Cá nhân hoặc doanh nghiệp.
 *
 * @property int $id
 * @property string $code Mã nhà cung cấp
 * @property string $name Tên nhà cung cấp
 * @property string $supplier_type Loại: INDIVIDUAL | COMPANY
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $tax_code Mã số thuế
 * @property int $payment_term_days Số ngày thanh toán
 * @property string $credit_limit Hạn mức tín dụng (DECIMAL 15,2)
 * @property string $current_ap Công nợ phải trả (DECIMAL 15,2)
 * @property int $lead_time_days Thời gian giao hàng
 * @property string|null $min_order_value Giá trị đơn tối thiểu
 * @property string $status Trạng thái: ACTIVE | INACTIVE | BLOCKED | PENDING
 * @property array<int, string>|null $tags
 */
class Supplier extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierFactory> */
    use HasFactory;

    protected $table = 'suppliers';

    protected $fillable = [
        'code',
        'name',
        'supplier_type',
        'email',
        'phone',
        'website',
        'tax_code',
        'billing_address',
        'shipping_address',
        'payment_term_days',
        'credit_limit',
        'current_ap',
        'lead_time_days',
        'min_order_value',
        'status',
        'notes',
        'tags',
    ];

    protected $casts = [
        'payment_term_days' => 'integer',
        'credit_limit' => 'decimal:2',
        'current_ap' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'lead_time_days' => 'integer',
        'tags' => 'array',
    ];
}