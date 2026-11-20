<?php

namespace App\Services\Booking;

use Illuminate\Support\Carbon;

/**
 * Preverjen predlog rezervacije, ki ga pripravi način rezervacije (handler).
 */
final class BookingDraft
{
    public function __construct(
        public Carbon $startsAt,
        public Carbon $endsAt,
        public ?Carbon $blocksUntil = null,
        public ?int $resourceId = null,
        public ?int $serviceId = null,
        public ?int $timeslotId = null,
        public int $quantity = 1,
        public ?float $totalPrice = null,
    ) {
        $this->blocksUntil ??= $this->endsAt->copy();
    }

    public function toAttributes(): array
    {
        return [
            'resource_id' => $this->resourceId,
            'service_id' => $this->serviceId,
            'timeslot_id' => $this->timeslotId,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'blocks_until' => $this->blocksUntil,
            'quantity' => $this->quantity,
            'total_price' => $this->totalPrice,
        ];
    }
}
