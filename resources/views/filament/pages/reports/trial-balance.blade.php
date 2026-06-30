<x-filament-panels::page>
    <form wire:submit="runReport">
        {{ $this->form }}
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            Bảng cân đối thử - từ {{ \Carbon\Carbon::parse($report['from_date'])->format('d/m/Y') }}
            đến {{ \Carbon\Carbon::parse($report['to_date'])->format('d/m/Y') }}
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800">
                        <th class="text-left p-2">Mã TK</th>
                        <th class="text-left p-2">Tên</th>
                        <th class="text-left p-2">Loại</th>
                        <th class="text-right p-2">Nợ đầu kỳ</th>
                        <th class="text-right p-2">Có đầu kỳ</th>
                        <th class="text-right p-2">PS Nợ</th>
                        <th class="text-right p-2">PS Có</th>
                        <th class="text-right p-2">Nợ cuối kỳ</th>
                        <th class="text-right p-2">Có cuối kỳ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] ?? [] as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-700">
                            <td class="p-2 font-mono font-bold">{{ $row['code'] }}</td>
                            <td class="p-2">{{ $row['name'] }}</td>
                            <td class="p-2">
                                <span class="text-xs px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-700">
                                    {{ $row['type'] }}
                                </span>
                            </td>
                            <td class="p-2 text-right">{{ number_format($row['opening_debit'], 0, ',', '.') }}</td>
                            <td class="p-2 text-right">{{ number_format($row['opening_credit'], 0, ',', '.') }}</td>
                            <td class="p-2 text-right text-green-600">{{ number_format($row['movement_debit'], 0, ',', '.') }}</td>
                            <td class="p-2 text-right text-red-600">{{ number_format($row['movement_credit'], 0, ',', '.') }}</td>
                            <td class="p-2 text-right font-bold">{{ number_format($row['closing_debit'], 0, ',', '.') }}</td>
                            <td class="p-2 text-right font-bold">{{ number_format($row['closing_credit'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-4 text-center text-gray-500">Không có dữ liệu phát sinh trong kỳ.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    @php $totals = $report['totals'] ?? []; @endphp
                    <tr class="bg-gray-200 dark:bg-gray-700 font-bold">
                        <td colspan="3" class="p-2 text-right">TỔNG CỘNG</td>
                        <td class="p-2 text-right">{{ number_format($totals['opening_debit'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-2 text-right">{{ number_format($totals['opening_credit'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-2 text-right text-green-600">{{ number_format($totals['movement_debit'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-2 text-right text-red-600">{{ number_format($totals['movement_credit'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-2 text-right">{{ number_format($totals['closing_debit'] ?? 0, 0, ',', '.') }}</td>
                        <td class="p-2 text-right">{{ number_format($totals['closing_credit'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>