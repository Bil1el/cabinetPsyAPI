<?php

namespace App\DTOs\Patient;

final readonly class StorePatientDTO
{
    public function __construct(public array $attributes) {}

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
