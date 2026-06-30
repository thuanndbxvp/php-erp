<?php

declare(strict_types=1);

namespace App\Filament\Resources\BulkPaymentResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'applications';

    protected static ?string $title = 'Hóa đơn được gom';

    protected static ?string $pluralModelLabel = 'Hóa đơn trong phiếu';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_label')
                    ->label('Hóa đơn')
                    ->getStateUsing(function ($record): string {
                        if ($record->invoiceOut) {
                            return "📤 {$record->invoiceOut->invoice_number}";
                        }
                        if ($record->invoiceIn) {
                            return "📥 {$record->invoiceIn->invoice_number}";
                        }
                        return '—';
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('amount_applied')
                    ->label('Số tiền')
                    ->money('VND')
                    ->weight('bold')
                    ->summarize([Tables\Columns\Summarizers\Sum::make()->money('VND')]),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Ghi chú')
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}