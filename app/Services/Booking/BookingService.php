<?php

namespace App\Services\Booking;

use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Setting;
use App\Models\User;
use App\Services\Booking\Modes\BookingModeHandler;
use App\Services\Booking\Modes\HourlyHandler;
use App\Services\Booking\Modes\NightlyHandler;
use App\Services\Booking\Modes\ServiceHandler;
use App\Services\Booking\Modes\TimeslotHandler;
use Illuminate\Support\Facades\DB;

/**
 * Pravila za oddajo, odpoved in upravljanje rezervacij — skupna vsem načinom rezervacije.
 * Način (termin, storitev, nočitve, urni najem) izračuna čas in vir; tu so skupna pravila
 * (gostje, potrjevanje, podvojene prijave, rok za odpoved) in zaklepanje proti dvojnim rezervacijam.
 */
class BookingService
{
    /** Dovoljeni prehodi statusov, ki jih izvede osebje. */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'rejected', 'cancelled'],
        'confirmed' => ['completed', 'no_show', 'cancelled'],
        'completed' => ['confirmed', 'no_show'],
        'no_show' => ['confirmed', 'completed'],
        'cancelled' => [],
        'rejected' => [],
    ];

    public function __construct(
        private Availability $availability,
        private BookingNotifier $notifier,
    ) {}

    public function handler(ResourceType $type): BookingModeHandler
    {
        return app(match ($type->booking_mode) {
            BookingMode::Timeslot => TimeslotHandler::class,
            BookingMode::Service => ServiceHandler::class,
            BookingMode::Nightly => NightlyHandler::class,
            BookingMode::Hourly => HourlyHandler::class,
        });
    }

    /** Pravila validacije za oddajo rezervacije (skupna + specifična za način). */
    public function rules(ResourceType $type, ?User $actor): array
    {
        $rules = $this->handler($type)->rules($type) + [
            'notes' => 'nullable|string|max:1000',
        ];

        if ($actor?->isStaff()) {
            // Osebje rezervira za obstoječega uporabnika ali vpiše podatke stranke
            $rules['user_id'] = 'nullable|integer|exists:users,id';
            $rules['customer_name'] = 'required_without:user_id|nullable|string|max:255';
            $rules['customer_email'] = 'required_without:user_id|nullable|email|max:255';
            $rules['customer_phone'] = 'nullable|string|max:40';
            $rules['internal_notes'] = 'nullable|string|max:1000';
        } elseif ($actor) {
            $rules['customer_phone'] = 'nullable|string|max:40';
        } else {
            $rules['customer_name'] = 'required|string|max:255';
            $rules['customer_email'] = 'required|email|max:255';
            $rules['customer_phone'] = 'required|string|max:40';
        }

        if (! $actor?->isStaff() && Setting::get('booking.terms')) {
            $rules['accept_terms'] = 'accepted';
        }

        return $rules;
    }

    /**
     * @throws BookingException
     */
    public function create(ResourceType $type, array $input, ?User $actor): Booking
    {
        $privileged = (bool) $actor?->isStaff();

        if (! $type->is_active && ! $privileged) {
            throw new BookingException('resource_type', __('Bookings are currently not available.'));
        }
        if (! $actor && ! $type->allow_guests) {
            throw new BookingException('auth', __('Please log in to make a booking.'));
        }

        $customer = $this->customer($input, $actor, $privileged);

        $booking = DB::transaction(function () use ($type, $input, $privileged, $customer, $actor) {
            // Zaklepanje po tipu: hkratne rezervacije istega tipa se preverijo zaporedno,
            // tako da dva uporabnika ne moreta dobiti istega zadnjega mesta
            ResourceType::whereKey($type->id)->lockForUpdate()->first();

            $draft = $this->handler($type)->draft($type, $input, $privileged);

            $this->ensureNotDuplicate($draft, $customer);

            $status = $type->requires_approval && ! $privileged ? BookingStatus::Pending : BookingStatus::Confirmed;

            return Booking::create($draft->toAttributes() + $customer + [
                'resource_type_id' => $type->id,
                'status' => $status,
                'notes' => $input['notes'] ?? null,
                'internal_notes' => $privileged ? ($input['internal_notes'] ?? null) : null,
                'created_by_user_id' => $actor?->id,
                'confirmed_at' => $status === BookingStatus::Confirmed ? now() : null,
            ]);
        });

        $this->notifier->created($booking);

        return $booking;
    }

    /** Ali sme $actor (ali gost z žetonom) videti rezervacijo. */
    public function canView(Booking $booking, ?User $actor, ?string $token): bool
    {
        return (bool) $actor?->isStaff()
            || ($actor && $booking->user_id === $actor->id)
            || ($token && hash_equals($booking->access_token, $token));
    }

    /** Razlog, zakaj stranka ne more odpovedati (ali null, če lahko). */
    public function cancellationBlocker(Booking $booking, ?User $actor): ?string
    {
        if (! $booking->isActive()) {
            return __('This booking can no longer be cancelled.');
        }
        if ($actor?->isStaff()) {
            return null;
        }
        if ($booking->starts_at->lte(now())) {
            return __('This booking has already started.');
        }

        $hours = $booking->type->cancellation_hours;
        if ($hours !== null && now()->addHours($hours)->gt($booking->starts_at)) {
            return trans_choice(
                'Bookings can only be cancelled at least :count hour in advance.|Bookings can only be cancelled at least :count hours in advance.',
                $hours,
                ['count' => $hours],
            );
        }

        return null;
    }

    public function cancel(Booking $booking, ?User $actor, ?string $token, ?string $reason = null): Booking
    {
        abort_unless($this->canView($booking, $actor, $token), 403);

        if ($error = $this->cancellationBlocker($booking, $actor)) {
            throw new BookingException('booking', $error);
        }

        $booking->update([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        $this->notifier->statusChanged($booking, $actor);

        return $booking;
    }

    /** Sprememba statusa s strani osebja (potrditev, zavrnitev, zaključek, neprihod). */
    public function changeStatus(Booking $booking, BookingStatus $status, User $actor, ?string $reason = null): Booking
    {
        abort_unless($actor->isStaff(), 403);

        if (! in_array($status->value, self::TRANSITIONS[$booking->status->value], true)) {
            throw new BookingException('status', __('This status change is not allowed.'));
        }

        $booking->update(array_filter([
            'status' => $status,
            'confirmed_at' => $status === BookingStatus::Confirmed && ! $booking->confirmed_at ? now() : null,
            'cancelled_at' => in_array($status, [BookingStatus::Cancelled, BookingStatus::Rejected], true) ? now() : null,
            'cancellation_reason' => $reason,
        ], fn ($v) => $v !== null));

        if (in_array($status, [BookingStatus::Confirmed, BookingStatus::Rejected, BookingStatus::Cancelled], true)) {
            $this->notifier->statusChanged($booking, $actor);
        }

        return $booking;
    }

    /** @return string[] statusi, v katere lahko osebje premakne rezervacijo */
    public function allowedTransitions(Booking $booking): array
    {
        return self::TRANSITIONS[$booking->status->value];
    }

    /** Dodelitev ali zamenjava vira (npr. konja) s strani osebja; preveri, da je vir prost. */
    public function assignResource(Booking $booking, ?int $resourceId, User $actor): Booking
    {
        abort_unless($actor->isStaff(), 403);

        if ($resourceId) {
            $resource = Resource::where('resource_type_id', $booking->resource_type_id)->find($resourceId);
            if (! $resource) {
                throw new BookingException('resource_id', __('Invalid resource.'));
            }

            DB::transaction(function () use ($booking, $resource) {
                ResourceType::whereKey($booking->resource_type_id)->lockForUpdate()->first();

                $type = $booking->type;
                $bookings = $this->availability->bookings([$resource->id], $booking->starts_at, $booking->blocks_until, $booking);
                $closures = $this->availability->closures($type, [$resource->id], $booking->starts_at, $booking->ends_at);

                if ($this->availability->isClosed($closures, $resource->id, $booking->starts_at, $booking->ends_at)
                    || ! $this->availability->hasRoom($bookings, $resource, $booking->starts_at, $booking->blocks_until)) {
                    throw new BookingException('resource_id', __(':name is not available at this time.', ['name' => $resource->name]));
                }

                $booking->update(['resource_id' => $resource->id]);
            });
        } else {
            $booking->update(['resource_id' => null]);
        }

        return $booking;
    }

    /**
     * Podatki o stranki, shranjeni na rezervaciji.
     *
     * @return array{user_id: ?int, customer_name: string, customer_email: string, customer_phone: ?string, locale: ?string}
     */
    private function customer(array $input, ?User $actor, bool $privileged): array
    {
        $user = match (true) {
            $privileged && ! empty($input['user_id']) => User::findOrFail($input['user_id']),
            $privileged => null,
            default => $actor,
        };

        if ($user) {
            return [
                'user_id' => $user->id,
                'customer_name' => ($privileged ? ($input['customer_name'] ?? null) : null) ?: $user->name,
                'customer_email' => ($privileged ? ($input['customer_email'] ?? null) : null) ?: $user->email,
                'customer_phone' => ($input['customer_phone'] ?? null) ?: $user->phone,
                'locale' => $user->locale ?: app()->getLocale(),
            ];
        }

        return [
            'user_id' => null,
            'customer_name' => trim($input['customer_name']),
            'customer_email' => mb_strtolower(trim($input['customer_email'])),
            'customer_phone' => $input['customer_phone'] ?? null,
            'locale' => app()->getLocale(),
        ];
    }

    /** Ista stranka se na isti termin (ali isti vir ob istem času) ne more prijaviti dvakrat. */
    private function ensureNotDuplicate(BookingDraft $draft, array $customer): void
    {
        $exists = Booking::active()
            ->where('starts_at', $draft->startsAt)
            ->where(fn ($q) => $customer['user_id']
                ? $q->where('user_id', $customer['user_id'])
                : $q->where('customer_email', $customer['customer_email']))
            ->where(fn ($q) => $draft->timeslotId
                ? $q->where('timeslot_id', $draft->timeslotId)
                : $q->where('resource_id', $draft->resourceId))
            ->exists();

        if ($exists) {
            throw new BookingException('booking', __('You already have a booking for this time.'));
        }
    }
}
