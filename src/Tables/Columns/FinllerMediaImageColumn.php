<?php

namespace Filament\Tables\Columns;

use Closure;
use Elegantly\Media\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

class ElegantlyMediaImageColumn extends ImageColumn
{
    protected string|Closure|null $collection = null;

    protected string|Closure|null $group = null;

    protected string|Closure|null $conversion = null;

    public function collection(string|Closure|null $collection): static
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

    public function getCollection(): ?string
    {
        return $this->evaluate($this->collection);
    }

    public function getConversion(): ?string
    {
        return $this->evaluate($this->conversion);
    }

    public function getImageUrl(?string $state = null): ?string
    {
        /** @var InteractWithMedia $record */
        $record = $this->getRecord();

        if ($this->queriesRelationships($record)) {
            $record = $record->getRelationValue($this->getRelationshipName());
        }

        /** @var ?Media $media */
        $media = $record->media->firstWhere('uuid', $state);

        if (! $media) {
            return null;
        }

        $conversion = $this->getConversion();

        if ($this->getVisibility() === 'private') {
            try {
                return $media->getTemporaryUrl(
                    $conversion,
                    now()->addMinutes(5),
                );
            } catch (Throwable $exception) {
                // This driver does not support creating temporary URLs.
            }
        }

        return $media->getUrl($conversion);
    }

    /**
     * @return array<string>
     */
    public function getState(): array
    {
        $collection = $this->getCollection();
        $group = $this->getGroup();

        return $this->getRecord()->getRelationValue('media')
            ->filter(function (Media $media) use ($collection, $group): bool {
                if (filled($collection) && $media->collection_name !== $collection) {
                    return false;
                }

                if (filled($group) && $media->collection_group !== $group) {
                    return false;
                }

                return true;
            })
            ->sortBy('order')
            ->map(fn (Media $media): string => $media->uuid)
            ->all();
    }

    public function applyEagerLoading(Builder|Relation $query): Builder|Relation
    {
        if ($this->isHidden()) {
            return $query;
        }

        if ($this->queriesRelationships($query->getModel())) {
            return $query->with([
                "{$this->getRelationshipName()}.media" => fn (Builder|Relation $query) => $query->when(
                    $this->getCollection(),
                    fn (Builder|Relation $query, string $collection) => $query->where(
                        'collection_name',
                        $collection,
                    ),
                ),
            ]);
        }

        return $query->with(['media']);
    }
}
