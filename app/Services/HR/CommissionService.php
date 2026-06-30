<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Enums\CommissionStatus;
use App\Enums\TargetType;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ Hoa hồng phát sinh (Commission).
 *
 * - calculateForSalesOrder(): gọi khi SO chuyển sang SHIPPED / COMPLETED.
 *   Tự động chọn rule phù hợp theo target_type, kiểm tra min_target_amount,
 *   áp rate_percent × target_value, cap bởi max_commission_amount (nếu có).
 *   KHÔNG tạo record nếu SO chưa có sales_person_id (không gán sale).
 * - approve/paid/reverse/cancel: state machine + audit timestamps.
 * - bulkApproveForPayroll(): chốt tất cả commission PENDING của 1 NV trong 1 tháng
 *   → APPROVED để chuẩn bị gộp vào Payslip.
 */
class CommissionService
{
    public function __construct(
        private readonly CommissionRuleService $ruleService,
    ) {}

    /**
     * Tính & lưu commission khi SO đạt trạng thái "đã giao".
     * Idempotent: nếu đã có commission với (employee_id, sales_order_id, rule_id) → bỏ qua.
     */
    public function calculateForSalesOrder(SalesOrder $order): ?Commission
    {
        // SO chưa có sale → không tính
        if (! $order->sales_person_id) {
            return null;
        }

        $employee = Employee::find($order->sales_person_id);
        if (! $employee) {
            return null;
        }

        $orderAmount = (string) ($order->total_amount ?? '0');
        if ((float) $orderAmount <= 0) {
            return null;
        }

        // Lấy rule đang active (hiệu lực tại ngày của SO)
        $dateRef = $order->ship_date?->toDateString() ?? $order->order_date?->toDateString() ?? now()->toDateString();

        /** @var \Illuminate\Database\Eloquent\Collection<int, CommissionRule> $rules */
        $rules = $this->ruleService->activeRules($dateRef);
        if ($rules->isEmpty()) {
            return null;
        }

        // Mặc định: lấy rule có rate_percent CAO NHẤT (ưu tiên khuyến khích).
        // Có thể thay đổi logic nếu cần theo nhiều target_type.
        $rule = $rules->first();

        // Tính target_value dựa trên target_type
        $targetValue = $this->resolveTargetValue($rule, $order);
        if ((float) $targetValue <= 0) {
            return null;
        }

        // Áp rate × target
        $commission = bcmul($targetValue, bcdiv((string) $rule->rate_percent, '100', 4), 2);
        if ((float) $commission <= 0) {
            return null;
        }

        // Cap bởi max_commission_amount
        if ($rule->max_commission_amount && (float) $rule->max_commission_amount > 0
            && (float) $commission > (float) $rule->max_commission_amount) {
            $commission = (string) $rule->max_commission_amount;
        }

        return DB::transaction(function () use ($employee, $order, $orderAmount, $rule, $targetValue, $commission, $dateRef) {
            // Idempotent check
            $exists = Commission::where('employee_id', $employee->id)
                ->where('sales_order_id', $order->id)
                ->where('rule_id', $rule->id)
                ->exists();
            if ($exists) {
                return null;
            }

            return Commission::create([
                'employee_id' => $employee->id,
                'sales_order_id' => $order->id,
                'rule_id' => $rule->id,
                'order_amount' => $orderAmount,
                'target_value' => $targetValue,
                'commission_amount' => $commission,
                'status' => CommissionStatus::PENDING->value,
                'earned_date' => $dateRef,
                'notes' => "Auto-calc từ SO {$order->order_number}",
            ]);
        });
    }

    /**
     * Phê duyệt 1 commission (PENDING → APPROVED).
     */
    public function approve(Commission $commission): Commission
    {
        if ($commission->status !== CommissionStatus::PENDING) {
            throw ValidationException::withMessages([
                'status' => "Commission không ở PENDING (hiện: {$commission->status->label()}).",
            ]);
        }
        $commission->status = CommissionStatus::APPROVED;
        $commission->approved_at = now();
        $commission->save();

        return $commission->fresh();
    }

    /**
     * Đánh dấu PAID (sau khi Payroll gộp vào payslip).
     */
    public function markPaid(Commission $commission, int $payslipId): Commission
    {
        if ($commission->status !== CommissionStatus::APPROVED) {
            throw ValidationException::withMessages([
                'status' => "Commission chưa APPROVED (hiện: {$commission->status->label()}).",
            ]);
        }
        $commission->status = CommissionStatus::PAID;
        $commission->paid_at = now();
        $commission->payslip_id = $payslipId;
        $commission->save();

        return $commission->fresh();
    }

    /**
     * Đảo ngược (REVERSED) - khi SO bị huỷ sau khi đã generate commission.
     */
    public function reverse(Commission $commission, ?string $notes = null): Commission
    {
        if ($commission->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => "Commission ở trạng thái terminal, không thể đảo ngược.",
            ]);
        }
        $commission->status = CommissionStatus::REVERSED;
        if ($notes) {
            $commission->notes = ($commission->notes ?? '') . "\n[REVERSED] " . $notes;
        }
        $commission->save();

        return $commission->fresh();
    }

    public function cancel(Commission $commission, ?string $notes = null): Commission
    {
        if ($commission->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => "Commission ở trạng thái terminal, không thể huỷ.",
            ]);
        }
        $commission->status = CommissionStatus::CANCELLED;
        if ($notes) {
            $commission->notes = ($commission->notes ?? '') . "\n[CANCELLED] " . $notes;
        }
        $commission->save();

        return $commission->fresh();
    }

    /**
     * Bulk: approve tất cả commission PENDING của (employee, year, month).
     * Trả về số record được approve.
     */
    public function bulkApproveForMonth(int $employeeId, int $year, int $month): int
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = date('Y-m-t', strtotime($from));

        return Commission::where('employee_id', $employeeId)
            ->whereBetween('earned_date', [$from, $to])
            ->where('status', CommissionStatus::PENDING->value)
            ->update([
                'status' => CommissionStatus::APPROVED->value,
                'approved_at' => now(),
            ]);
    }

    /**
     * Resolve target_value tuỳ theo target_type của rule.
     *
     * @return string (decimal string, 2 chữ số)
     */
    private function resolveTargetValue(CommissionRule $rule, SalesOrder $order): string
    {
        return match ($rule->target_type) {
            // Doanh thu sau CK (subtotal - discount_amount)
            TargetType::REVENUE => (string) bcsub(
                (string) ($order->subtotal ?? '0'),
                (string) ($order->discount_amount ?? '0'),
                2,
            ),
            // Số đơn - luôn = 1 cho mỗi SO
            TargetType::ORDER_COUNT => '1',
            // Lợi nhuận = doanh thu - giá vốn (tổng_cost)
            TargetType::PROFIT => (string) bcsub(
                (string) ($order->total_amount ?? '0'),
                (string) ($order->total_cost ?? '0'),
                2,
            ),
            // Số tiền thu hộ (collected) - placeholder: lấy paid_amount của InvoiceOut (nếu có)
            // Tạm thời dùng total_amount cho đến khi InvoiceOut được tính toàn hệ thống
            TargetType::COLLECTED_AMT => (string) ($order->total_amount ?? '0'),
            // Khách hàng mới - không extract được từ SO (cần pipeline riêng)
            // → fallback = 1 (mỗi SO là 1 khách hàng mới nếu chưa từng mua)
            TargetType::NEW_CUSTOMER => $this->isNewCustomer($order) ? '1' : '0',
        };
    }

    private function isNewCustomer(SalesOrder $order): bool
    {
        if (! $order->customer_id) {
            return false;
        }
        // Khách hàng "mới" = chưa có SO trước đó (older than order_date)
        return ! \App\Models\SalesOrder::where('customer_id', $order->customer_id)
            ->where('id', '!=', $order->id)
            ->where('order_date', '<', $order->order_date)
            ->exists();
    }
}
