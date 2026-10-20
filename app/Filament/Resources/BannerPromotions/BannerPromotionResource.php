<?php

namespace App\Filament\Resources\BannerPromotions;

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
    use Translatable;

    protected static ?string $model = BannerPromotion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Banner Promotions';

    protected static ?string $pluralModelLabel = 'Banner Promotions';

    protected static ?string $modelLabel = 'Banner Promotion';

    protected static ?string $recordTitleAttribute = 'title';

    // protected static ?int $navigationSort = 1;

    /**
     * Mengelompokkan modul ini di bawah grup "Konten Halaman Utama"
     * di sidebar Filament.
     */
    // public static function getNavigationGroup(): ?string
    // {
    //     return 'Konten Halaman Utama';
    // }

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
