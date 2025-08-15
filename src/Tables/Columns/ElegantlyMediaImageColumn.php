<?php

namespace Filament\Tables\Columns;

use Closure;
use Elegantly\Media\Contracts\InteractWithMedia;
use Filament\Support\Concerns\HasMediaFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Throwable;

class ElegantlyMediaImageColumn extends ImageColumn
{
    use HasMediaFilter;

    protected string|Closure $collection = 'default';

    protected string|Closure|null $group = null;

    protected string|Closure|null $conversion = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultImageUrl(function (ElegantlyMediaImageColumn $column, Model $record): ?string {
            if ($column->hasRelationship($record)) {
                $record = $column->getRelationshipResults($record);
            }
            /** @var InteractWithMedia[] $records */
            $records = Arr::wrap($record);

            $collection = $column->getCollection();

            foreach ($records as $record) {
                /** conversion specific fallback url are not supported */
                if ($conversion = $column->getConversion()) {
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
        return $this->cacheState(function (): array {
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
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>|Relation  $query
     * @return Builder<TModel>|Relation
     */
    public function applyEagerLoading(Builder|Relation $query): Builder|Relation
    {
        if ($this->isHidden()) {
            return $query;
        }

        /** @phpstan-ignore-next-line */
        $modifyMediaQuery = fn (Builder|Relation $query) => $query->ordered();

        if ($this->hasRelationship($query->getModel())) {
            return $query->with([
                "{$this->getRelationshipName($query->getModel())}.media" => $modifyMediaQuery,
            ]);
        }

        return $query->with(['media' => $modifyMediaQuery]);
    }
}
