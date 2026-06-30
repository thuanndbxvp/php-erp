<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntryResource\RelationManagers;

use App\Enums\EntryDC;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class LedgerEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgerEntries';

    protected static ?string $title = 'Dòng sổ cái';

    protected static ?string $pluralModelLabel = 'Dòng sổ cái';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('account.code')
                    ->label('Mã TK')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Tên TK')
                    ->wrap(),

                Tables\Columns\TextColumn::make('dc')
                    ->label('Loại')
                    ->formatStateUsing(fn (EntryDC $state): string => $state->label())
                    ->badge()
                    ->color(fn (EntryDC $state): string => $state === EntryDC::DEBIT ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->color(fn (LedgerEntry $record): string => $record->dc === EntryDC::DEBIT ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('amount_base')
                    ->label('Quy VND')
                    ->money('VND')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Ghi chú')
                    ->placeholder('—')
                    ->limit(30),

                Tables\Columns\TextColumn::make('party_type')
                    ->label('Đối tượng')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'CUSTOMER' => '👤 KH',
                        'SUPPLIER' => '🏭 NCC',
                        default => $state ?? '—',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}