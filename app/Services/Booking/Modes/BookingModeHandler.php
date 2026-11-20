<?php

namespace App\Services\Booking\Modes;

use App\Models\Booking;
use App\Models\ResourceType;
use App\Models\User;
use App\Services\Booking\BookingDraft;
use App\Services\Booking\BookingException;

/**
 * Način rezervacije: izračun razpoložljivosti za izbirnik in preverjanje konkretne rezervacije.
 */
interface BookingModeHandler
{
    /** Pravila validacije vhodnih podatkov, specifičnih za način. */
    public function rules(ResourceType $type): array;

    /** Pravila validacije poizvedbe po razpoložljivosti. */
    public function availabilityRules(): array;

    /** Podatki za izbirnik na strani rezervacije. */
    public function availability(ResourceType $type, array $query, ?User $viewer): array;

    /**
     * Preveri vhod in vrne predlog rezervacije. $privileged (osebje) obide okno za
     * rezervacije vnaprej. $ignore se ne šteje med zasedenost (pri spremembi rezervacije).
     *
     * @throws BookingException
     */
    public function draft(ResourceType $type, array $input, bool $privileged, ?Booking $ignore = null): BookingDraft;
}
