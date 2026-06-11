<?php

namespace App\Filament\Resources\ReferralRules;

use App\Filament\Resources\ReferralRules\Pages\CreateReferralRule;
use App\Filament\Resources\ReferralRules\Pages\EditReferralRule;
use App\Filament\Resources\ReferralRules\Pages\ListReferralRules;
use App\Filament\Resources\ReferralRules\Schemas\ReferralRuleForm;
use App\Filament\Resources\ReferralRules\Tables\ReferralRulesTable;
use App\Models\ReferralRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReferralRuleResource extends Resource
{
    protected static ?string $model = ReferralRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'قوانین رفرال';

    protected static ?string $modelLabel = 'قانون رفرال';

    protected static ?string $pluralModelLabel = 'قوانین رفرال';

    protected static string|\UnitEnum|null $navigationGroup = 'رفرال';

    public static function form(Schema $schema): Schema
    {
        return ReferralRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferralRulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferralRules::route('/'),
            'create' => CreateReferralRule::route('/create'),
            'edit' => EditReferralRule::route('/{record}/edit'),
        ];
    }
}
