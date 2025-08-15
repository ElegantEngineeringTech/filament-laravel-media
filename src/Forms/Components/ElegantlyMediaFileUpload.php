<?php

declare(strict_types=1);

namespace Filament\Forms\Components;

use Closure;
use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\Models\Media;
use Filament\Support\Concerns\HasMediaFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ElegantlyMediaFileUpload extends FileUpload
{
    use HasMediaFilter;

    protected string|Closure $collection = 'default';

    protected string|Closure|null $group = null;

    protected string|Closure|null $diskName = null;

    protected string|Closure|null $conversion = null;

    protected string|Closure|null $mediaName = null;

    /**
     * @var array<string, mixed> | Closure | null
     */
    protected array|Closure|null $metadata = null;

    /**
     * @var array<string, mixed> | Closure | null
     */
    protected array|Closure|null $properties = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadStateFromRelationshipsUsing(static function (ElegantlyMediaFileUpload $component, InteractWithMedia $record): void {
            /** @var Model&InteractWithMedia $record */
            $media = $record->load('media')->getMedia($component->getCollection())
                ->when(
                    $component->hasMediaFilter(),
                    fn (Collection $media) => $component->filterMedia($media)
                )
                ->when(
                    ! $component->isMultiple(),
                    fn (Collection $media): Collection => $media->take(1),
                )
                ->mapWithKeys(function (Media $media): array {
                    $uuid = $media->getAttributeValue('uuid');

                    return [$uuid => $uuid];
                })
                ->toArray();

            $component->state($media);
        });

        $this->afterStateHydrated(static function (BaseFileUpload $component, string|array|null $state): void {
            if (is_array($state)) {
                return;
            }

            $component->state([]);
        });

        $this->beforeStateDehydrated(null);

        $this->dehydrated(false);

        $this->getUploadedFileUsing(static function (ElegantlyMediaFileUpload $component, string $file): ?array {
            if (! $component->getRecord()) {
                return null;
            }

            /** @var ?Media $media */
            $media = $component->getRecord()->getRelationValue('media')->firstWhere('uuid', $file);

            $url = null;

            if ($component->getVisibility() === 'private') {
                $conversion = $component->getConversion();

                try {
                    $url = $media?->getTemporaryUrl(
                        expiration: now()->addMinutes(5),
                        conversion: $conversion,
                        fallback: true,
                    );
                } catch (Throwable $exception) {
                    // This driver does not support creating temporary URLs.
                }
            }

            $url ??= $media?->getUrl(
                conversion: $component->getConversion(),
                fallback: true,
            );

            return [
                'name' => $media?->getAttributeValue('name') ?? $media?->getAttributeValue('file_name'),
                'size' => $media?->getAttributeValue('size'),
                'type' => $media?->getAttributeValue('mime_type'),
                'url' => $url,
            ];
        });

        $this->saveRelationshipsUsing(static function (ElegantlyMediaFileUpload $component) {
            $component->deleteAbandonedFiles();
            $component->saveUploadedFiles();
        });

        $this->saveUploadedFileUsing(static function (ElegantlyMediaFileUpload $component, TemporaryUploadedFile $file, ?InteractWithMedia $record): ?string {

            try {
                if (! $file->exists()) {
                    return null;
                }
            } catch (UnableToCheckFileExistence $exception) {
                return null;
            }

            $media = $record->addMedia(
                file: $file->getRealPath(),
                collectionName: $component->getCollection(),
                collectionGroup: $component->getGroup(),
                name: $component->getMediaName($file),
                disk: $component->getDiskName() ?: null,
                metadata: $component->getMetadata(),
                attributes: $component->getProperties(),
            );

            return $media->getAttributeValue('uuid');
        });

        $this->reorderUploadedFilesUsing(static function (ElegantlyMediaFileUpload $component, ?Model $record, array $state): array {
            $uuids = array_filter(array_values($state));

            $mediaClass = ($record && method_exists($record, 'getMediaModel')) ? $record->getMediaModel() : null;
            $mediaClass ??= config('media-library.media_model', Media::class);

            $mappedIds = $mediaClass::query()->whereIn('uuid', $uuids)->pluck(app($mediaClass)->getKeyName(), 'uuid')->toArray();

            $mediaClass::setNewOrder([
                ...array_flip($uuids),
                ...$mappedIds,
            ]);

            return $state;
        });
    }

    public function collection(string|Closure $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function group(string|Closure|null $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function conversion(string|Closure|null $conversion): static
    {
        $this->conversion = $conversion;

        return $this;
    }

    /**
     * @param  array<string, mixed> | Closure | null  $properties
     */
    public function customProperties(array|Closure|null $properties): static
    {
        return $this->metadata($properties);
    }

    /**
     * @param  array<string, mixed> | Closure | null  $metadata
     */
    public function metdata(array|Closure|null $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * @param  array<string, mixed> | Closure | null  $properties
     */
    public function properties(array|Closure|null $properties): static
    {
        $this->properties = $properties;

        return $this;
    }

    public function deleteAbandonedFiles(): void
    {
        /** @var InteractWithMedia $record */
        $record = $this->getRecord();

        $record
            ->getMedia($this->getCollection() ?? 'default')
            ->whereNotIn('uuid', array_keys($this->getState() ?? []))
            ->when($this->hasMediaFilter(), fn (Collection $media): Collection => $this->filterMedia($media))
            ->each(fn (Media $media) => $record->deleteMedia($media->id));
    }

    public function getDiskName(): string
    {
        return $this->evaluate($this->diskName) ?? '';
    }

    public function getCollection(): string
    {
        return $this->evaluate($this->collection);
    }

    public function getGroup(): ?string
    {
        return $this->evaluate($this->group);
    }

    public function getConversion(): ?string
    {
        return $this->evaluate($this->conversion);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->evaluate($this->metadata) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->evaluate($this->properties) ?? [];
    }

    public function mediaName(string|Closure|null $name): static
    {
        $this->mediaName = $name;

        return $this;
    }

    public function getMediaName(TemporaryUploadedFile $file): ?string
    {
        return $this->evaluate($this->mediaName, [
            'file' => $file,
        ]);
    }
}
