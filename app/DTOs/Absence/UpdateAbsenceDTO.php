<?php

namespace App\DTOs\Absence;

final readonly class UpdateAbsenceDTO
{
    public function __construct(public array $attributes) {}

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
