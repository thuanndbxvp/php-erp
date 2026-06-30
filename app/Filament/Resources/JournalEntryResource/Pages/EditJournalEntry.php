<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJournalEntry extends EditRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Xóa'),
        ];
    }
}