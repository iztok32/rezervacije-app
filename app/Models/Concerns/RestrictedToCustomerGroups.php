<?php

namespace App\Models\Concerns;

use App\Enums\Permission;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Dostop do ponudbe ali termina samo za izbrane skupine strank.
 * Brez izbranih skupin je odprto za vse; osebje, ki rezervira za stranke, vidi vse.
 */
trait RestrictedToCustomerGroups
{
    public function customerGroups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class);
    }

    public function isRestricted(): bool
    {
        return $this->groupIds() !== [];
    }

    public function isOpenTo(?User $user): bool
    {
        $groups = $this->groupIds();

        if ($groups === [] || $user?->hasPermission(Permission::BookingsCreate)) {
            return true;
        }

        return $user !== null && array_intersect($groups, $user->customerGroupIds()) !== [];
    }

    public function scopeOpenTo(Builder $query, ?User $user): Builder
    {
        if ($user?->hasPermission(Permission::BookingsCreate)) {
            return $query;
        }

        $ids = $user?->customerGroupIds() ?? [];

        return $query->where(fn (Builder $q) => $q
            ->whereDoesntHave('customerGroups')
            ->when($ids, fn (Builder $q) => $q->orWhereHas('customerGroups', fn (Builder $g) => $g->whereIn('customer_groups.id', $ids))));
    }

    /** @return int[] */
    private function groupIds(): array
    {
        $groups = $this->relationLoaded('customerGroups')
            ? $this->customerGroups
            : $this->customerGroups()->get(['customer_groups.id']);

        return $groups->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
