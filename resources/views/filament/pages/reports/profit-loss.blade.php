<x-filament-panels::page>
    <form wire:submit="runReport">
        {{ $this->form }}
    </form>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
        <x-filament::section>
            <x-slot name="heading">📈 Doanh thu</x-slot>
            <div class="text-2xl font-bold text-green-600">
                {{ number_format($report['revenue']['total'] ?? 0, 0, ',', '.') }} ₫
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">📉 Chi phí</x-slot>
            <div class="text-2xl font-bold text-red-600">
                {{ number_format($report['expense']['total'] ?? 0, 0, ',', '.') }} ₫
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">💰 Lợi nhuận ròng</x-slot>
            <div class="text-2xl font-bold {{ ($report['net_income'] ?? 0) >= 0 ? 'text-primary-600' : 'text-danger-600' }}">
                {{ number_format($report['net_income'] ?? 0, 0, ',', '.') }} ₫
            </div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
        <x-filament::section>
            <x-slot name="heading">Chi tiết Doanh thu</x-slot>
            <table class="w-full text-sm">
                <tbody>
                    @forelse ($report['revenue']['rows'] ?? [] as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-700">
                            <td class="p-2 font-mono">{{ $row['code'] }}</td>
                            <td class="p-2">{{ $row['name'] }}</td>
                            <td class="p-2 text-right text-green-600">{{ number_format($row['balance'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-4 text-center text-gray-500">Không có doanh thu</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Chi tiết Chi phí</x-slot>
            <table class="w-full text-sm">
                <tbody>
                    @forelse ($report['expense']['rows'] ?? [] as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-700">
                            <td class="p-2 font-mono">{{ $row['code'] }}</td>
                            <td class="p-2">{{ $row['name'] }}</td>
                            <td class="p-2 text-right text-red-600">{{ number_format($row['balance'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-4 text-center text-gray-500">Không có chi phí</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    </div>
</x-filament-panels::page>