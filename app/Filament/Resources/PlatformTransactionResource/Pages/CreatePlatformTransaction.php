<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformTransactionResource\Pages;

use App\Filament\Resources\PlatformTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformTransaction extends CreateRecord
{
    protected static string $resource = PlatformTransactionResource::class;
}