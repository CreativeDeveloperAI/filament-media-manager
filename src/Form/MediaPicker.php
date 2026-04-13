<?php

namespace Slimani\MediaManager\Form;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Livewire;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Slimani\MediaManager\Livewire\MediaBrowser;
use Slimani\MediaManager\Models\File;
use Slimani\MediaManager\Models\Folder;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaPicker extends FileUpload
{
    protected string $pickerId;

    protected string|\Closure|null $collection = null;

    protected string|\Closure|null $relationship = null;

    protected string|\Closure|null $directory = null;

    protected string|\Closure|null $conversion = null;

    public function conversion(string|\Closure|null $name): static
    {
        $this->conversion = $name;

        return $this;
    }

    public function getConversion(): ?string
    {
        return $this->evaluate($this->conversion) ?? '';
    }

    public function getPickerId(): string
    {
        return $this->pickerId ?? $this->getStatePath();
    }

    public function relationship(string|\Closure|null $name = null): static
    {
        $this->relationship = $name ?? $this->getName();

        return $this;
    }

    public function collection(string|\Closure|null $name): static
    {
        $this->collection = $name;

        return $this;
    }

    public function getCollection(): string
    {
        return $this->evaluate($this->collection) ?? 'default';
    }

    public function directory(string|\Closure|null $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function getDirectory(): ?string
    {
        return $this->evaluate($this->directory);
    }

    public function getRelationship(): ?Relation
    {
        $name = $this->evaluate($this->relationship) ?: $this->getName();

        if (! $name) {
            return null;
        }

        $record = $this->getRecord();

        if (! $record) {
            return null;
        }

        if (! method_exists($record, $name)) {
            return null;
        }

        $relationship = $record->{$name}();

        if (! $relationship instanceof Relation) {
            return null;
        }

        return $relationship;
    }

    protected function getIdentifiersFromState($state): array
    {
        return array_map('strval', array_filter(Arr::wrap($state)));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->saveRelationshipsUsing(null);
        $this->fetchFileInformation(false);

        $this->pickerId = str(static::class)->afterLast('\\')->after('-')->append('-')->append($this->getName())->toString();

        $this->hintAction(
            Action::make('browse_media')
                ->label('تصفح الوسائط')
                ->icon(Heroicon::FolderOpen)
                ->color('primary')
                ->schema(function (MediaPicker $component, Action $action): array {
                    $pickerId = $component->getPickerId();
                    $actionIndex = $action->getNestingIndex() ?? array_key_last($action->getLivewire()->mountedActions);
                    $statePath = "mountedActions.{$actionIndex}.data.selected_ids";

                    $actionData = $action->getLivewire()->mountedActions[$actionIndex]['data'] ?? [];
                    $selectedIds = $actionData['selected_ids'] ?? null;
                    $currentFolderId = $actionData['current_folder_id'] ?? null;

                    $items = $selectedIds
                        ? array_map(fn ($id) => "file-{$id}", array_filter(explode(',', $selectedIds)))
                        : collect((array) ($component->getState() ?? []))
                            ->map(fn ($id) => str_starts_with($id, 'file-') ? $id : "file-{$id}")
                            ->toArray();

                    return [
                        Livewire::make(MediaBrowser::class, [
                            'isPicker' => true,
                            'multiple' => $component->isMultiple(),
                            'selectedItems' => $items,
                            'pickerId' => $pickerId,
                            'statePath' => $statePath,
                            'acceptedFileTypes' => $component->getAcceptedFileTypes(),
                            'onSelect' => null,
                            'currentFolderId' => (int) $currentFolderId ?: null,
                        ])->key("media-browser-{$pickerId}-{$actionIndex}"),

                        Hidden::make('selected_ids')
                            ->extraAttributes(fn ($component) => [
                                'x-on:sync-picker-ids.window' => "\$event.detail.statePath === '{$statePath}' ? \$wire.set('{$component->getStatePath()}', \$event.detail.ids, false) : null",
                            ]),
                    ];
                })
                ->slideOver()
                ->modalWidth('6xl')
                ->action(function (MediaPicker $component, array $data) {
                    $selectedFileIds = array_filter(explode(',', $data['selected_ids'] ?? ''));
                    $record = $component->getRecord();
                    $collection = $component->getCollection();

                    if ($record instanceof HasMedia && $collection) {
                        $spatieMediaUuidsFromFiles = [];
                        foreach ($selectedFileIds as $fileId) {
                            $file = File::find($fileId);
                            if ($file && $file->getFirstMedia('default')) {
                                $spatieMediaUuidsFromFiles[] = $file->getFirstMedia('default')->uuid;
                            }
                        }
                        $component->state($component->isMultiple() ? $spatieMediaUuidsFromFiles : (Arr::first($spatieMediaUuidsFromFiles) ?? null));
                    } else {
                        $component->state($selectedFileIds);
                    }
                })
        );

        $this->getUploadedFileUsing(static function (MediaPicker $component, string $file): ?array {
            if (Str::isUuid($file)) {
                $media = Media::where('uuid', $file)->first();
                if ($media) {
                    $url = null;
                    try {
                        $url = $media->getTemporaryUrl(now()->addMinutes(20), $component->getConversion());
                    } catch (\Throwable $e) {
                        $url = $media->getUrl($component->getConversion());
                    }

                    return [
                        'name' => $media->name,
                        'size' => $media->size,
                        'type' => $media->mime_type,
                        'url' => $url,
                    ];
                }
            }

            if (is_numeric($file)) {
                $fileRecord = File::find($file);
                if ($fileRecord) {
                    $media = $fileRecord->getFirstMedia('default');
                    if ($media) {
                        return [
                            'name' => $media->name ?? $fileRecord->name,
                            'size' => $media->size ?? $fileRecord->size ?? 0,
                            'type' => $media->mime_type ?? $fileRecord->mime_type,
                            'url' => $fileRecord->getUrl($component->getConversion()),
                        ];
                    }
                }
            }

            return null;
        });

        $this->saveUploadedFileUsing(static function (MediaPicker $component, TemporaryUploadedFile $file): ?string {
            $folderId = null;
            $directory = $component->getDirectory();

            if ($directory) {
                $segments = explode('/', trim($directory, '/'));
                $parentId = null;

                foreach ($segments as $segment) {
                    $folder = Folder::firstOrCreate([
                        'name' => $segment,
                        'parent_id' => $parentId,
                    ]);
                    $parentId = $folder->id;
                }
                $folderId = $parentId;
            }

            $fileModel = File::create([
                'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'uploaded_by_user_id' => auth()->id(),
                'folder_id' => $folderId,
            ]);

            $media = $fileModel->addMediaFromString($file->get())
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('default');

            $fileModel->update([
                'size' => $media->size,
                'mime_type' => $media->mime_type,
                'extension' => $media->extension,
                'width' => $media->getCustomProperty('width'),
                'height' => $media->getCustomProperty('height'),
            ]);

            return (string) $fileModel->id;
        });

        $this->afterStateHydrated(static function (MediaPicker $component, $state): void {
            $record = $component->getRecord();
            $collection = $component->getCollection();

            if ($record instanceof HasMedia && $collection) {
                $media = $record->getMedia($collection);
                if ($media->isNotEmpty()) {
                    $component->state($component->isMultiple()
                        ? $media->pluck('uuid')->toArray()
                        : $media->first()->uuid
                    );

                    return;
                }
            }

            if (blank($state) && $record) {
                $relationship = $component->getRelationship();
                if ($relationship) {
                    if ($relationship instanceof BelongsTo) {
                        $state = $record->getAttribute($relationship->getForeignKeyName());
                    } elseif ($relationship instanceof BelongsToMany or $relationship instanceof MorphToMany) {
                        $state = $relationship->get();
                    }
                } else {
                    try {
                        $state = $record->getAttribute($component->getName());
                    } catch (\Exception $e) {
                        // Column might not exist
                    }
                }
            }

            if ($state instanceof Collection) {
                $component->state($state->map(fn ($file) => (string) $file->id)->filter()->values()->toArray());

                return;
            }

            if ($state instanceof Model) {
                $component->state($component->isMultiple() ? [(string) $state->id] : (string) $state->id);

                return;
            }

            if (is_scalar($state) && $state !== '') {
                $component->state($component->isMultiple() ? [(string) $state] : (string) $state);

                return;
            }

            if (empty($state)) {
                $component->state($component->isMultiple() ? [] : null);
            }
        });

        $this->dehydrateStateUsing(static function (MediaPicker $component, $state) {
            $record = $component->getRecord();
            if ($record instanceof HasMedia) {
                return $state; // Keep as is, will be handled in saveRelationships
            }

            $identifiers = $component->getIdentifiersFromState($state);

            if ($component->isMultiple()) {
                return $identifiers;
            }

            return $identifiers[0] ?? null;
        });

        $this->saveRelationshipsUsing(static function (MediaPicker $component, $state): void {
            $record = $component->getRecord();
            if (! $record) {
                return;
            }

            $identifiers = $component->getIdentifiersFromState($state);
            $collection = $component->getCollection();

            if ($record instanceof HasMedia && $collection) {
                $currentMediaUuids = $record->getMedia($collection)->pluck('uuid')->toArray();

                // Delete media that is no longer selected
                $toDelete = array_diff($currentMediaUuids, $identifiers);
                Media::whereIn('uuid', $toDelete)->delete();

                // Add new media
                $toAdd = array_diff($identifiers, $currentMediaUuids);
                foreach ($toAdd as $id) {
                    $media = Media::where('uuid', $id)->first();
                    if ($media) {
                        $record->addMedia($media->getPath())
                            ->usingFileName($media->file_name)
                            ->usingName($media->name)
                            ->toMediaCollection($collection);
                    }
                }

                // IMPORTANT: Refresh the state to avoid 'loading' state after save
                $record->refresh();
                $component->state($component->isMultiple()
                    ? $record->getMedia($collection)->pluck('uuid')->toArray()
                    : $record->getMedia($collection)->first()?->uuid
                );

                return;
            }

            $relationship = $component->getRelationship();
            if ($relationship instanceof BelongsTo) {
                $column = $relationship->getForeignKeyName();
                $id = $identifiers[0] ?? null;

                if ($record->{$column} != $id) {
                    $record->{$column} = $id;
                    $record->save();
                }

                return;
            }

            if ($relationship instanceof BelongsToMany || $relationship instanceof MorphToMany) {
                $pivotData = [];
                $relCollection = $component->getCollection() ?: $component->getName();

                foreach ($identifiers as $id) {
                    $pivotData[$id] = ['collection' => $relCollection];
                }

                $relationship->sync($pivotData);

                return;
            }

            if (! $relationship && ! $component->isMultiple()) {
                try {
                    $record->{$component->getName()} = $identifiers[0] ?? null;
                    $record->save();
                } catch (\Exception $e) {
                    // Column doesn't exist, ignore if we are using spatie collection
                }
            }
        });
    }

    public function getValidationRules(): array
    {
        return [];
    }
}
