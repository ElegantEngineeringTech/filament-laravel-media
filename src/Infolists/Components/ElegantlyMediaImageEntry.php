<?php

namespace Filament\Infolists\Components;

use Closure;
use Elegantly\Media\Contracts\InteractWithMedia;
use Elegantly\Media\Models\Media;
use Filament\Support\Concerns\HasMediaFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Throwable;

class ElegantlyMediaImageEntry extends ImageEntry
{
    use HasMediaFilter;

    protected string|Closure $collectionName = 'default';

    protected string|Closure|null $groupName = null;

    protected string|Closure|null $conversionName = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultImageUrl(function (ElegantlyMediaImageEntry $component, Model $record): ?string {
            if ($component->hasStateRelationship($record)) {
                $record = $component->getStateRelationshipResults($record);
            }

            /** @var InteractWithMedia[] $records */
            $records = Arr::wrap($record);

            $collectionName = $component->getCollectionName();

            foreach ($records as $record) {
                /** conversion specific fallback url are not supported */
                if ($conversionName = $component->getConversionName()) {
                    continue;
                }

                if (! $collectionName) {
                    continue;
                }

                $url = value($record->getMediaCollection($collectionName)?->fallback);

                if (blank($url)) {
                    continue;
                }

                return $url;
            }

            return null;
        });
    }

    public function collectionName(string|Closure $collectionName): static
    {
        $this->collectionName = $collectionName;

        return $this;
    }

    public function groupName(string|Closure|null $groupName): static
    {
        $this->groupName = $groupName;

        return $this;
    }

    public function conversionName(string|Closure|null $conversionName): static
    {
        $this->conversionName = $conversionName;

        return $this;
    }

    public function getCollectionName(): string
    {
        return $this->evaluate($this->collectionName);
    }

    public function getGroupName(): ?string
    {
        return $this->evaluate($this->groupName);
    }

    public function getConversionName(): ?string
    {
        return $this->evaluate($this->conversionName);
    }

    public function getImageUrl(?string $state = null): ?string
    {
        $record = $this->getRecord();

        if (! $record) {
            return null;
        }

        if ($this->hasStateRelationship($record)) {
            $record = $this->getStateRelationshipResults($record);
        }

        /** @var InteractWithMedia[] $records */
        $records = Arr::wrap($record);

        foreach ($records as $record) {

            /** @var ?Media $media */
            $media = $record->getRelationValue('media')->first(fn (Media $media): bool => $media->uuid === $state);

            if (! $media) {
                continue;
            }

            $conversionName = $this->getConversionName();

            if ($this->getVisibility() === 'private') {
                try {
                    return $media->getTemporaryUrl(
                        expiration: now()->addMinutes(5),
                        conversion: $conversionName,
                    );
                } catch (Throwable $exception) {
                    // This driver does not support creating temporary URLs.
                }
            }

            return $media->getUrl(
                conversion: $conversionName,
            );
        }

        return null;
    }

    /**
     * @return array<string>
     */
    public function getState(): array
    {
        $record = $this->getRecord();

        if ($this->hasStateRelationship($record)) {
            $record = $this->getStateRelationshipResults($record);
        }

        /** @var InteractWithMedia[] $records */
        $records = Arr::wrap($record);

        $state = [];

        $collectionName = $this->getCollectionName();
        $groupName = $this->getGroupName();

        foreach ($records as $record) {
            $state = [
                ...$state,
                ...$record->getMedia($collectionName, $groupName)
                    ->when(
                        $this->hasMediaFilter(),
                        fn (Collection $media) => $this->filterMedia($media)
                    )
                    ->sortBy('order_column')
                    ->pluck('uuid')
                    ->all(),
            ];
        }

        return array_unique($state);
    }
}
