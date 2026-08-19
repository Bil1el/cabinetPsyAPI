<?php

namespace App\DTOs\Appointment;

final readonly class UpdateAppointmentDTO
{
    public function __construct(public array $attributes) {}

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
