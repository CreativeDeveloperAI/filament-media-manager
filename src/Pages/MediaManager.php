<?php

namespace Slimani\MediaManager\Pages;

use Composer\InstalledVersions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;
use Slimani\MediaManager\MediaManagerPlugin;
use Slimani\MediaManager\Models\File;

class MediaManager extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms, InteractsWithSchemas {
        InteractsWithForms::getCachedSchemas insteadof InteractsWithSchemas;
        InteractsWithSchemas::getCachedSchemas as getBaseCachedSchemas;
    }

    public array $selectedFileIds = [];

    #[On('media-selection-synced')]
    public function syncSelection(array $ids): void
    {
        $this->selectedFileIds = $ids;
    }

    public function boot(): void
    {
        if (app()->runningUnitTests()) {
            \Livewire\store($this)->set('forceRender', true);
        }
    }

    protected string $view = 'media-manager::filament.pages.media-manager';

    public function getHeading(): string
    {
        return 'مدير الوسائط';
    }

    public static function getNavigationGroup(): ?string
    {
        return static::getPlugin()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return static::getPlugin()->getNavigationLabel();
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return static::getPlugin()->getNavigationIcon();
    }

    public static function getNavigationSort(): ?int
    {
        return static::getPlugin()->getNavigationSort();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::getPlugin()->getShouldRegisterNavigation();
    }

    public function getHeaderWidgets(): array
    {
        return static::getPlugin()->getHeaderWidgets();
    }

    public function getFooterWidgets(): array
    {
        return static::getPlugin()->getFooterWidgets();
    }

    public function getHeader(): ?View
    {
        return static::getPlugin()->getHeader();
    }

    public function getFooter(): ?View
    {
        return static::getPlugin()->getFooter();
    }

    protected static function getPlugin(): MediaManagerPlugin
    {
        /** @var MediaManagerPlugin $plugin */
        $plugin = Filament::getPlugin('media-manager');

        return $plugin;
    }

    protected function getHeaderActions(): array
    {
        $versionInfo = $this->getVersionInfo();
        $version = $versionInfo['version'];
        $hasUpdate = $versionInfo['hasUpdate'];

        return [
            ActionGroup::make([
                Action::make('regenerate_conversions')
                    ->label('إعادة توليد التحويلات')
                    ->icon('heroicon-m-arrow-path')
                    ->schema([
                        Select::make('conversions')
                            ->label('تحويلات محددة')
                            ->multiple()
                            ->options(function () {
                                $file = new File;
                                $file->registerMediaConversions();

                                return collect($file->mediaConversions)->mapWithKeys(fn ($c) => [$c->getName() => $c->getName()])->toArray();
                            })
                            ->helperText('حدد التحويلات المراد إعادة توليدها. اتركه فارغاً للكل.'),
                        Checkbox::make('only_missing')
                            ->label('المفقودة فقط')
                            ->default(true),
                        Checkbox::make('with_responsive_images')
                            ->label('مع الصور المتجاوبة'),
                        Checkbox::make('force')
                            ->label('إعادة التوليد القسري'),
                    ])
                    ->modalHeading(fn () => count($this->selectedFileIds) > 0
                        ? 'إعادة توليد التحويلات لـ '.count($this->selectedFileIds).' عناصر'
                        : 'إعادة توليد التحويلات لجميع العناصر')
                    ->modalDescription(fn () => count($this->selectedFileIds) > 0
                        ? null
                        : 'سيؤدي هذا إلى إعادة توليد التحويلات لجميع عناصر الوسائط في المكتبة. قد يستغرق هذا بعض الوقت.')
                    ->successNotificationTitle('بدأت إعادة توليد الوسائط بنجاح.')
                    ->action(function (array $data) {
                        $params = [
                            'modelType' => File::class,
                        ];

                        if (! empty($this->selectedFileIds)) {
                            $params['--ids'] = implode(',', $this->selectedFileIds);
                        }

                        if (! empty($data['conversions'])) {
                            $params['--only'] = implode(',', (array) $data['conversions']);
                        }

                        if ($data['only_missing']) {
                            $params['--only-missing'] = true;
                        }

                        if ($data['with_responsive_images']) {
                            $params['--with-responsive-images'] = true;
                        }

                        if ($data['force']) {
                            $params['--force'] = true;
                        }

                        try {
                            Artisan::call('media-library:regenerate', $params);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('خطأ في إعادة توليد الوسائط: '.$e->getMessage())
                                ->send();

                            return;
                        }
                    }),
            ])
                ->icon(Heroicon::EllipsisVertical)
                ->color('gray')
                ->iconButton(),
        ];
    }

    protected function getVersionInfo(): array
    {
        $packageName = 'slimani/filament-media-manager';

        $installedVersion = 'v0.0.0';
        try {
            $installedVersion = InstalledVersions::getPrettyVersion($packageName) ?? 'v0.0.0';
        } catch (\Exception $e) {
            // Fallback
        }

        $cacheKey = 'media_manager_latest_version';

        $latestVersion = Cache::remember($cacheKey, now()->addDay(), function () {
            try {
                $response = Http::get('https://api.github.com/repos/slimani-dev/filament-media-manager/releases/latest');

                if ($response->successful()) {
                    return $response->json('tag_name');
                }
            } catch (\Exception $e) {
                // تجاهل أخطاء الشبكة
            }

            return null;
        });

        $hasUpdate = false;
        if ($latestVersion && $installedVersion !== 'dev-main') {
            $hasUpdate = version_compare(
                ltrim($installedVersion, 'v'),
                ltrim($latestVersion, 'v'),
                '<'
            );
        }

        return [
            'version' => $installedVersion,
            'latestVersion' => $latestVersion,
            'hasUpdate' => $hasUpdate,
        ];
    }
}
