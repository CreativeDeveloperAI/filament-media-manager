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
        return $this->pickerId ?? $this->getName();
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

        // We handle saving manually
        $this->saveRelationshipsUsing(null);
        $this->dehydrated(true);

        $this->pickerId = $this->getName();

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
                        ])->key("media-browser-{$pickerId}"),

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

                    // If we're picking for a Spatie collection, we want to set the state to UUIDs
                    $record = $component->getRecord();
                    if ($record instanceof HasMedia) {
                        $uuids = [];
                        foreach ($selectedFileIds as $id) {
                            $file = File::find($id);
                            if ($file && $file->getFirstMedia('default')) {
                                $uuids[] = $file->getFirstMedia('default')->uuid;
                            }
                        }
                        $component->state($component->isMultiple() ? $uuids : (Arr::first($uuids) ?? null));
                    } else {
                        $component->state($component->isMultiple() ? $selectedFileIds : (Arr::first($selectedFileIds) ?? null));
                    }
                })
        );

        $this->getUploadedFileUsing(static function (MediaPicker $component, $file): ?array {
            if (blank($file)) return null;

            $media = null;
            if (Str::isUuid($file)) {
                $media = Media::where('uuid', $file)->first();
            } elseif (is_numeric($file)) {
                $fileRecord = File::find($file);
                $media = $fileRecord?->getFirstMedia('default');
            }

            if (!$media) return null;

            return [
                'name' => $media->file_name,
                'size' => $media->size,
                'type' => $media->mime_type,
                'url' => $media->getUrl($component->getConversion()),
            ];
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
                    $state = $record->getAttribute($relationship->getForeignKeyName());
                } else {
                    try {
                        $state = $record->getAttribute($component->getName());
                    } catch (\Exception $e) {}
                }
            }

            if ($state instanceof Collection) {
                $state = $state->pluck('id')->toArray();
            }

            $component->state($state);
        });

        $this->saveRelationshipsUsing(static function (MediaPicker $component, $state): void {
            $record = $component->getRecord();
            if (! $record) return;

            $identifiers = $component->getIdentifiersFromState($state);
            $collection = $component->getCollection();

            if ($record instanceof HasMedia && $collection) {
                $currentMedia = $record->getMedia($collection);
                $currentMediaUuids = $currentMedia->pluck('uuid')->toArray();

                // Delete removed
                $toDelete = array_diff($currentMediaUuids, $identifiers);
                Media::whereIn('uuid', $toDelete)->where('model_id', $record->getKey())->delete();

                // Add new
                $toAdd = array_diff($identifiers, $currentMediaUuids);
                foreach ($toAdd as $uuid) {
                    $originalMedia = Media::where('uuid', $uuid)->first();
                    if ($originalMedia) {
                        $record->addMedia($originalMedia->getPath())
                            ->usingFileName($originalMedia->file_name)
                            ->usingName($originalMedia->name)
                            ->toMediaCollection($collection);
                    }
                }

                // Refresh to get new UUIDs for the component state
                $record->refresh();
                return;
            }

            // Fallback for non-spatie
            $relationship = $component->getRelationship();
            if ($relationship instanceof BelongsTo) {
                $record->setAttribute($relationship->getForeignKeyName(), Arr::first($identifiers));
                $record->save();
            }
        });
    }
}
