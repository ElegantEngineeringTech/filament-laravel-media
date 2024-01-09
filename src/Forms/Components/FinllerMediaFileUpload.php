<?php

namespace Filament\Forms\Components;

use Closure;
use Finller\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class FinllerMediaFileUpload extends FileUpload
{
    protected string|Closure|null $collection = null;

    protected string|Closure|null $group = null;

    protected string|Closure|null $disk = null;

    protected string|Closure|null $conversion = null;

    protected string|Closure|null $mediaName = null;

    /**
     * @var array<string, mixed> | Closure | null
     */
    protected array|Closure|null $metadata = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadStateFromRelationshipsUsing(static function (FinllerMediaFileUpload $component, Model $record): void {
            $files = $record->load('media')->getMedia($component->getCollection())
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

        $this->getUploadedFileUsing(static function (FinllerMediaFileUpload $component, string $file): ?array {
            if (! $component->getRecord()) {
                return null;
            }

            /** @var ?Media $media */
            $media = $component->getRecord()->getRelationValue('media')->firstWhere('uuid', $file);

            $url = null;

            $conversion = $component->getConversion();

            if ($component->getVisibility() === 'private') {

                try {
                    $url = $media?->getTemporaryUrl(
                        $conversion,
                        now()->addMinutes(5),
                    );
                } catch (Throwable $exception) {
                    // This driver does not support creating temporary URLs.
                }
            }

            $url ??= $media?->getUrl($conversion);

            return [
                'name' => $media->getAttributeValue('name') ?? $media->getAttributeValue('file_name'),
                'size' => $media->getAttributeValue('size'),
                'type' => $media->getAttributeValue('mime_type'),
                'url' => $url,
            ];
        });

        $this->saveRelationshipsUsing(static function (FinllerMediaFileUpload $component) {
            $component->deleteAbandonedFiles();
            $component->saveUploadedFiles();
        });

        $this->saveUploadedFileUsing(static function (FinllerMediaFileUpload $component, TemporaryUploadedFile $file, ?Model $record): ?string {
            if (! method_exists($record, 'addMedia')) {
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
                $file,
                collection_name: $component->getCollection(),
                collection_group: $component->getGroup(),
                name: $component->getMediaName($file),
                metadata: $component->getMetadata(),
            );

            //  $mediaAdder
            //     ->addCustomHeaders($component->getCustomHeaders())
            //     ->usingFileName($filename)
            //     ->usingName($component->getMediaName($file) ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            //     ->storingConversionsOnDisk($component->getConversionsDisk() ?? '')
            //     ->withCustomProperties($component->getCustomProperties())
            //     ->withManipulations($component->getManipulations())
            //     ->withResponsiveImagesIf($component->hasResponsiveImages())
            //     ->withProperties($component->getProperties())
            //     ->toMediaCollection($component->getCollection(), $component->getDiskName());

            return $media->getAttributeValue('uuid');
        });

        $this->reorderUploadedFilesUsing(static function (FinllerMediaFileUpload $component, array $state): array {
            $uuids = array_filter(array_values($state));

            $mediaClass = config('media.model', Media::class);

            $mappedIds = $mediaClass::query()->whereIn('uuid', $uuids)->pluck('id', 'uuid')->toArray();

            $mediaClass::setNewOrder([
                ...array_flip($uuids),
                ...$mappedIds,
            ]);

            return $state;
        });
    }

    public function collection(string|Closure|null $collection): static
    {
        $this->collection = $collection;

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

    public function getCollection(): string
    {
        return $this->evaluate($this->collection);
    }

    public function getConversion(): string
    {
        return $this->evaluate($this->conversion);
    }

    public function getDisk(): string
    {
        return $this->evaluate($this->disk);
    }

    public function getGroup(): string
    {
        return $this->evaluate($this->group);
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
