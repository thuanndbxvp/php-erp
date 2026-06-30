<x-filament-panels::page>
    {{-- Form chọn kỳ báo cáo --}}
    <form wire:submit="runReport">
        {{ $this->form }}
    </form>

    @php
        $revenue = (float) ($report['revenue']['total'] ?? 0);
        $cogs = (float) ($report['cogs']['total'] ?? 0);
        $grossProfit = (float) ($report['gross_profit']['amount'] ?? 0);
        $grossMargin = (float) ($report['gross_profit']['margin'] ?? 0);

        $directCosts = (float) ($report['direct_costs']['total'] ?? 0);
        $contribution = (float) ($report['contribution_profit']['amount'] ?? 0);
        $contributionMargin = (float) ($report['contribution_profit']['margin'] ?? 0);

        $opex = (float) ($report['opex']['total'] ?? 0);
        $netProfit = (float) ($report['net_profit']['amount'] ?? 0);
        $netMargin = (float) ($report['net_profit']['margin'] ?? 0);
    @endphp

    {{-- Bộ 4 KPI chính --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        {{-- Doanh thu --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span class="text-lg">📈</span> Doanh thu
            </div>
            <div class="mt-2 text-2xl font-bold text-success-600 dark:text-success-400">
                {{ number_format($revenue, 0, ',', '.') }} ₫
            </div>
            <div class="text-xs text-gray-500 mt-1">100%</div>
        </div>

        {{-- Lãi gộp --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span class="text-lg">💎</span> Lãi gộp (Gross Profit)
            </div>
            <div class="mt-2 text-2xl font-bold {{ $grossProfit >= 0 ? 'text-info-600 dark:text-info-400' : 'text-danger-600 dark:text-danger-400' }}">
                {{ number_format($grossProfit, 0, ',', '.') }} ₫
            </div>
            <div class="text-xs text-gray-500 mt-1">
                Gross Margin: <span class="font-semibold">{{ number_format($grossMargin, 2) }}%</span>
            </div>
        </div>

        {{-- Lãi trên biến phí --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span class="text-lg">🎯</span> Lãi trên biến phí
            </div>
            <div class="mt-2 text-2xl font-bold {{ $contribution >= 0 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400' }}">
                {{ number_format($contribution, 0, ',', '.') }} ₫
            </div>
            <div class="text-xs text-gray-500 mt-1">
                Contribution Margin: <span class="font-semibold">{{ number_format($contributionMargin, 2) }}%</span>
            </div>
        </div>

        {{-- Lợi nhuận ròng --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span class="text-lg">💰</span> Lợi nhuận ròng
            </div>
            <div class="mt-2 text-2xl font-bold {{ $netProfit >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">
                {{ number_format($netProfit, 0, ',', '.') }} ₫
            </div>
            <div class="text-xs text-gray-500 mt-1">
                Net Margin: <span class="font-semibold">{{ number_format($netMargin, 2) }}%</span>
            </div>
        </div>
    </div>

    {{-- Bảng P&L dạng Statement (kiểu báo cáo tài chính chuyên nghiệp) --}}
    <div class="mt-6 rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-800">
            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                📊 Báo cáo Kết quả Kinh doanh (Quản trị)
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Kỳ: {{ \Carbon\Carbon::parse($report['start_date'])->format('d/m/Y') }}
                → {{ \Carbon\Carbon::parse($report['end_date'])->format('d/m/Y') }}
            </p>
        </div>

        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-6 py-3 text-left font-semibold">Khoản mục</th>
                    <th class="px-6 py-3 text-right font-semibold w-40">Số tiền (VND)</th>
                    <th class="px-6 py-3 text-right font-semibold w-24">% DT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                {{-- DOANH THU --}}
                <tr class="bg-success-50/50 dark:bg-success-900/10">
                    <td class="px-6 py-3 font-bold text-success-700 dark:text-success-300">
                        1. DOANH THU (REVENUE)
                    </td>
                    <td class="px-6 py-3 text-right font-bold text-success-700 dark:text-success-300">
                        {{ number_format($revenue, 0, ',', '.') }}
                    </td>
                    <td class="px-6 py-3 text-right text-success-700 dark:text-success-300">100.00%</td>
                </tr>
                @forelse ($report['revenue']['rows'] ?? [] as $row)
                    <tr>
                        <td class="px-6 py-2 pl-10 text-gray-600 dark:text-gray-400">
                            <span class="font-mono text-xs text-gray-400">{{ $row['code'] }}</span>
                            <span class="ml-2">{{ $row['name'] }}</span>
                        </td>
                        <td class="px-6 py-2 text-right text-success-600 dark:text-success-400">
                            {{ number_format($row['amount'], 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-2 text-right text-gray-500">
                            {{ number_format($row['percentage'], 2) }}%
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-2 pl-10 text-center text-gray-400 italic">Chưa có doanh thu</td>
                    </tr>
                @endforelse

                {{-- GIÁ VỐN --}}
                <tr class="bg-danger-50/50 dark:bg-danger-900/10">
                    <td class="px-6 py-3 font-bold text-danger-700 dark:text-danger-300">
                        2. GIÁ VỐN HÀNG BÁN (COGS)
                    </td>
                    <td class="px-6 py-3 text-right font-bold text-danger-700 dark:text-danger-300">
                        ({{ number_format($cogs, 0, ',', '.') }})
                    </td>
                    <td class="px-6 py-3 text-right text-danger-700 dark:text-danger-300">
                        {{ $revenue > 0 ? number_format(-$cogs / $revenue * 100, 2) : '0.00' }}%
                    </td>
                </tr>
                <tr>
                    <td colspan="3" class="px-6 py-1 pl-10 text-xs italic text-gray-500">
                        Tính trên {{ count($report['cogs']['rows'] ?? []) }} đơn bán đã SHIPPED trong kỳ
                    </td>
                </tr>

                {{-- LÃI GỘP --}}
                <tr class="bg-info-50/70 dark:bg-info-900/20">
                    <td class="px-6 py-4 font-bold text-base text-info-800 dark:text-info-200">
                        = LÃI GỘP (GROSS PROFIT)
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-base text-info-800 dark:text-info-200">
                        {{ number_format($grossProfit, 0, ',', '.') }}
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-info-800 dark:text-info-200">
                        {{ number_format($grossMargin, 2) }}%
                    </td>
                </tr>

                {{-- DIRECT COSTS --}}
                <tr class="bg-warning-50/50 dark:bg-warning-900/10">
                    <td class="px-6 py-3 font-bold text-warning-700 dark:text-warning-300">
                        3. CHI PHÍ TRỰC TIẾP (DIRECT COSTS)
                    </td>
                    <td class="px-6 py-3 text-right font-bold text-warning-700 dark:text-warning-300">
                        ({{ number_format($directCosts, 0, ',', '.') }})
                    </td>
                    <td class="px-6 py-3 text-right text-warning-700 dark:text-warning-300">
                        {{ $revenue > 0 ? number_format(-$directCosts / $revenue * 100, 2) : '0.00' }}%
                    </td>
                </tr>
                @forelse ($report['direct_costs']['rows'] ?? [] as $row)
                    <tr>
                        <td class="px-6 py-2 pl-10 text-gray-600 dark:text-gray-400">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-200">
                                {{ $row['label'] }}
                            </span>
                            <span class="ml-2 text-xs text-gray-500">({{ $row['count'] ?? 0 }} phiếu)</span>
                        </td>
                        <td class="px-6 py-2 text-right text-warning-600 dark:text-warning-400">
                            ({{ number_format($row['amount'], 0, ',', '.') }})
                        </td>
                        <td class="px-6 py-2 text-right text-gray-500">
                            {{ $revenue > 0 ? number_format(-$row['amount'] / $revenue * 100, 2) : '0.00' }}%
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-2 pl-10 text-center text-gray-400 italic">Chưa có chi phí trực tiếp</td>
                    </tr>
                @endforelse

                {{-- LÃI TRÊN BIẾN PHÍ --}}
                <tr class="bg-warning-100/70 dark:bg-warning-900/30">
                    <td class="px-6 py-4 font-bold text-base text-warning-800 dark:text-warning-200">
                        = LÃI TRÊN BIẾN PHÍ (CONTRIBUTION PROFIT)
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-base text-warning-800 dark:text-warning-200">
                        {{ number_format($contribution, 0, ',', '.') }}
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-warning-800 dark:text-warning-200">
                        {{ number_format($contributionMargin, 2) }}%
                    </td>
                </tr>

                {{-- OPEX --}}
                <tr class="bg-danger-50/50 dark:bg-danger-900/10">
                    <td class="px-6 py-3 font-bold text-danger-700 dark:text-danger-300">
                        4. CHI PHÍ VẬN HÀNH (OPEX)
                    </td>
                    <td class="px-6 py-3 text-right font-bold text-danger-700 dark:text-danger-300">
                        ({{ number_format($opex, 0, ',', '.') }})
                    </td>
                    <td class="px-6 py-3 text-right text-danger-700 dark:text-danger-300">
                        {{ $revenue > 0 ? number_format(-$opex / $revenue * 100, 2) : '0.00' }}%
                    </td>
                </tr>
                @forelse ($report['opex']['rows'] ?? [] as $row)
                    <tr>
                        <td class="px-6 py-2 pl-10 text-gray-600 dark:text-gray-400">
                            <span class="font-mono text-xs text-gray-400">{{ $row['code'] }}</span>
                            <span class="ml-2">{{ $row['name'] }}</span>
                            <span class="ml-2 text-xs text-gray-500">({{ $row['count'] }} phiếu)</span>
                        </td>
                        <td class="px-6 py-2 text-right text-danger-600 dark:text-danger-400">
                            ({{ number_format($row['amount'], 0, ',', '.') }})
                        </td>
                        <td class="px-6 py-2 text-right text-gray-500">
                            {{ $revenue > 0 ? number_format(-$row['amount'] / $revenue * 100, 2) : '0.00' }}%
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-2 pl-10 text-center text-gray-400 italic">Chưa có chi phí vận hành</td>
                    </tr>
                @endforelse

                {{-- LỢI NHUẬN RÒNG --}}
                <tr class="bg-primary-100/70 dark:bg-primary-900/30">
                    <td class="px-6 py-5 font-extrabold text-base text-primary-800 dark:text-primary-200">
                        🎯 LỢI NHUẬN RÒNG (NET PROFIT)
                    </td>
                    <td class="px-6 py-5 text-right font-extrabold text-base text-primary-800 dark:text-primary-200">
                        {{ number_format($netProfit, 0, ',', '.') }}
                    </td>
                    <td class="px-6 py-5 text-right font-extrabold text-primary-800 dark:text-primary-200">
                        {{ number_format($netMargin, 2) }}%
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Chú thích --}}
    <div class="mt-4 p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
        <p class="text-xs text-blue-800 dark:text-blue-200">
            <strong>💡 Chú thích:</strong>
            Báo cáo này dùng cho QUẢN TRỊ (phân tích chi phí theo biến phí / định phí).
            Khác với Báo cáo TT200 (chỉ Revenue - Expense), P&L Quản trị tách riêng
            <strong>Direct Costs</strong> (gắn với từng đơn hàng) và <strong>OPEX</strong>
            (chi phí vận hành cố định) để phục vụ quyết định giá bán và phân tích cơ cấu chi phí.
        </p>
    </div>
</x-filament-panels::page>
