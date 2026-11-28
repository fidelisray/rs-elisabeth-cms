<?php

namespace App\Filament\Resources\BannerPromotions;

use App\Enums\AdminNavigationGroup;
use App\Filament\Concerns\FormatsBilingualRecordTitle;
use App\Filament\Resources\BannerPromotions\Pages\CreateBannerPromotion;
use App\Filament\Resources\BannerPromotions\Pages\EditBannerPromotion;
use App\Filament\Resources\BannerPromotions\Pages\ListBannerPromotions;
use App\Filament\Resources\BannerPromotions\Schemas\BannerPromotionForm;
use App\Filament\Resources\BannerPromotions\Tables\BannerPromotionsTable;
use App\Models\BannerPromotion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class BannerPromotionResource extends Resource
{
    use FormatsBilingualRecordTitle;
    use Translatable;

    protected static ?string $model = BannerPromotion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::MarketingEditorial;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('Banner Promosi');
    }

    public static function getModelLabel(): string
    {
        return __('model.banner_promotion');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Banner Promosi');
    }

    public static function form(Schema $schema): Schema
    {
        return BannerPromotionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BannerPromotionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBannerPromotions::route('/'),
            'create' => CreateBannerPromotion::route('/create'),
            'edit' => EditBannerPromotion::route('/{record}/edit'),
        ];
    }
}
