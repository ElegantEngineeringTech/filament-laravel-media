<?php

namespace Filament\Forms\Components\RichEditor\FileAttachmentProviders;

use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\Models\Media;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ElegantlyMediaFileAttachmentProvider implements FileAttachmentProvider
{
    /**
     * @var Collection<string, Media>
     */
    protected Collection $media;

    protected RichContentAttribute $attribute;

    protected string $collectionName = 'default';

    public static function make(): static
    {
        return app(static::class);
    }

    public function collectionName(?string $collectionName): static
    {
        $this->collectionName = $collectionName;

        return $this;
    }

    public function attribute(RichContentAttribute $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function getExistingModel(): ?InteractWithMedia
    {
        $model = $this->attribute->getModel();

        if (! $model->exists) {
            return null;
        }

        return $model;
    }

    /**
     * @return Collection<string, Media>
     */
    public function getMedia(): ?Collection
    {
        if (isset($this->media)) {
            return $this->media;
        }

        /** @var Collection<string, Media> $media */
        $media = $this->getExistingModel()?->getMedia($this->getCollectionName())->keyBy('uuid');

        return $this->media = $media;
    }

    public function getFileAttachmentUrl(mixed $file): ?string
    {
        $media = $this->getMedia();

        if (! $media) {
            return null;
        }

        if (! $media->has($file)) {
            return null;
        }

        $fileAttachment = $media->get($file);

        if ($this->attribute->getFileAttachmentsVisibility() === 'private') {
            try {
                return $fileAttachment->getTemporaryUrl(
                    expiration: now()->addMinutes(30)->endOfHour(),
                );
            } catch (Throwable $exception) {
                // This driver does not support creating temporary URLs.
            }
        }

        return $fileAttachment->getUrl();
    }

    public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
    {
        return $this->getExistingModel() /** @phpstan-ignore method.notFound */
            ->addMedia(
                file: $file->getRealPath(),
                collectionName: $this->getCollectionName(),
                disk: $this->attribute->getFileAttachmentsDiskName(),
                name: (string) Str::ulid()
            )
            ->uuid;
    }

    /**
     * @param  array<mixed>  $exceptIds
     */
    public function cleanUpFileAttachments(array $exceptIds): void
    {
        $model = $this->getExistingModel();
        $collectionName = $this->getCollectionName();

        $model->clearMediaCollection(
            $collectionName,
            except: $model->getMedia($collectionName)->whereIn('uuid', $exceptIds)->pluck('id')->all()
        );

    }

    public function getDefaultFileAttachmentVisibility(): ?string
    {
        return 'private';
    }

    public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
    {
        return true;
    }

    public function getCollectionName(): string
    {
        return $this->collectionName ?? $this->attribute->getName();
    }
}
