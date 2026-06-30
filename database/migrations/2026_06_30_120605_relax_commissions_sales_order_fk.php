<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cho phép sales_order_id nullable để hỗ trợ các trường hợp
     * hoa hồng được tạo thủ công (bonus, điều chỉnh, hoa hồng thưởng thêm...)
     * mà không gắn với 1 đơn bán cụ thể.
     *
     * Khi có sales_order_id: hoa hồng tự động từ SalesOrderObserver.
     * Khi NULL: hoa hồng manual (HR/admin tạo).
     *
     * Implementation: SQLite không hỗ trợ ALTER COLUMN trực tiếp qua doctrine,
     * nên dùng cách portable: tạo bảng tạm, copy data, swap.
     */
    public function up(): void
    {
        // Cách 1 (SQLite-friendly): recreate cột thông qua raw SQL + rebuild FK
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: bật FK check, tạo bảng mới, copy, swap, bật FK lại
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::create('commissions_new', function ($table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('sales_order_id')->nullable();
                $table->unsignedBigInteger('rule_id');
                $table->decimal('order_amount', 15, 2);
                $table->decimal('target_value', 15, 2)->default(0);
                $table->decimal('commission_amount', 15, 2);
                $table->string('status', 32)->default('PENDING');
                $table->date('earned_date');
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('payslip_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            DB::statement('INSERT INTO commissions_new SELECT * FROM commissions');

            Schema::drop('commissions');
            Schema::rename('commissions_new', 'commissions');

            // Recreate FK + indexes
            DB::statement('CREATE INDEX commissions_employee_idx ON commissions(employee_id)');
            DB::statement('CREATE INDEX commissions_so_idx ON commissions(sales_order_id)');
            DB::statement('CREATE INDEX commissions_rule_idx ON commissions(rule_id)');
            DB::statement('CREATE INDEX commissions_status_idx ON commissions(status)');
            DB::statement('CREATE INDEX commissions_earned_date_idx ON commissions(earned_date)');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            // MySQL/PG: dùng doctrine style change (yêu cầu doctrine/dbal)
            Schema::table('commissions', function ($table) {
                $table->dropForeign(['sales_order_id']);
                $table->foreignId('sales_order_id')->nullable()->change();
                $table->foreign('sales_order_id')
                    ->references('id')->on('sales_orders')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::create('commissions_new', function ($table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('sales_order_id');
                $table->unsignedBigInteger('rule_id');
                $table->decimal('order_amount', 15, 2);
                $table->decimal('target_value', 15, 2)->default(0);
                $table->decimal('commission_amount', 15, 2);
                $table->string('status', 32)->default('PENDING');
                $table->date('earned_date');
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('payslip_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            DB::statement('INSERT INTO commissions_new SELECT * FROM commissions');

            Schema::drop('commissions');
            Schema::rename('commissions_new', 'commissions');

            DB::statement('CREATE INDEX commissions_employee_idx ON commissions(employee_id)');
            DB::statement('CREATE INDEX commissions_so_idx ON commissions(sales_order_id)');
            DB::statement('CREATE INDEX commissions_rule_idx ON commissions(rule_id)');
            DB::statement('CREATE INDEX commissions_status_idx ON commissions(status)');
            DB::statement('CREATE INDEX commissions_earned_date_idx ON commissions(earned_date)');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('commissions', function ($table) {
                $table->dropForeign(['sales_order_id']);
                $table->foreignId('sales_order_id')->nullable(false)->change();
                $table->foreign('sales_order_id')
                    ->references('id')->on('sales_orders')->restrictOnDelete();
            });
        }
    }
};