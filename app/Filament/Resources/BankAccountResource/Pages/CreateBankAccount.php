<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankAccountResource\Pages;

use App\Filament\Resources\BankAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBankAccount extends CreateRecord
{
    protected static string $resource = BankAccountResource::class;
}