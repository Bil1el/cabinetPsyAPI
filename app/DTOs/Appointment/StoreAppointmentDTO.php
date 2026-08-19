<?php

namespace App\DTOs\Appointment;

final readonly class StoreAppointmentDTO
{
    public function __construct(public array $attributes) {}

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
