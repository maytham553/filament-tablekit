<?php

namespace Elite\TableViews\Tests\Fixtures\Pages;

use Elite\TableViews\Tests\Fixtures\CustomerResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
}
