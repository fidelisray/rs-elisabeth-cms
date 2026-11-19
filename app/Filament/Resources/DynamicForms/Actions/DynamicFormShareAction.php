<?php

namespace App\Filament\Resources\DynamicForms\Actions;

use App\Models\DynamicForm;
use App\Services\DynamicForm\DynamicFormShareService;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DynamicFormShareAction
{
    public static function make(string $name = 'shareLink'): Action
    {
        return Action::make($name)
            ->label('Link & QR Code')
            ->icon(Heroicon::OutlinedQrCode)
            ->color('info')
            ->authorize(fn (DynamicForm $record): bool => Gate::allows('view', $record)
                && app(DynamicFormShareService::class)->canShare($record))
            ->visible(fn (DynamicForm $record): bool => app(DynamicFormShareService::class)->canShare($record)
                && Gate::allows('view', $record))
            ->modalHeading(fn (DynamicForm $record): string => 'Link & QR Code: '.$record->title)
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn (DynamicForm $record): View => view(
                'filament.resources.dynamic-forms.components.share-modal',
                app(DynamicFormShareService::class)->buildModalData($record),
            ))
            ->extraModalFooterActions(fn (DynamicForm $record): array => [
                Action::make('downloadPng')
                    ->label('Unduh PNG (Siap Cetak)')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('primary')
                    ->authorize(function (DynamicForm $record): bool {
                        $service = app(DynamicFormShareService::class);

                        return Gate::allows('view', $record) && $service->canShare($record) && $service->qrUrl($record) !== null;
                    })
                    ->visible(function (DynamicForm $record): bool {
                        $service = app(DynamicFormShareService::class);

                        return $service->canShare($record) && $service->qrUrl($record) !== null;
                    })
                    ->action(function (DynamicForm $record): ?StreamedResponse {
                        $service = app(DynamicFormShareService::class);
                        $qrUrl = $service->qrUrl($record);
                        if (! $service->canShare($record) || $qrUrl === null) {
                            return null;
                        }

                        $binary = $service->renderQrPng($qrUrl);
                        $filename = $service->downloadFilename($record, 'png');

                        return response()->streamDownload(function () use ($binary): void {
                            echo $binary;
                        }, $filename, [
                            'Content-Type' => 'image/png',
                            'X-Content-Type-Options' => 'nosniff',
                        ]);
                    }),

                Action::make('downloadSvg')
                    ->label('Unduh SVG (Vektor)')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->authorize(function (DynamicForm $record): bool {
                        $service = app(DynamicFormShareService::class);

                        return Gate::allows('view', $record) && $service->canShare($record) && $service->qrUrl($record) !== null;
                    })
                    ->visible(function (DynamicForm $record): bool {
                        $service = app(DynamicFormShareService::class);

                        return $service->canShare($record) && $service->qrUrl($record) !== null;
                    })
                    ->action(function (DynamicForm $record): ?StreamedResponse {
                        $service = app(DynamicFormShareService::class);
                        $qrUrl = $service->qrUrl($record);
                        if (! $service->canShare($record) || $qrUrl === null) {
                            return null;
                        }

                        $svg = $service->renderQrSvg($qrUrl, withXmlHeader: true);
                        $filename = $service->downloadFilename($record, 'svg');

                        return response()->streamDownload(function () use ($svg): void {
                            echo $svg;
                        }, $filename, [
                            'Content-Type' => 'image/svg+xml; charset=UTF-8',
                            'X-Content-Type-Options' => 'nosniff',
                        ]);
                    }),
            ]);
    }
}
