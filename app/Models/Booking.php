<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'access_token',
        'resource_type_id', 'resource_id', 'service_id', 'timeslot_id',
        'user_id', 'customer_name', 'customer_email', 'customer_phone', 'locale',
        'starts_at', 'ends_at', 'blocks_until', 'quantity',
        'status', 'total_price', 'notes', 'internal_notes',
        'created_by_user_id', 'confirmed_at', 'cancelled_at', 'cancellation_reason',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'blocks_until' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'quantity' => 'integer',
            'total_price' => 'decimal:2',
            'status' => BookingStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->reference ??= self::generateReference();
            $booking->access_token ??= Str::random(48);
            $booking->blocks_until ??= $booking->ends_at;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** Kratka berljiva oznaka brez zamenljivih znakov (0/O, 1/I). */
    public static function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $ref = 'R';
            for ($i = 0; $i < 7; $i++) {
                $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('reference', $ref)->exists());

        return $ref;
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class, 'resource_type_id')->withTrashed();
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function timeslot(): BelongsTo
    {
        return $this->belongsTo(Timeslot::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Rezervacije, ki zasedajo vir (čakajoče in potrjene). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', BookingStatus::ACTIVE);
    }

    /** Rezervacije, ki se prekrivajo z intervalom [from, to) — upošteva čas za pripravo. */
    public function scopeOverlapping(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('blocks_until', '>', $from);
    }

    /**
     * Rezervacije, ki jih član osebja sme videti: potrebuje pravico za ogled, upošteva
     * omejitev vloge na tipe virov in doseg "samo svoji viri" (vir ali vodja termina).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->hasPermission(Permission::BookingsView)) {
            return $query->whereRaw('1 = 0');
        }

        if ($types = $user->allowedResourceTypeIds()) {
            $query->whereIn('resource_type_id', $types);
        }

        if ($user->seesOnlyOwnBookings()) {
            $query->where(fn (Builder $q) => $q
                ->whereHas('resource', fn (Builder $r) => $r->where('user_id', $user->id))
                ->orWhereHas('timeslot.staff', fn (Builder $s) => $s->where('users.id', $user->id)));
        }

        return $query;
    }

    public function isVisibleTo(User $user): bool
    {
        return static::visibleTo($user)->whereKey($this->id)->exists();
    }

    /** Osebje, ki sme rezervacijo potrjevati, odpovedati in urejati. */
    public function isManageableBy(?User $user): bool
    {
        return $user !== null && $user->hasPermission(Permission::BookingsManage) && $this->isVisibleTo($user);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /** Povezava za ogled in odpoved rezervacije brez prijave. */
    public function manageUrl(): string
    {
        return route('bookings.show', ['booking' => $this->reference, 'token' => $this->access_token]);
    }

    /** Opis za seznam in e-pošto, npr. "Frizerski salon · Striženje · Maja". */
    public function title(): string
    {
        return collect([
            $this->type?->name,
            $this->service?->name ?? $this->timeslot?->name,
            $this->resource?->name,
        ])->filter()->implode(' · ');
    }
}
