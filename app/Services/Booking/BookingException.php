<?php

namespace App\Services\Booking;

use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Kršitev pravila rezervacije. Kontrolerji jo pretvorijo v napako validacije za polje $field.
 */
class BookingException extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }

    public function toValidationException(): ValidationException
    {
        return ValidationException::withMessages([$this->field => $this->getMessage()]);
    }
}
