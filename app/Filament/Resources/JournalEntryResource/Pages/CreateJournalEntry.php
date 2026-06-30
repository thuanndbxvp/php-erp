<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Enums\EntryDC;
use App\Filament\Resources\JournalEntryResource;
use App\Models\ChartOfAccount;
use App\Models\LedgerEntry;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateJournalEntry extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['status'] = \App\Enums\JournalStatus::POSTED->value;
        $data['posted_by'] = Auth::id();
        $data['posted_at'] = now();
        $data['total_debit'] = '0';
        $data['total_credit'] = '0';
        return $data;
    }

    protected function afterCreate(): void
    {
        $journal = $this->record;
        $lines = $this->data['lines'] ?? [];
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($lines as $row) {
            $amount = (string) ($row['amount'] ?? '0');
            $dc = $row['dc'] ?? 'DEBIT';
            $accountId = (int) ($row['account_id'] ?? 0);
            if ($accountId === 0 || bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            LedgerEntry::create([
                'journal_entry_id' => $journal->id,
                'chart_of_account_id' => $accountId,
                'dc' => $dc,
                'amount' => $amount,
                'currency' => $journal->currency,
                'exchange_rate' => (string) $journal->exchange_rate,
                'amount_base' => (string) round((float) $amount * (float) $journal->exchange_rate, 2),
                'description' => $row['line_description'] ?? null,
                'posting_date' => $journal->entry_date,
                'status' => 'ACTIVE',
            ]);

            if ($dc === EntryDC::DEBIT->value) {
                $totalDebit = bcadd($totalDebit, $amount, 2);
            } else {
                $totalCredit = bcadd($totalCredit, $amount, 2);
            }
        }

        $journal->total_debit = $totalDebit;
        $journal->total_credit = $totalCredit;
        $journal->save();
    }
}