<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng giao dịch ngân hàng thực tế (Sao kê / Bank Statement).
     *
     * Phân biệt so với payments:
     *   - bank_transactions là dòng dữ liệu THÔ từ sao kê NH (import MT940/CSV)
     *   - payments là khoản tiền ERP ghi nhận từ phía khách hàng / NCC
     *   - 1 giao dịch NH có thể tương ứng 0..N payment (qua PaymentApplication).
     *
     * Quy ước amount:
     *   - DEPOSIT / TRANSFER_IN / INTEREST => dương (tiền vào)
     *   - WITHDRAWAL / TRANSFER_OUT / FEE => âm (tiền ra)
     *   - ADJUSTMENT => có thể âm hoặc dương
     *
     * balance: Số dư sau khi giao dịch này được ghi nhận (snapshot từ NH).
     */
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();

            // FK tới tài khoản ngân hàng
            $table->foreignId('bank_account_id')
                ->constrained('bank_accounts')
                ->cascadeOnDelete()
                ->comment('Tài khoản ngân hàng');

            // Ngày phát sinh giao dịch trên sao kê
            $table->date('transaction_date')
                ->comment('Ngày phát sinh giao dịch');

            // Ngày hạch toán (post date) - có thể khác transaction_date với giao dịch cuối ngày
            $table->date('post_date')
                ->nullable()
                ->comment('Ngày hạch toán');

            // Loại giao dịch (Enum TxType, snapshot string)
            $table->string('type', 32)
                ->comment('Loại: DEPOSIT/WITHDRAWAL/TRANSFER_IN/TRANSFER_OUT/FEE/INTEREST/ADJUSTMENT');

            // Số tiền: dương = vào, âm = ra (DECIMAL 15,2 BẮT BUỘC cho tiền tệ)
            $table->decimal('amount', 15, 2)
                ->comment('Số tiền giao dịch (dương = vào, âm = ra)');

            // Số dư sau giao dịch (snapshot từ NH)
            $table->decimal('balance', 15, 2)
                ->nullable()
                ->comment('Số dư sau giao dịch');

            // Mã tham chiếu từ ngân hàng (FT number, mã CK...)
            $table->string('reference', 100)
                ->nullable()
                ->comment('Mã giao dịch từ ngân hàng');

            // Diễn giải trên sao kê
            $table->text('description')
                ->nullable()
                ->comment('Nội dung giao dịch trên sao kê');

            // Đối phương (người gửi / người nhận hiển thị trên sao kê)
            $table->string('counterparty_name', 255)
                ->nullable()
                ->comment('Tên đối phương trên sao kê');

            $table->string('counterparty_account', 50)
                ->nullable()
                ->comment('Số tài khoản đối phương');

            // Đối soát với Payment (Enum ReconStatus, snapshot string)
            $table->string('recon_status', 32)
                ->default('UNRECONCILED')
                ->comment('Trạng thái đối soát: UNRECONCILED/MATCHED/DISPUTED');

            // FK tới payment đã match (nếu MATCHED)
            $table->foreignId('matched_payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete()
                ->comment('Payment đã match với giao dịch này');

            // Import tracking
            $table->string('import_batch_id', 100)
                ->nullable()
                ->comment('Mã batch import sao kê');

            $table->json('raw_data')
                ->nullable()
                ->comment('Dữ liệu gốc từ MT940/CSV - JSON');

            $table->timestamp('created_at')
                ->nullable()
                ->comment('Thời điểm import vào hệ thống');

            // Index truy vấn phổ biến
            $table->index(['bank_account_id', 'transaction_date'], 'bank_tx_account_date_idx');
            $table->index('recon_status', 'bank_tx_recon_idx');
            $table->index('reference', 'bank_tx_ref_idx');
            $table->index('import_batch_id', 'bank_tx_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
