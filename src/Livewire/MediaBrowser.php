<?php

namespace Slimani\MediaManager\Livewire;

use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Hugomyb\FilamentMediaAction\Actions\MediaAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Slimani\MediaManager\Components\MediaItem;
use Slimani\MediaManager\Infolists\Components\RepeatableEntry as CustomRepeatableEntry;
use Slimani\MediaManager\Models\File;
use Slimani\MediaManager\Models\Folder;
use Slimani\MediaManager\Models\Tag;

/**
 * @property-read Collection $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 *
 *
 * no need to add HasSchemas because interface HasForms extends HasSchemas
 */
class MediaBrowser extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms, InteractsWithSchemas {
        InteractsWithForms::getCachedSchemas insteadof InteractsWithSchemas;
        InteractsWithSchemas::getCachedSchemas as getBaseCachedSchemas;
    }
    use WithFileUploads;
    use WithPagination;

    public ?string $serializedOnSelect = null;

    public function clearCachedSchemas(): void
    {
        $this->cachedSchemas = [];
    }

    public ?Folder $currentFolder = null;

    // حالة واجهة المستخدم
    public string $search = '';

    public string $sortField = '';

    public string $sortDirection = 'asc';

    public ?int $currentFolderId = null;

    public array $breadcrumbs = [];

    // حالة الوسوم
    public bool $isEditingTags = false;

    public array $activeTags = [];

    public ?int $editingFolderId = null;

    public ?int $selectedFileId = null;

    // حالة منتقي الملفات
    public bool $showDetails = true;

    public bool $isPicker = false;

    public bool $multiple = false;

    public ?string $pickerId = null;

    // حالة الفلاتر
    public bool $showFilters = false;

    public bool $showSelectedOnly = false;

    public array $filterTags = [];

    public ?string $filterType = null;

    public ?string $filterSizeMin = null;

    public ?string $filterSizeMax = null;

    public array $selectedItems = [];

    public int|string $perPage = 10;

    public function getPageName(): string
    {
        return 'media_browser_page';
    }

    public function boot()
    {
        if (app()->runningUnitTests()) {
            \Livewire\store($this)->set('forceRender', true);
        }
    }

    public function queryString(): array
    {
        if ($this->isPicker) {
            return [];
        }

        return [
            'sortField' => ['as' => 'sort_by', 'history' => true],
            'sortDirection' => ['as' => 'sort_dir', 'history' => true],
            'currentFolderId' => ['as' => 'folder', 'history' => true],
            'perPage' => ['history' => true],
            'showSelectedOnly' => ['history' => true],
            'paginators.'.$this->getPageName() => ['as' => $this->getPageName(), 'history' => true],
        ];
    }

    public ?string $statePath = null;

    public ?Collection $files = null;

    protected static ?string $title = 'مركز الوسائط';

    public ?array $acceptedFileTypes = [];

    public function updatedSortField(): void
    {
        $this->clearCachedSchemas();
        $this->setPage(1, $this->getPageName());
    }

    public function updatedSortDirection(): void
    {
        $this->clearCachedSchemas();
        $this->setPage(1, $this->getPageName());
    }

    public function updatedSearch(): void
    {
        $this->clearCachedSchemas();
        $this->setPage(1, $this->getPageName());
    }

    public function updatedFilterTags(): void
    {
        $this->clearCachedSchemas();
        $this->setPage(1, $this->getPageName());
    }

    public function updatedFilterType(): void
    {
        $this->clearCachedSchemas();
        $this->setPage(1, $this->getPageName());
    }

    public function updatedFilterSizeMin(): void
    {
        $this->clearCachedSchemas();
        $this->setPage(1, $this->getPageName());
    }

    public function updatedFilterSizeMax(): void
    {
        $this->clearCachedSchemas();
        $this->setPage(1, $this->getPageName());
    }

    public function mount(
        bool $isPicker = false,
        bool $multiple = false,
        ?string $pickerId = null,
        array $selectedItems = [],
        ?string $onSelect = null,
        ?string $statePath = null,
        ?array $acceptedFileTypes = [],
        ?int $currentFolderId = null
    ): void {
        $this->isPicker = $isPicker;
        $this->multiple = $multiple;
        $this->pickerId = $pickerId;
        $this->selectedItems = $selectedItems;
        $this->serializedOnSelect = $onSelect;
        $this->statePath = $statePath;
        $this->acceptedFileTypes = $acceptedFileTypes;

        if (! $this->isPicker && is_null($currentFolderId)) {
            $currentFolderId = request()->query('folder');
        }

        $this->currentFolderId = $currentFolderId;

        if ($this->serializedOnSelect) {
            $this->executeOnSelect();
        }

        if ($this->currentFolderId) {
            $this->currentFolder = Folder::query()->with(['tags'])->withCount(['children', 'files'])->find($this->currentFolderId);
            $this->generateBreadcrumbs();
        }
    }

    #[On('open-media-browser')]
    public function openMediaBrowser(string $pickerId, bool $multiple = false, array $selectedItems = []): void
    {
        $this->isPicker = true;
        $this->multiple = $multiple;
        $this->selectedItems = collect($selectedItems)
            ->map(fn ($id) => (is_string($id) && (str_starts_with($id, 'file-') || str_starts_with($id, 'folder-'))) ? $id : "file-{$id}")
            ->toArray();
        $this->pickerId = $pickerId;

        if (count($this->selectedItems) === 1) {
            $this->locateItem(reset($this->selectedItems));
        } else {
            $this->resetPage($this->getPageName());
        }

        $this->clearCachedSchemas();
        $this->dispatch('open-modal', id: 'media-browser-modal');
    }

    public function updatedSelectedItems(): void
    {
        $this->syncState();
    }

    public function getActions(): array
    {
        return [
            $this->createFolderAction(),
            $this->uploadAction(),
            $this->bulkMoveAction(),
            $this->bulkDeleteAction(),
        ];
    }

    public function toggleDetailsAction(): Action
    {
        return Action::make('toggleDetails')
            ->label('التفاصيل')
            ->icon('heroicon-o-information-circle')
            ->hiddenLabel()
            ->color(fn () => $this->showDetails ? 'primary' : 'gray')
            ->action(function () {
                $this->showDetails = ! $this->showDetails;
                $this->clearCachedSchemas();
            });
    }

    public function toggleFiltersAction(): Action
    {
        return Action::make('toggleFilters')
            ->label('الفلاتر')
            ->hiddenLabel()
            ->icon('heroicon-o-funnel')
            ->color(fn () => $this->showFilters || $this->hasActiveFilters() ? 'primary' : 'gray')
            ->badge(fn () => $this->getActiveFiltersCount() ?: null)
            ->action(function () {
                $this->showFilters = ! $this->showFilters;
                $this->clearCachedSchemas();
            });
    }

    public function toggleSortDirectionAction(): Action
    {
        return Action::make('toggleSortDirection')
            ->label('اتجاه الترتيب')
            ->hiddenLabel()
            ->icon(fn () => $this->sortDirection === 'asc' ? 'heroicon-o-bars-arrow-up' : 'heroicon-o-bars-arrow-down')
            ->color('gray')
            ->action(function () {
                $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
                $this->clearCachedSchemas();
            });
    }

    public function hasActiveFilters(): bool
    {
        return $this->getActiveFiltersCount() > 0;
    }

    public function getActiveFiltersCount(): int
    {
        $count = 0;

        if (! empty($this->filterTags)) {
            $count++;
        }

        if ($this->filterType !== null && $this->filterType !== '') {
            $count++;
        }

        if ($this->filterSizeMin !== null && $this->filterSizeMin !== '') {
            $count++;
        }

        if ($this->filterSizeMax !== null && $this->filterSizeMax !== '') {
            $count++;
        }

        return $count;
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Grid::make(['default' => 1, 'lg' => 4])
                    ->schema([
                        Flex::make(fn () => [
                            Flex::make([
                                $this->createFolderAction(),
                                $this->uploadAction(),
                                $this->clearSelectionAction()
                                    ->visible(fn () => count($this->selectedItems) > 1),
                            ])->extraAttributes([
                                'class' => 'gap-2',
                            ]),

                            Flex::make([
                                TextInput::make('search')
                                    ->live()
                                    ->debounce()
                                    ->hiddenLabel()
                                    ->placeholder('البحث في الملفات...')
                                    ->prefixIcon('heroicon-m-magnifying-glass')
                                    ->columnSpan(1),
                                Flex::make([
                                    Select::make('sortField')
                                        ->hiddenLabel()
                                        ->options([
                                            'name' => 'الاسم',
                                            'created_at' => 'التاريخ',
                                            'size' => 'الحجم',
                                            'mime_type' => 'النوع',
                                        ])
                                        ->live()
                                        ->placeholder('ترتيب')
                                        ->extraAttributes([
                                            'class' => 'md:w-32',
                                        ])->grow(),
                                    $this->toggleSortDirectionAction(),
                                    $this->toggleFiltersAction(),
                                    $this->toggleDetailsAction(),
                                ])->extraAttributes([
                                    'class' => 'gap-4',
                                ])->alignEnd()
                                    ->grow(false),
                            ])->extraAttributes([
                                'class' => 'md:gap-4',
                            ])->from('md')
                                ->alignEnd()
                                ->grow(false),
                        ])->columnSpanFull()
                            ->from('md'),

                        Section::make()
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 4])->schema([
                                    Select::make('filterType')
                                        ->label('نوع الملف')
                                        ->options([
                                            'image' => 'صور',
                                            'video' => 'فيديو',
                                            'audio' => 'صوت',
                                            'document' => 'مستندات',
                                            'archive' => 'ملفات مضغوطة',
                                        ])
                                        ->placeholder('جميع الأنواع')
                                        ->live()
                                        ->columnSpan(1),
                                    Select::make('filterTags')
                                        ->label('الوسوم')
                                        ->multiple()
                                        ->options(Tag::pluck('name', 'id'))
                                        ->live()
                                        ->searchable()
                                        ->columnSpan(1),
                                    TextInput::make('filterSizeMin')
                                        ->label('الحجم الأدنى (ميجابايت)')
                                        ->numeric()
                                        ->live()
                                        ->debounce()
                                        ->columnSpan(1),
                                    TextInput::make('filterSizeMax')
                                        ->label('الحجم الأقصى (ميجابايت)')
                                        ->numeric()
                                        ->live()
                                        ->debounce()
                                        ->columnSpan(1),
                                ]),
                                Flex::make([
                                    Action::make('closeFilter')
                                        ->label('إغلاق')
                                        ->icon(Heroicon::XCircle)
                                        ->color('danger')
                                        ->action(function () {
                                            $this->showFilters = ! $this->showFilters;
                                            $this->clearCachedSchemas();
                                        }),
                                    Action::make('clearFilters')
                                        ->label('مسح الفلاتر')
                                        ->color('danger')
                                        ->outlined()
                                        ->disabled(fn () => ! $this->hasActiveFilters())
                                        ->action(function () {
                                            $this->reset(['filterTags', 'filterType', 'filterSizeMin', 'filterSizeMax']);
                                            $this->clearCachedSchemas();
                                            $this->resetPage();
                                        }),
                                ]),
                            ])
                            ->visible(fn () => $this->showFilters)
                            ->columnSpanFull(),
                        \Slimani\MediaManager\Components\Section::make()
                            ->heading(view('media-manager::components.breadcrumbs', ['breadcrumbs' => $this->breadcrumbs]))
                            ->columnSpan(fn () => ['lg' => $this->showDetails ? 3 : 4])
                            ->extraAttributes([
                                'class' => 'fi-media-grid-container',
                            ])
                            ->schema([

                                CustomRepeatableEntry::make('items')
                                    ->hiddenLabel()
                                    ->state($this->getItemsProperty())
                                    ->contained(false)
                                    ->schema(fn (CustomRepeatableEntry $component) => [
                                        MediaItem::make($item = $component->getItem())
                                            ->isPicker($this->isPicker)
                                            ->isAccepted(! ($this->isPicker && $item instanceof File) || $this->isAccepted($item)),
                                    ])
                                    ->extraAttributes([
                                        'class' => 'fi-media-grid',
                                    ])
                                    ->visible(fn () => $this->getItemsProperty()->isNotEmpty()),

                                EmptyState::make('لا توجد ملفات')
                                    ->description('قم برفع ملف أو إنشاء مجلد للبدء.')
                                    ->icon(Heroicon::Document)
                                    ->contained(false)
                                    ->footer([
                                        $this->createFolderAction(),
                                        $this->uploadAction(),
                                    ])
                                    ->visible(fn () => $this->getItemsProperty()->isEmpty()),

                                ViewEntry::make('pagination')
                                    ->view('media-manager::filament.pages.media-manager.pagination')
                                    ->viewData(['paginator' => $this->getItemsProperty()])
                                    ->visible(fn () => $this->getItemsProperty()->total() > 0),
                            ])
                            ->contained(false),

                        \Slimani\MediaManager\Components\Section::make()
                            ->heading('التفاصيل')
                            ->extraAttributes([
                                'class' => 'flex h-full no-negative-header-margin',
                            ])
                            ->columnSpan(['lg' => 1])
                            ->visible(fn () => $this->showDetails)
                            ->schema([
                                // 1. تفاصيل العنصر المحدد (عنصر واحد أو أكثر)
                                Grid::make(1)
                                    ->visible(fn () => count($this->selectedItems) > 0)
                                    ->schema(function () {
                                        if (count($this->selectedItems) > 1) {
                                            $data = $this->getSelectedItemsDataProperty();
                                            $items = $data['items'] ?? [];

                                            return [
                                                TextEntry::make('selection_title')
                                                    ->hiddenLabel()
                                                    ->state(fn () => count($this->selectedItems).' عناصر محددة')
                                                    ->weight(FontWeight::Bold)
                                                    ->size(TextSize::Large),

                                                Grid::make(1)->schema(fn () => collect($items)->map(function ($item) {
                                                    $type = $item instanceof Folder ? 'folder' : 'file';
                                                    $itemKey = "{$type}-{$item->id}";

                                                    return TextEntry::make('item_'.$itemKey)
                                                        ->hiddenLabel()
                                                        ->badge()
                                                        ->state($item->name)
                                                        ->icon($item instanceof Folder ? 'heroicon-m-folder' : 'heroicon-m-document')
                                                        ->iconColor($item instanceof Folder ? 'amber' : 'gray')
                                                        ->belowContent(function () use ($item) {
                                                            if ($item instanceof Folder) {
                                                                $itemsCount = $item->children_count + $item->files_count;

                                                                return TextEntry::make('items_count')
                                                                    ->hiddenLabel()
                                                                    ->state(" {$itemsCount} عناصر")
                                                                    ->size(TextSize::ExtraSmall)
                                                                    ->color('gray')
                                                                    ->badge();
                                                            } else {
                                                                return Flex::make([
                                                                    TextEntry::make('extension')
                                                                        ->hiddenLabel()
                                                                        ->state(filled($item->extension) ? str($item->extension)->upper() : 'ملف')
                                                                        ->size(TextSize::ExtraSmall)
                                                                        ->badge(),
                                                                    TextEntry::make('size')
                                                                        ->hiddenLabel()
                                                                        ->state(Number::fileSize($item->size ?? 0))
                                                                        ->size(TextSize::ExtraSmall)
                                                                        ->color('gray')
                                                                        ->badge(),
                                                                ])->extraAttributes([
                                                                    'class' => 'gap-1',
                                                                ]);
                                                            }
                                                        })
                                                        ->suffixActions([
                                                            Action::make('deselect')
                                                                ->iconButton()
                                                                ->icon(Heroicon::XMark)
                                                                ->color('danger')
                                                                ->action(fn () => $this->toggleSelection($itemKey)),
                                                            Action::make('locate')
                                                                ->iconButton()
                                                                ->icon(Heroicon::OutlinedMagnifyingGlassCircle)
                                                                ->action(fn () => $this->locateItem($itemKey)),
                                                            MediaAction::make($item->name)
                                                                ->iconButton()
                                                                ->slideOver()
                                                                ->icon(Heroicon::OutlinedEye)
                                                                ->media(fn () => $item->getUrl())
                                                                ->visible($item instanceof File),
                                                            Action::make('open_url')
                                                                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                                                ->iconButton()
                                                                ->url(fn () => $item->getUrl(), true)
                                                                ->visible($item instanceof File),
                                                        ]);
                                                })->toArray())
                                                    ->extraAttributes([
                                                        'class' => 'multi-item-select',
                                                    ]),

                                                TextEntry::make('selection_size')
                                                    ->label('الحجم الإجمالي')
                                                    ->state(fn () => Number::fileSize($this->getSelectedItemsDataProperty()['size'] ?? 0))
                                                    ->badge(),

                                                Flex::make([
                                                    TextEntry::make('selection_files')
                                                        ->label('الملفات')
                                                        ->state(fn () => collect($this->selectedItems)->filter(fn ($i) => str_starts_with($i, 'file-'))->count())
                                                        ->badge(),
                                                    TextEntry::make('selection_folders')
                                                        ->label('المجلدات')
                                                        ->state(fn () => $this->getSelectedItemsDataProperty()['folders_count'] ?? 0)
                                                        ->visible(fn () => ($this->getSelectedItemsDataProperty()['folders_count'] ?? 0) > 0)
                                                        ->badge(),
                                                ]),

                                            ];
                                        }

                                        $itemKey = reset($this->selectedItems);

                                        if (! $itemKey) {
                                            return [];
                                        }

                                        if (! str_contains($itemKey, '-')) {
                                            $itemKey = "file-{$itemKey}";
                                        }

                                        [$type, $id] = explode('-', $itemKey);

                                        if ($type === 'file') {
                                            $file = File::find($id);

                                            return $file ? $this->fileDetailsSchema($file) : [];
                                        }

                                        $folder = Folder::find($id);

                                        return $folder ? $this->folderDetailsSchema($folder) : [];
                                    }),

                                // 2. لا يوجد تحديد - عرض المجلد الحالي أو الجذر
                                Grid::make(1)
                                    ->visible(fn () => empty($this->selectedItems))
                                    ->schema(function () {
                                        if ($this->currentFolder) {
                                            return $this->folderDetailsSchema($this->currentFolder);
                                        }

                                        return [
                                            TextEntry::make('root_info')
                                                ->hiddenLabel()
                                                ->state('مكتبة الوسائط')
                                                ->weight(FontWeight::Bold)
                                                ->size(TextSize::Large),

                                            TextEntry::make('root_desc')
                                                ->hiddenLabel()
                                                ->state('حدد ملفاً أو مجلداً لعرض التفاصيل.')
                                                ->color('gray'),
                                        ];
                                    }),
                            ])
                            ->footerActions([
                                $this->bulkMoveAction()
                                    ->visible(fn () => count($this->selectedItems) > 0),
                                $this->bulkDeleteAction()
                                    ->visible(fn () => count($this->selectedItems) > 0),
                                $this->clearSelectionAction()
                                    ->visible(fn () => count($this->selectedItems) > 0),
                            ]),
                    ]),
            ]);
    }

    public function deleteFile(int $id): void
    {
        $file = File::find($id);
        if ($file) {
            $idToRemove = "file-{$id}";
            $this->selectedItems = collect($this->selectedItems)
                ->reject(fn ($item) => $item === $idToRemove)
                ->values()
                ->toArray();

            $file->delete();
            $this->selectedFileId = null;
            $this->clearCachedSchemas();
            $this->dispatch('media-deleted');
            $this->syncState();
        }
    }

    public function selectFile(int $id): void
    {
        $file = File::find($id);

        if ($this->isPicker && $file && ! $this->isAccepted($file)) {
            return;
        }

        $this->selectedFileId = $id;

        if ($this->isPicker && ! $this->multiple) {
            $this->selectedItems = ["file-{$id}"];
        } else {
            $this->toggleSelection("file-{$id}");

            return;
        }

        $this->clearCachedSchemas();

        $this->syncState();
    }

    public function toggleSelection($id): void
    {
        if (str_starts_with($id, 'file-')) {
            $fileId = str_replace('file-', '', $id);
            $file = File::find($fileId);

            if ($this->isPicker && $file && ! $this->isAccepted($file)) {
                return;
            }
        }

        if (collect($this->selectedItems)->contains($id)) {
            $this->selectedItems = collect($this->selectedItems)->reject(fn ($item) => $item === $id)->toArray();
        } else {
            if ($this->isPicker && ! $this->multiple) {
                $this->selectedItems = [$id];
            } else {
                $this->selectedItems[] = $id;
            }
        }

        $this->isEditingTags = false;
        $this->editingFolderId = null;
        $this->clearCachedSchemas();

        $this->syncState();
    }

    public function addToSelection(string $id): void
    {
        if (! in_array($id, $this->selectedItems)) {
            if ($this->isPicker && ! $this->multiple) {
                $this->selectedItems = [$id];
            } else {
                $this->selectedItems[] = $id;
            }
            $this->isEditingTags = false;
            $this->editingFolderId = null;
            $this->clearCachedSchemas();

            $this->syncState();
        }
    }

    protected function executeOnSelect(): void
    {
        if (! $this->serializedOnSelect) {
            return;
        }

        try {
            /** @var \Closure $callback */
            $callback = unserialize($this->serializedOnSelect)->getClosure();

            $fileIds = collect($this->selectedItems)
                ->filter(fn ($i) => str_starts_with($i, 'file-'))
                ->map(fn ($i) => str_replace('file-', '', $i))
                ->toArray();

            $files = File::whereIn('id', $fileIds)->get();

            $callback($files, $this);
        } catch (\Throwable $e) {
            Log::error('فشل تنفيذ دالة onSelect: '.$e->getMessage());
        }
    }

    protected function syncState(): void
    {
        $ids = collect($this->selectedItems)
            ->filter(fn ($i) => str_starts_with($i, 'file-'))
            ->map(fn ($i) => (int) str_replace('file-', '', $i))
            ->values()
            ->toArray();

        if ($this->statePath) {
            $this->dispatch('sync-picker-ids',
                statePath: $this->statePath,
                ids: implode(',', $ids),
            );
        }

        $this->dispatch('media-selection-synced', ids: $ids);

        $this->executeOnSelect();
    }

    public function isAccepted(File $file): bool
    {
        if (empty($this->acceptedFileTypes)) {
            return true;
        }

        foreach ($this->acceptedFileTypes as $type) {
            $typePattern = str_replace(['/', '*'], ['\/', '.*'], $type);
            if (preg_match("/^{$typePattern}$/i", $file->mime_type)) {
                return true;
            }
        }

        return false;
    }

    public function clearSelection(): void
    {
        $this->selectedItems = [];
        $this->clearCachedSchemas();
        $this->syncState();
    }

    /**
     * @param  ImageEntry  $component
     */
    public function getRepeaterItemKey(Entry $component, string $prefix, string $suffix): int
    {
        return str($component->getStatePath())
            ->replaceFirst($prefix, '')
            ->replaceEnd($suffix, '')
            ->toInteger();
    }

    protected function applySearchAndFiltersAndDeepSearchToQuery($query, $isFolder = false)
    {
        $column = $isFolder ? 'parent_id' : 'folder_id';

        if ($this->search || $this->hasActiveFilters()) {
            if ($this->currentFolderId !== null) {
                $folderIds = array_merge(
                    [$this->currentFolderId],
                    $this->currentFolder?->getAllDescendantIds() ?? []
                );
                $query->whereIn($column, $folderIds);
            }

            if ($this->search) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', "%{$this->search}%"));
                });
            }

            if (! empty($this->filterTags)) {
                $query->whereHas('tags', function ($q) {
                    $q->whereIn('media_tags.id', $this->filterTags);
                });
            }
        } else {
            $query->where($column, $this->currentFolderId);
        }

        return $query;
    }

    public function getFoldersProperty()
    {
        if (($this->filterType || ($this->filterSizeMin !== null && $this->filterSizeMin !== '') || ($this->filterSizeMax !== null && $this->filterSizeMax !== '')) && ($this->search || $this->hasActiveFilters())) {
            return collect();
        }

        $query = Folder::query()
            ->with(['tags'])
            ->withCount(['children', 'files']);

        $query = $this->applySearchAndFiltersAndDeepSearchToQuery($query, true);

        return $query->get();
    }

    public function getMediaFilesProperty()
    {
        $query = File::query()->with(['tags']);

        $query = $this->applySearchAndFiltersAndDeepSearchToQuery($query, false);

        if ($this->search || $this->hasActiveFilters()) {
            if ($this->filterType) {
                if ($this->filterType === 'document') {
                    $query->whereIn('mime_type', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain']);
                } elseif ($this->filterType === 'archive') {
                    $query->whereIn('mime_type', ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/x-tar']);
                } else {
                    $query->where('mime_type', 'like', "{$this->filterType}/%");
                }
            }

            if ($this->filterSizeMin !== null && $this->filterSizeMin !== '') {
                $query->where('size', '>=', (float) $this->filterSizeMin * 1024 * 1024);
            }

            if ($this->filterSizeMax !== null && $this->filterSizeMax !== '') {
                $query->where('size', '<=', (float) $this->filterSizeMax * 1024 * 1024);
            }
        }

        return $query->get();
    }

    public function getItemsProperty()
    {
        if ($this->showSelectedOnly) {
            $selectedFolderIds = [];
            $selectedFileIds = [];

            foreach ($this->selectedItems as $itemKey) {
                [$type, $id] = explode('-', $itemKey);
                if ($type === 'folder') {
                    $selectedFolderIds[] = $id;
                } else {
                    $selectedFileIds[] = $id;
                }
            }

            $folders = Folder::query()->whereIn('id', $selectedFolderIds)->with(['tags'])->withCount(['children', 'files'])->get();
            $files = File::query()->whereIn('id', $selectedFileIds)->with(['tags'])->get();
            $allItems = $folders->concat($files);
        } else {
            $folders = $this->getFoldersProperty();
            $files = $this->getMediaFilesProperty();
            $allItems = $folders->concat($files);
        }

        $sortProperty = $this->sortField ?: 'name';
        if ($sortProperty === 'mime_type') {
            $allItems = $allItems->map(function ($item) {
                $item->sort_type = $item instanceof Folder ? 'folder' : $item->mime_type;

                return $item;
            });
            $sortProperty = 'sort_type';
        }

        $sortFlags = $sortProperty === 'name' ? (SORT_NATURAL | SORT_FLAG_CASE) : SORT_REGULAR;

        $sortCallback = function ($item) use ($sortProperty) {
            if ($sortProperty === 'created_at') {
                return $item->created_at?->timestamp ?? 0;
            }

            return $item->{$sortProperty} ?? '';
        };

        $allItems = $allItems->sort(function ($a, $b) use ($sortProperty, $sortCallback) {
            $aPriority = $a instanceof Folder ? 0 : 1;
            $bPriority = $b instanceof Folder ? 0 : 1;

            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            $valA = $sortCallback($a);
            $valB = $sortCallback($b);

            $result = ($sortProperty === 'name')
                ? strnatcasecmp((string) $valA, (string) $valB)
                : $valA <=> $valB;

            return $this->sortDirection === 'desc' ? -$result : $result;
        });

        $allItems = $allItems->values();

        $page = $this->getPage($this->getPageName());
        $perPage = $this->perPage;

        if ($perPage === 'all') {
            $items = $allItems->mapWithKeys(fn ($item) => [
                ($item instanceof Folder ? 'folder-' : 'file-').$item->id => $item,
            ]);

            return new LengthAwarePaginator(
                $items,
                $allItems->count(),
                $allItems->count() ?: 1,
                1,
                [
                    'path' => Paginator::resolveCurrentPath(),
                    'pageName' => $this->getPageName(),
                ]
            );
        }
        $items = $allItems->forPage($page, $perPage)
            ->mapWithKeys(fn ($item) => [
                ($item instanceof Folder ? 'folder-' : 'file-').$item->id => $item,
            ]);

        return new LengthAwarePaginator(
            $items,
            $allItems->count(),
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $this->getPageName(),
            ]
        );
    }

    public function getSelectedFileProperty(): File|array|null
    {
        if (count($this->selectedItems) !== 1) {
            return null;
        }

        [$type, $id] = explode('-', reset($this->selectedItems));

        if ($type !== 'file') {
            return null;
        }

        return File::find($id);
    }

    public function getSelectedItemsDataProperty(): array
    {
        if (empty($this->selectedItems)) {
            return [];
        }

        $filesCount = 0;
        $foldersCount = 0;
        $totalSize = 0;
        $items = [];

        foreach ($this->selectedItems as $itemKey) {
            [$type, $id] = explode('-', $itemKey);

            if ($type === 'folder') {
                $folder = Folder::find($id);

                if ($folder) {
                    $items[] = $folder;
                    $descendantIds = $folder->getAllDescendantIds();
                    $allFolderIdsInThisSelection = array_merge([$folder->id], $descendantIds);

                    $foldersCount += count($allFolderIdsInThisSelection);

                    $filesCount += File::whereIn('folder_id', $allFolderIdsInThisSelection)->count();
                    $totalSize += File::whereIn('folder_id', $allFolderIdsInThisSelection)->sum('size');
                }
            } else {
                $file = File::find($id);
                if ($file) {
                    $items[] = $file;
                    $filesCount++;
                    $totalSize += $file->size;
                }
            }
        }

        return [
            'files_count' => $filesCount,
            'folders_count' => $foldersCount,
            'size' => $totalSize,
            'items' => $items,
        ];
    }

    protected function fileDetailsSchema(File $file): array
    {
        return [
            ImageEntry::make('sel_preview')
                ->hiddenLabel()
                ->state(static function () use ($file) {
                    $url = $file->getUrl('preview');

                    return str($url)->replace('–', '%E2%80%93')->toString();
                })
                ->imageWidth('100%')
                ->imageHeight('auto')
                ->extraImgAttributes(['class' => 'object-contain w-full'])
                ->visible(static function () use ($file) {
                    $mimeType = $file->mime_type;

                    return str($mimeType)->startsWith('image/') || str($mimeType)->startsWith('video/');
                }),

            TextEntry::make('sel_thumb')
                ->hiddenLabel()
                ->state(new HtmlString(Blade::render('<div class="flex items-center justify-center bg-gray-100 dark:bg-gray-800 rounded-lg h-32"><x-heroicon-o-document-text class="w-12 h-12 text-gray-400" /></div>')))
                ->html()
                ->visible(! str($file->mime_type)->startsWith('image/') && ! str($file->mime_type)->startsWith('video/')),

            TextEntry::make('sel_name')
                ->hiddenLabel()
                ->state($file->name.($file->extension ? '.'.$file->extension : ''))
                ->weight(FontWeight::Bold),

            Flex::make([
                TextEntry::make('sel_size')
                    ->label('الحجم')
                    ->state(Number::fileSize($file->size ?? 0))
                    ->badge(),
                TextEntry::make('sel_type')
                    ->label('النوع')
                    ->state($file->mime_type)
                    ->badge(),
            ]),

            TextEntry::make('sel_caption')
                ->state($file->caption)
                ->visible((bool) $file->caption),

            TextEntry::make('alt_text')
                ->state($file->alt_text)
                ->visible((bool) $file->alt_text),

            TextEntry::make('sel_path')
                ->label('الرابط العام')
                ->state($file->getUrl())
                ->copyable()
                ->limit(30)
                ->hintActions([
                    Action::make('locate')
                        ->iconButton()
                        ->icon(Heroicon::OutlinedMagnifyingGlassCircle)
                        ->action(fn () => $this->locateItem("file-{$file->id}")),
                    MediaAction::make($file->name)
                        ->iconButton()
                        ->slideOver()
                        ->icon(Heroicon::OutlinedEye)
                        ->media($file->getUrl()),
                    Action::make('open_url')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->iconButton()
                        ->url($file->getUrl(), true),
                ]),

            TextEntry::make('sel_created_at')
                ->label('تاريخ الرفع')
                ->state($file->created_at)
                ->since()
                ->color('gray'),

            TagsInput::make('activeTags')
                ->label('الوسوم')
                ->suggestions(Tag::pluck('name')->toArray())
                ->live()
                ->visible(fn () => $this->isEditingTags)
                ->hintAction(
                    Action::make('saveTags')
                        ->label('حفظ الوسوم')
                        ->icon('heroicon-m-check')
                        ->color('success')
                        ->action(fn () => $this->saveTags())
                ),

            TextEntry::make('tags_display')
                ->label('الوسوم')
                ->state($file->tags->pluck('name') ?: 'لا توجد وسوم')
                ->visible(fn () => ! $this->isEditingTags)
                ->badge()
                ->hintAction(
                    Action::make('editTags')
                        ->label('تعديل الوسوم')
                        ->icon('heroicon-m-pencil-square')
                        ->action(function () use ($file) {
                            $this->selectedFileId = $file->id;
                            $this->editingFolderId = null;
                            $this->activeTags = $file->tags->pluck('name')->toArray();
                            $this->isEditingTags = true;
                            $this->clearCachedSchemas();
                        })
                ),

        ];
    }

    protected function folderDetailsSchema(Folder $folder): array
    {
        $recursiveStats = $folder->getRecursiveStats();

        return [
            TextEntry::make('sel_folder_name')
                ->label('المجلد')
                ->state($folder->name)
                ->weight(FontWeight::Bold)
                ->size(TextSize::Large),

            TextEntry::make('sel_folder_created_at')
                ->label('تاريخ الإنشاء')
                ->state($folder->created_at)
                ->date(),

            TextEntry::make('sel_folder_total_size')
                ->label('الحجم الإجمالي')
                ->state(Number::fileSize($recursiveStats['total_size']))
                ->badge()
                ->color('success'),

            Flex::make([
                TextEntry::make('sel_folder_recursive_files')
                    ->label('الملفات')
                    ->state($recursiveStats['files_count'])
                    ->badge(),

                TextEntry::make('sel_folder_recursive_folders')
                    ->label('المجلدات')
                    ->state($recursiveStats['folders_count'])
                    ->badge(),
            ]),

            TagsInput::make('activeTags')
                ->label('الوسوم')
                ->suggestions(Tag::pluck('name')->toArray())
                ->live()
                ->visible(fn () => $this->isEditingTags)
                ->hintAction(
                    Action::make('saveFolderTags')
                        ->label('حفظ الوسوم')
                        ->icon('heroicon-m-check')
                        ->color('success')
                        ->action(fn () => $this->saveTags())
                ),

            TextEntry::make('folder_tags_display')
                ->label('الوسوم')
                ->state($folder->tags->pluck('name') ?: 'لا توجد وسوم')
                ->visible(fn () => ! $this->isEditingTags)
                ->badge()
                ->hintAction(
                    Action::make('editFolderTags')
                        ->label('تعديل الوسوم')
                        ->icon('heroicon-m-pencil-square')
                        ->action(function () use ($folder) {
                            $this->editingFolderId = $folder->id;
                            $this->selectedFileId = null;
                            $this->activeTags = $folder->tags->pluck('name')->toArray();
                            $this->isEditingTags = true;
                            $this->clearCachedSchemas();
                        })
                ),
        ];
    }

    public function locateItem(string $itemKey): void
    {
        $this->search = '';

        if (! str_contains($itemKey, '-')) {
            $itemKey = "file-{$itemKey}";
        }

        [$type, $id] = explode('-', $itemKey);

        $parentId = null;
        if ($type === 'folder') {
            $folder = Folder::find($id);
            if ($folder) {
                $parentId = $folder->parent_id;
            }
        } else {
            $file = File::find($id);
            if ($file) {
                $parentId = $file->folder_id;
            }
        }

        $this->setCurrentFolder($parentId);

        $this->showSelectedOnly = false;

        $perPage = (int) $this->perPage;
        if ($perPage > 0) {
            $folders = $this->getFoldersProperty();
            $files = $this->getMediaFilesProperty();
            $allItems = $folders->concat($files);

            $sortProperty = $this->sortField ?: 'name';
            if ($sortProperty === 'mime_type') {
                $allItems = $allItems->map(function ($item) {
                    $item->sort_type = $item instanceof Folder ? 'folder' : $item->mime_type;

                    return $item;
                });
                $sortProperty = 'sort_type';
            }

            $sortCallback = function ($item) use ($sortProperty) {
                if ($sortProperty === 'created_at') {
                    return $item->created_at?->timestamp ?? 0;
                }

                return $item->{$sortProperty} ?? '';
            };

            $allItems = $allItems->sort(function ($a, $b) use ($sortProperty, $sortCallback) {
                $aPriority = $a instanceof Folder ? 0 : 1;
                $bPriority = $b instanceof Folder ? 0 : 1;

                if ($aPriority !== $bPriority) {
                    return $aPriority <=> $bPriority;
                }

                $valA = $sortCallback($a);
                $valB = $sortCallback($b);

                $result = ($sortProperty === 'name')
                    ? strnatcasecmp((string) $valA, (string) $valB)
                    : $valA <=> $valB;

                return $this->sortDirection === 'desc' ? -$result : $result;
            })->values();

            $index = $allItems->search(function ($item) use ($type, $id) {
                return ($item instanceof Folder ? 'folder' : 'file') === $type && $item->id == $id;
            });

            if ($index !== false) {
                $page = floor($index / $perPage) + 1;
                $this->setPage($page, $this->getPageName());
            }
        }

        $this->clearCachedSchemas();
    }

    public function setCurrentFolder(?int $id): void
    {
        $this->currentFolderId = $id;
        $this->currentFolder = $id ? Folder::query()->with(['tags'])->withCount(['children', 'files'])->find($id) : null;
        $this->editingFolderId = null;
        $this->isEditingTags = false;
        $this->generateBreadcrumbs();
        $this->setPage(1, $this->getPageName());

        $this->dispatch('media-folder-changed', folderId: $id, statePath: $this->statePath);
    }

    public function saveTags(): void
    {
        $model = null;

        if (count($this->selectedItems) === 1) {
            [$type, $id] = explode('-', reset($this->selectedItems));
            if ($type === 'file') {
                $model = File::find($id);
            } else {
                $model = Folder::find($id);
            }
        } elseif ($this->editingFolderId) {
            $model = Folder::find($this->editingFolderId);
        } elseif ($this->currentFolderId) {
            $model = $this->currentFolder;
        }

        if ($model) {
            $tagIds = collect($this->activeTags)->map(function ($name) {
                return Tag::firstOrCreate(['name' => $name])->id;
            })->toArray();

            $model->tags()->sync($tagIds);
        }

        $this->isEditingTags = false;
        $this->editingFolderId = null;
        $this->clearCachedSchemas();
    }

    public function generateBreadcrumbs(): void
    {
        $breadcrumbs = [];
        $folder = $this->currentFolder;

        while ($folder) {
            array_unshift($breadcrumbs, [
                'id' => $folder->id,
                'name' => $folder->name,
            ]);
            $folder = $folder->parent;
        }

        $this->breadcrumbs = $breadcrumbs;
    }

    public function clearSelectionAction(): Action
    {
        return Action::make('clearSelection')
            ->label('مسح التحديد')
            ->icon(Heroicon::XMark)
            ->color('danger')
            ->outlined()
            ->action(fn () => $this->clearSelection());
    }

    public function bulkDeleteAction(): Action
    {
        return Action::make('bulkDelete')
            ->label('حذف')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('حذف العناصر المحددة؟')
            ->modalDescription('هل أنت متأكد من حذف العناصر المحددة؟ لا يمكن التراجع عن هذا الإجراء.')
            ->modalSubmitActionLabel('نعم، احذفها')
            ->action(fn () => $this->deleteSelectedItems());
    }

    public function bulkDeleteSelectedAction(): Action
    {
        return Action::make('bulkDeleteSelected')
            ->label('حذف المحددة')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('حذف العناصر المحددة؟')
            ->modalDescription('هل أنت متأكد من حذف العناصر المحددة؟ لا يمكن التراجع عن هذا الإجراء.')
            ->modalSubmitActionLabel('نعم، احذفها')
            ->action(fn () => $this->deleteSelectedItems());
    }

    public function bulkMoveAction(): Action
    {
        return Action::make('bulkMove')
            ->label('نقل')
            ->icon(Heroicon::ArrowsRightLeft)
            ->schema([
                SelectTree::make('folder_id')
                    ->label('المجلد الهدف')
                    ->query(Folder::query()->orderBy('name'), 'name', 'parent_id')
                    ->prepend([
                        'name' => 'الجذر',
                        'value' => 0,
                    ])
                    ->enableBranchNode()
                    ->withCount()
                    ->required()
                    ->searchable(),
            ])
            ->action(fn (array $data) => $this->moveSelectedItems($data['folder_id']));
    }

    protected function getFolderTreePath(Folder $folder): string
    {
        $path = [$folder->name];
        $parent = $folder->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' / ', $path);
    }

    public function createFolderAction(): Action
    {
        return Action::make('createFolder')
            ->label('إنشاء مجلد')
            ->icon(Heroicon::OutlinedFolderPlus)
            ->schema([
                TextInput::make('name')
                    ->label('اسم المجلد')
                    ->required(),
            ])
            ->action(function (array $data) {
                Folder::create([
                    'name' => $data['name'],
                    'parent_id' => $this->currentFolderId,
                ]);

                $this->dispatch('media-uploaded');
                $this->clearCachedSchemas();
            });
    }

    public function goUpAction(): Action
    {
        return Action::make('goUp')
            ->label('للأعلى')
            ->icon('heroicon-m-arrow-left')
            ->iconButton()
            ->color('gray')
            ->action(function () {
                if ($this->currentFolder) {
                    $this->setCurrentFolder($this->currentFolder->parent_id);
                }

                $this->dispatch('media-uploaded');
                $this->clearCachedSchemas();
            })->visible(fn () => $this->currentFolderId !== null);
    }

    public function uploadAction(): Action
    {
        return Action::make('upload')
            ->label('رفع')
            ->icon('heroicon-m-arrow-up-tray')
            ->schema([
                FileUpload::make('files')
                    ->label('الملفات')
                    ->multiple()
                    ->preserveFilenames()
                    ->disk(fn () => filament('media-manager')->getDisk())
                    ->required(),
                TagsInput::make('tags')
                    ->label('الوسوم')
                    ->suggestions(Tag::pluck('name')->toArray()),
                TextInput::make('caption')
                    ->label('التعليق'),
                TextInput::make('alt_text')
                    ->label('النص البديل'),
            ])
            ->action(function (array $data) {
                foreach ($data['files'] as $file) {
                    $filename = $file instanceof UploadedFile
                        ? $file->getClientOriginalName()
                        : basename($file);

                    $name = pathinfo($filename, PATHINFO_FILENAME);

                    $fileModel = File::create([
                        'name' => $name,
                        'uploaded_by_user_id' => auth()->id(),
                        'folder_id' => $this->currentFolderId,
                        'caption' => $data['caption'] ?? null,
                        'alt_text' => $data['alt_text'] ?? null,
                    ]);

                    if (isset($data['tags'])) {
                        $tagIds = collect($data['tags'])->map(function ($name) {
                            return Tag::firstOrCreate(['name' => $name])->id;
                        })->toArray();

                        $fileModel->tags()->sync($tagIds);
                    }

                    try {
                        $diskName = filament('media-manager')->getDisk();

                        if ($file instanceof UploadedFile) {
                            $media = $fileModel->addMediaFromString($file->get())
                                ->usingFileName($filename)
                                ->toMediaCollection('default', $diskName);
                        } else {
                            $disk = Storage::disk($diskName);

                            $pathsToTry = [
                                $file,
                                'livewire-tmp/'.$file,
                            ];

                            $actualPath = $file;
                            foreach ($pathsToTry as $candidate) {
                                try {
                                    if ($disk->exists($candidate)) {
                                        $actualPath = $candidate;
                                        break;
                                    }
                                } catch (\Throwable $e) {
                                    continue;
                                }
                            }

                            try {
                                $media = $fileModel->addMediaFromDisk($actualPath, $diskName)
                                    ->usingFileName($filename)
                                    ->toMediaCollection('default', $diskName);
                            } catch (\Throwable $e) {
                                try {
                                    $content = $disk->get($actualPath);
                                    $media = $fileModel->addMediaFromString($content)
                                        ->usingFileName($filename)
                                        ->toMediaCollection('default', $diskName);
                                } catch (\Throwable $finalError) {
                                    throw $e;
                                }
                            }
                        }

                        $fileModel->update([
                            'size' => $media->size,
                            'mime_type' => $media->mime_type,
                            'extension' => $media->extension,
                            'width' => $media->getCustomProperty('width'),
                            'height' => $media->getCustomProperty('height'),
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('خطأ في رفع الوسائط: '.$e->getMessage());

                        $fileModel->delete();

                        $this->dispatch('media-upload-error', message: $e->getMessage());
                    }
                }

                $this->dispatch('media-uploaded');
                $this->clearCachedSchemas();
            });
    }

    public function selectSelectedItems(): void
    {
        if (! $this->isPicker) {
            return;
        }

        $fileIds = collect($this->selectedItems)
            ->filter(fn ($id) => str_starts_with($id, 'file-'))
            ->map(fn ($id) => str_replace('file-', '', $id))
            ->toArray();

        if (empty($fileIds) && $this->selectedFileId) {
            $fileIds = [$this->selectedFileId];
        }

        if (empty($fileIds)) {
            Notification::make()
                ->title('يرجى تحديد ملف واحد على الأقل')
                ->warning()
                ->send();

            return;
        }

        $uuids = File::with('media')->whereIn('id', $fileIds)->get()->map(fn ($file) => $file->getFirstMedia('default')?->uuid)->filter()->values()->toArray();

        $this->dispatch('media-picker-selected', [
            'pickerId' => $this->pickerId,
            'uuids' => $uuids,
        ]);

        $this->dispatch('close-modal', id: 'media-browser-modal');
    }

    public function deleteSelectedItems(): void
    {
        foreach ($this->selectedItems as $itemKey) {
            if (! str_contains($itemKey, '-')) {
                $itemKey = "file-{$itemKey}";
            }

            [$type, $id] = explode('-', $itemKey);

            if ($type === 'folder') {
                $folder = Folder::find($id);
                if ($folder) {
                    $this->recursiveDeleteFolder($folder);
                }
            } else {
                $file = File::find($id);
                if ($file) {
                    $file->delete();
                }
            }
        }

        $this->selectedItems = [];
        $this->clearCachedSchemas();
        $this->syncState();
        $this->dispatch('media-updated');

        Notification::make()
            ->title('تم حذف العناصر بنجاح')
            ->success()
            ->send();
    }

    protected function recursiveDeleteFolder(Folder $folder): void
    {
        foreach ($folder->files as $file) {
            $file->delete();
        }

        foreach ($folder->children as $subFolder) {
            $this->recursiveDeleteFolder($subFolder);
        }

        $folder->delete();
    }

    public function moveSelectedItems(?int $targetFolderId): void
    {
        $targetFolderId = ($targetFolderId === 0 || $targetFolderId === null) ? null : $targetFolderId;

        foreach ($this->selectedItems as $itemKey) {
            if (! str_contains($itemKey, '-')) {
                $itemKey = "file-{$itemKey}";
            }

            [$type, $id] = explode('-', $itemKey);

            if ($type === 'folder') {
                $folder = Folder::find($id);
                if ($folder && $targetFolderId != $folder->id) {
                    $descendantIds = $folder->getAllDescendantIds();
                    if (! in_array($targetFolderId, $descendantIds)) {
                        $folder->update(['parent_id' => $targetFolderId]);
                    }
                }
            } else {
                $file = File::find($id);
                if ($file) {
                    $file->update(['folder_id' => $targetFolderId]);
                }
            }
        }

        $this->selectedItems = [];
        $this->clearCachedSchemas();
        $this->syncState();
        $this->dispatch('media-updated');

        Notification::make()
            ->title('تم نقل العناصر بنجاح')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('media-manager::livewire.media-browser');
    }
}
