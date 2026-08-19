<?php

namespace App\DTOs\Patient;

final readonly class UpdatePatientDTO
{
    public function __construct(public array $attributes) {}

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
