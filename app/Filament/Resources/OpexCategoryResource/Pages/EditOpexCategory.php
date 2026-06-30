<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpexCategoryResource\Pages;

use App\Filament\Resources\OpexCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOpexCategory extends EditRecord
{
    protected static string $resource = OpexCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Xóa'),
        ];
    }
}
