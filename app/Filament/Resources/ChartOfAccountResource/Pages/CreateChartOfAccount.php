<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChartOfAccountResource\Pages;

use App\Filament\Resources\ChartOfAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChartOfAccount extends CreateRecord
{
    protected static string $resource = ChartOfAccountResource::class;
}