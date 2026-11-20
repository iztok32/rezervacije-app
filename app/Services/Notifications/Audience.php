<?php

namespace App\Services\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CustomerGroup;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Prejemniki ročnega sporočila:
 *  - customers: stranke z računom (po želji samo izbrane skupine strank)
 *  - bookings:  stranke z aktivno rezervacijo v obdobju (tudi gostje), po želji za tip ali vir;
 *               upošteva, katere rezervacije pošiljatelj sme videti
 *  - users:     izbrani uporabniki
 */
class Audience
{
    public const MODES = ['customers', 'bookings', 'users'];

    public static function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(self::MODES)],
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'integer|exists:customer_groups,id',
            'from' => 'required_if:mode,bookings|nullable|date_format:Y-m-d',
            'to' => 'required_if:mode,bookings|nullable|date_format:Y-m-d|after_or_equal:from',
            'resource_type_id' => 'nullable|integer|exists:resource_types,id',
            'resource_id' => 'nullable|integer|exists:resources,id',
            'user_ids' => 'required_if:mode,users|nullable|array|max:500',
            'user_ids.*' => 'integer|exists:users,id',
        ];
    }

    /**
     * @return Collection<int, array{user_id: ?int, name: string, email: string, locale: ?string}>
     */
    public function resolve(array $input, User $sender): Collection
    {
        $recipients = match ($input['mode']) {
            'customers' => $this->customers($input['group_ids'] ?? []),
            'bookings' => $this->withBookings($input, $sender),
            'users' => User::whereIn('id', $input['user_ids'] ?? [])->where('is_active', true)->get()->map(fn (User $u) => $this->fromUser($u)),
        };

        // Ena oseba enkrat: po računu, sicer po e-pošti
        return $recipients
            ->unique(fn ($r) => $r['user_id'] ? 'u'.$r['user_id'] : 'e'.mb_strtolower($r['email']))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** Opis prejemnikov za dnevnik, npr. "Stranke z rezervacijo 24. 11. 2026 – 30. 11. 2026 · Jahanje". */
    public function describe(array $input): string
    {
        return match ($input['mode']) {
            'customers' => empty($input['group_ids'])
                ? __('All customers')
                : __('Customer groups').': '.CustomerGroup::whereIn('id', $input['group_ids'])->orderBy('name')->pluck('name')->implode(', '),
            'bookings' => collect([
                __('Customers with bookings').' '.Carbon::parse($input['from'])->format('j. n. Y').' – '.Carbon::parse($input['to'])->format('j. n. Y'),
                ! empty($input['resource_type_id']) ? ResourceType::withTrashed()->find($input['resource_type_id'])?->name : null,
                ! empty($input['resource_id']) ? Resource::withTrashed()->find($input['resource_id'])?->name : null,
            ])->filter()->implode(' · '),
            'users' => __('Selected users'),
        };
    }

    private function customers(array $groupIds): Collection
    {
        return User::whereNull('role_id')
            ->where('is_active', true)
            ->when($groupIds, fn ($q) => $q->whereHas('customerGroups', fn ($g) => $g->whereIn('customer_groups.id', $groupIds)))
            ->get()
            ->map(fn (User $u) => $this->fromUser($u));
    }

    private function withBookings(array $input, User $sender): Collection
    {
        return Booking::visibleTo($sender)
            ->with('user')
            ->whereIn('status', BookingStatus::ACTIVE)
            ->where('starts_at', '<', Carbon::parse($input['to'])->addDay())
            ->where('ends_at', '>', Carbon::parse($input['from']))
            ->when($input['resource_type_id'] ?? null, fn ($q, $id) => $q->where('resource_type_id', $id))
            ->when($input['resource_id'] ?? null, fn ($q, $id) => $q->where('resource_id', $id))
            ->get()
            ->map(fn (Booking $b) => $b->user && $b->user->is_active
                ? $this->fromUser($b->user)
                : ['user_id' => null, 'name' => $b->customer_name, 'email' => $b->customer_email, 'locale' => $b->locale]);
    }

    private function fromUser(User $u): array
    {
        return ['user_id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'locale' => $u->locale];
    }
}
