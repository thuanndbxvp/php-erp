<x-filament-panels::page>
    <form wire:submit="runReport">
        {{ $this->form }}
    </form>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
        <x-filament::section>
            <x-slot name="heading">Tổng Tài sản</x-slot>
            <div class="text-2xl font-bold text-info-600">
                {{ number_format($report['assets']['total'] ?? 0, 0, ',', '.') }} ₫
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Tổng Nợ phải trả</x-slot>
            <div class="text-2xl font-bold text-warning-600">
                {{ number_format($report['liabilities']['total'] ?? 0, 0, ',', '.') }} ₫
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Tổng Vốn CSH</x-slot>
            <div class="text-2xl font-bold text-primary-600">
                {{ number_format($report['total_equity_with_pl'] ?? 0, 0, ',', '.') }} ₫
            </div>
            <div class="text-xs text-gray-500 mt-1">
                Vốn gốc: {{ number_format($report['equities']['total'] ?? 0, 0, ',', '.') }}
                + LN kỳ: {{ number_format($report['current_period_pl'] ?? 0, 0, ',', '.') }}
            </div>
        </x-filament::section>
    </div>

    @php
        $balanced = $report['balanced'] ?? true;
    @endphp

    <div class="mt-4 p-3 rounded {{ $balanced ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
        @if ($balanced)
            ✅ Bảng CĐKT cân bằng: Tổng Tài sản = Nợ phải trả + Vốn CSH
        @else
            ⚠️ Lệch: Tổng TS ≠ Tổng NPT + VCSH. Cần kiểm tra số liệu.
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
        <x-filament::section>
            <x-slot name="heading">Tài sản</x-slot>
            <table class="w-full text-sm">
                <tbody>
                    @forelse ($report['assets']['rows'] ?? [] as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-700">
                            <td class="p-2 font-mono">{{ $row['code'] }}</td>
                            <td class="p-2">{{ $row['name'] }}</td>
                            <td class="p-2 text-right text-info-600">{{ number_format($row['balance'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-4 text-center text-gray-500">Trống</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <div class="space-y-4">
            <x-filament::section>
                <x-slot name="heading">Nợ phải trả</x-slot>
                <table class="w-full text-sm">
                    <tbody>
                        @forelse ($report['liabilities']['rows'] ?? [] as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                <td class="p-2 font-mono">{{ $row['code'] }}</td>
                                <td class="p-2">{{ $row['name'] }}</td>
                                <td class="p-2 text-right text-warning-600">{{ number_format($row['balance'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-4 text-center text-gray-500">Trống</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Vốn chủ sở hữu</x-slot>
                <table class="w-full text-sm">
                    <tbody>
                        @forelse ($report['equities']['rows'] ?? [] as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                <td class="p-2 font-mono">{{ $row['code'] }}</td>
                                <td class="p-2">{{ $row['name'] }}</td>
                                <td class="p-2 text-right text-primary-600">{{ number_format($row['balance'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-4 text-center text-gray-500">Trống</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>