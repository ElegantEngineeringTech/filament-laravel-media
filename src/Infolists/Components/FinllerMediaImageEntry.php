<?php

namespace Filament\Infolists\Components;

use Closure;
use Finller\Media\Models\Media;
use Throwable;

class FinllerMediaImageEntry extends ImageEntry
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

        $relationshipName = $this->getRelationshipName();

        if (filled($relationshipName)) {
            $record = $record->getRelationValue($relationshipName);
        }

        /** @var ?Media $media */
        $media = $record->media->first(fn (Media $media): bool => $media->uuid === $state);

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
}
