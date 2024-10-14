<?php

namespace Filament\Forms\Components;

use Closure;
use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ElegantlyMediaFileUpload extends FileUpload
{
    protected string|Closure|null $collection = null;

    protected string|Closure|null $group = null;

    protected string|Closure|null $diskName = null;

    protected string|Closure|null $conversion = null;

    protected string|Closure|null $mediaName = null;

    /**
     * @var array<string, mixed> | Closure | null
     */
    protected array|Closure|null $metadata = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadStateFromRelationshipsUsing(static function (ElegantlyMediaFileUpload $component, Model $record): void {
            $files = $record
                ->getMedia($component->getCollection(), $component->getGroup())
                ->when(
                    ! $component->isMultiple(),
                    fn (Collection $files): Collection => $files->take(1),
                )
                ->mapWithKeys(function (Media $file): array {
                    $uuid = $file->getAttributeValue('uuid');

                    return [$uuid => $uuid];
                })
                ->toArray();

            $component->state($files);
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
            /** @var null|(Model&InteractWithMedia) $record */
            $record = $component->getRecord();

            if (! $record) {
                return null;
            }

            /** @var ?Media $media */
            $media = $record->getMedia()->firstWhere('uuid', $file);

            if (! $media) {
                return null;
            }

            $url = null;

            $conversion = $component->getConversion();

            if ($component->getVisibility() === 'private') {

                try {
                    $url = $media?->getTemporaryUrl(
                        expiration: now()->addMinutes(5),
                        conversion: $conversion,
                    );
                } catch (Throwable $exception) {
                    // This driver does not support creating temporary URLs.
                }
            }

            $url ??= $media?->getUrl($conversion);

            return [
                'name' => $media->getName($conversion),
                'size' => $media->getSize($conversion),
                'type' => $media->getMimeType($conversion),
                'url' => $url,
            ];
        });

        $this->saveRelationshipsUsing(static function (ElegantlyMediaFileUpload $component) {
            $component->deleteAbandonedFiles();
            $component->saveUploadedFiles();
        });

        $this->saveUploadedFileUsing(static function (ElegantlyMediaFileUpload $component, TemporaryUploadedFile $file, ?Model $record): ?string {
            if (! ($record instanceof InteractWithMedia)) {
                return $file;
            }

            try {
                if (! $file->exists()) {
                    return null;
                }
            } catch (UnableToCheckFileExistence $exception) {
                return null;
            }

            $media = $record->addMedia(
                $file->getRealPath(),
                collectionName: $component->getCollection(),
                collectionGroup: $component->getGroup(),
                name: $component->getMediaName($file),
                // metadata: $component->getMetadata(),
                disk: $component->getDiskName()
            );

            return $media->getAttributeValue('uuid');
        });

        $this->reorderUploadedFilesUsing(static function (ElegantlyMediaFileUpload $component, array $state): array {
            $uuids = array_filter(array_values($state));

            /** @var class-string<Media> */
            $mediaClass = config('media.model', Media::class);

            $mediaClass::reorder($uuids, using: 'uuid');

            return $state;
        });
    }

    public function collection(string|Closure|null $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function diskName(string|Closure|null $diskName): static
    {
        $this->diskName = $diskName;

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
     * @param  array<string, mixed> | Closure | null  $metadata
     */
    public function metadata(array|Closure|null $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function deleteAbandonedFiles(): void
    {
        /** @var Model $record */
        $record = $this->getRecord();

        $record
            ->getMedia($this->getCollection(), $this->getGroup())
            ->whereNotIn('uuid', array_keys($this->getState() ?? []))
            ->each(fn (Media $media) => $media->delete());
    }

    public function getCollection(): ?string
    {
        return $this->evaluate($this->collection);
    }

    public function getConversion(): ?string
    {
        return $this->evaluate($this->conversion);
    }

    public function getGroup(): ?string
    {
        return $this->evaluate($this->group);
    }

    public function getDiskName(): string
    {
        return $this->evaluate($this->diskName) ?? config('filament.default_filesystem_disk') ?? config('media.disk');
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->evaluate($this->metadata) ?? [];
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
