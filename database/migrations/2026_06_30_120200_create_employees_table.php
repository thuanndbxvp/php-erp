<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng Nhân viên (Employees).
     *
     * Lưu thông tin cá nhân + phòng ban + chức vụ + lương + thông tin thuế.
     * BẮT BUỘC có `user_id` (nullable) để map với tài khoản đăng nhập ở bảng `users`.
     * Một nhân viên có thể chưa có user (chưa cấp tài khoản ERP) - vẫn hợp lệ.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Mã nhân viên nội bộ - duy nhất (VD: EMP-00001)
            $table->string('employee_code', 32)
                ->unique()
                ->comment('Mã nhân viên nội bộ (VD: EMP-00001)');

            // ============= THÔNG TIN CÁ NHÂN =============
            $table->string('full_name')
                ->comment('Họ và tên đầy đủ');

            $table->string('email')
                ->nullable()
                ->comment('Email cá nhân / công ty');

            $table->string('phone', 20)
                ->nullable()
                ->comment('Số điện thoại');

            $table->date('date_of_birth')
                ->nullable()
                ->comment('Ngày sinh');

            $table->string('gender', 10)
                ->nullable()
                ->comment('Giới tính (MALE/FEMALE/OTHER)');

            $table->string('id_card_number', 20)
                ->nullable()
                ->comment('Số CMND/CCCD');

            $table->text('address')
                ->nullable()
                ->comment('Địa chỉ thường trú');

            // ============= LIÊN KẾT TÀI KHOẢN =============
            // BẮT BUỘC có field user_id (nullable) để map với users
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Tài khoản đăng nhập (nullable nếu chưa cấp)');

            // ============= CƠ CẤU TỔ CHỨC =============
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete()
                ->comment('Phòng ban hiện tại');

            $table->foreignId('position_id')
                ->nullable()
                ->constrained('positions')
                ->nullOnDelete()
                ->comment('Chức vụ hiện tại');

            // Nhân viên quản lý trực tiếp (manager cấp trên) - self-reference, NULL = không có manager
            $table->foreignId('manager_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete()
                ->comment('Quản lý trực tiếp (self-reference)');

            // ============= LOẠI HÌNH & TRẠNG THÁI =============
            // snapshot string, Model sẽ cast Enum
            $table->string('employee_type', 32)
                ->default('FULLTIME')
                ->comment('FULLTIME/PARTTIME/CONTRACTOR/INTERN/COMMISSION_ONLY');

            $table->string('status', 32)
                ->default('PROBATION')
                ->comment('PROBATION/ACTIVE/ON_LEAVE/SUSPENDED/TERMINATED');

            $table->date('start_date')
                ->comment('Ngày vào làm');

            $table->date('end_date')
                ->nullable()
                ->comment('Ngày nghỉ việc (nếu đã nghỉ)');

            $table->date('probation_end_date')
                ->nullable()
                ->comment('Ngày kết thúc thử việc');

            // ============= LƯƠNG & THUẾ =============
            $table->decimal('base_salary', 15, 2)
                ->default(0)
                ->comment('Lương cơ bản (tháng / giờ / khoán tuỳ salary_type)');

            $table->string('salary_type', 32)
                ->default('MONTHLY')
                ->comment('MONTHLY/HOURLY/PIECE_RATE/COMMISSION_ONLY');

            // Thông tin ngân hàng nhận lương
            $table->string('bank_name')
                ->nullable()
                ->comment('Tên ngân hàng');

            $table->string('bank_account_number', 50)
                ->nullable()
                ->comment('Số tài khoản ngân hàng');

            $table->string('bank_account_holder')
                ->nullable()
                ->comment('Chủ tài khoản');

            // Mã số thuế cá nhân (MST) - quan trọng để tính personal tax
            $table->string('tax_code', 20)
                ->nullable()
                ->comment('Mã số thuế cá nhân');

            // Số người phụ thuộc (giảm trừ gia cảnh thuế TNCN)
            $table->unsignedTinyInteger('dependents_count')
                ->default(0)
                ->comment('Số người phụ thuộc (giảm trừ thuế TNCN)');

            // ============= ẢNH ĐẠI DIỆN =============
            $table->string('avatar_path')
                ->nullable()
                ->comment('Đường dẫn ảnh đại diện');

            $table->timestamps();

            // Index phục vụ truy vấn
            $table->index('employee_type', 'employees_type_idx');
            $table->index('status', 'employees_status_idx');
            $table->index('department_id', 'employees_department_idx');
            $table->index('position_id', 'employees_position_idx');
            $table->index('manager_id', 'employees_manager_idx');
            $table->index('start_date', 'employees_start_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
