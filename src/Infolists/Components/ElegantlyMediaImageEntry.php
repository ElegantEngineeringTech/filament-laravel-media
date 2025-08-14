<?php

namespace Filament\Infolists\Components;

use Closure;
use Elegantly\Media\Contracts\InteractWithMedia;
use Filament\Support\Concerns\HasMediaFilter;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Throwable;

class ElegantlyMediaImageEntry extends ImageEntry
{
    use HasMediaFilter;

    protected string|Closure $collection = 'default';

    protected string|Closure|null $group = null;

    protected string|Closure|null $conversion = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultImageUrl(function (ElegantlyMediaImageEntry $component, Model $record): ?string {
            if ($component->hasRelationship($record)) {
                $record = $component->getRelationshipResults($record);
            }

            /** @var InteractWithMedia[] $records */
            $records = Arr::wrap($record);

            $collection = $component->getCollection();

            foreach ($records as $record) {
                /** conversion specific fallback url are not supported */
                if ($conversion = $component->getConversion()) {
                    continue;
                }

                if (! $collection) {
                    continue;
                }

                $url = value($record->getMediaCollection($collection)?->fallback);

                if (blank($url)) {
                    continue;
                }

                return $url;
            }

            return null;
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

    public function getImageUrl(?string $state = null): ?string
    {
        $record = $this->getRecord();

        if (! $record) {
            return null;
        }

        if ($this->hasRelationship($record)) {
            $record = $this->getRelationshipResults($record);
        }

        /** @var InteractWithMedia[] $records */
        $records = Arr::wrap($record);

        foreach ($records as $record) {

            $media = $record->media->first(fn ($media): bool => $media->uuid === $state);

            if (! $media) {
                continue;
            }

            $conversion = $this->getConversion();

            if ($this->getVisibility() === 'private') {
                try {
                    return $media->getTemporaryUrl(
                        expiration: now()->addMinutes(5),
                        conversion: $conversion,
                    );
                } catch (Throwable $exception) {
                    // This driver does not support creating temporary URLs.
                }
            }

            return $media->getUrl(
                conversion: $conversion,
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

        if ($this->hasRelationship($record)) {
            $record = $this->getRelationshipResults($record);
        }

        /** @var InteractWithMedia[] $records */
        $records = Arr::wrap($record);

        $state = [];

        $collection = $this->getCollection();
        $group = $this->getGroup();

        foreach ($records as $record) {
            $state = [
                ...$state,
                ...$record->getMedia($collection, $group)
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
