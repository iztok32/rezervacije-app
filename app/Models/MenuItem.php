<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Postavka javnega menija (glava ali noga): vsebinska stran, ponudba (tip vira) ali povezava.
 */
class MenuItem extends Model
{
    public const LOCATIONS = ['header', 'footer'];

    public const KINDS = ['page', 'offer', 'link'];

    public const VISIBILITY = ['all', 'guests', 'users'];

    protected $fillable = ['location', 'kind', 'label', 'page_id', 'resource_type_id', 'url', 'visibility', 'new_tab', 'display_order'];

    protected function casts(): array
    {
        return [
            'new_tab' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function resourceType(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class);
    }

    /**
     * Meni za obiskovalca: samo objavljene strani, aktivna ponudba, do katere ima dostop,
     * in postavke, vidne gostom oz. prijavljenim.
     *
     * @return array{header: array, footer: array}
     */
    public static function forVisitor(?User $user): array
    {
        $items = static::with(['page', 'resourceType.customerGroups'])
            ->whereIn('visibility', ['all', $user ? 'users' : 'guests'])
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (self $item) => $item->link($user))
            ->filter();

        return collect(self::LOCATIONS)
            ->mapWithKeys(fn ($location) => [$location => $items->where('location', $location)->values()->all()])
            ->all();
    }

    /** @return array{id: int, location: string, label: string, href: string, external: bool, new_tab: bool}|null */
    public function link(?User $user): ?array
    {
        [$label, $href, $external] = match ($this->kind) {
            'page' => $this->page?->is_published ? [$this->page->title, $this->page->url(), false] : [null, null, false],
            'offer' => $this->resourceType?->is_active && $this->resourceType->isOpenTo($user)
                ? [$this->resourceType->name, route('book.show', $this->resourceType), false]
                : [null, null, false],
            'link' => [$this->url, $this->url, ! str_starts_with((string) $this->url, '/')],
            default => [null, null, false],
        };

        if (! $href) {
            return null;
        }

        return [
            'id' => $this->id,
            'location' => $this->location,
            'label' => $this->label ?: $label,
            'href' => $href,
            'external' => $external,
            'new_tab' => $this->new_tab,
        ];
    }

    /** @return Collection<int, self> */
    public static function ordered(string $location): Collection
    {
        return static::where('location', $location)->orderBy('display_order')->orderBy('id')->get();
    }
}
