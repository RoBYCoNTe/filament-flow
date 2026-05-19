# Form Components

Filament Flow provides three form components for state selection.

## StateSelect

A dropdown select component:

```php
use RoBYCoNTe\FilamentFlow\Forms\Components\StateSelect;

StateSelect::make('state')
    ->label('Order Status')
    ->required();
```

## StateRadio

Radio buttons with descriptions:

```php
use RoBYCoNTe\FilamentFlow\Forms\Components\StateRadio;

StateRadio::make('state')
    ->label('Order Status')
    ->descriptions(); // Shows state descriptions
```

## StateToggleButtons

Toggle buttons with colors and icons:

```php
use RoBYCoNTe\FilamentFlow\Forms\Components\StateToggleButtons;

StateToggleButtons::make('state')
    ->label('Order Status')
    ->inline(); // Display inline
```

## Complete Form Example

```php
<?php
// app/Filament/Resources/OrderResource/Schemas/OrderForm.php

namespace App\Filament\Resources\OrderResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use RoBYCoNTe\FilamentFlow\Forms\Components\StateToggleButtons;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('order_number')
                    ->label('Order Number')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->default(fn() => 'ORD-' . strtoupper(uniqid())),

                StateToggleButtons::make('state'),

                TextInput::make('total_amount')
                    ->label('Total Amount')
                    ->required()
                    ->numeric()
                    ->prefix('€')
                    ->default(0.00),

                TextInput::make('customer_name')
                    ->label('Customer Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('customer_email')
                    ->label('Customer Email')
                    ->email()
                    ->required(),

                Textarea::make('customer_address')
                    ->label('Customer Address')
                    ->rows(3)
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
```
