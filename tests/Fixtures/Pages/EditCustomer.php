<?php

namespace TableKit\Tests\Fixtures\Pages;

use Filament\Resources\Pages\EditRecord;
use TableKit\Tests\Fixtures\CustomerResource;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
}
