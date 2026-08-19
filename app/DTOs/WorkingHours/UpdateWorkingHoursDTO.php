<?php

namespace App\DTOs\WorkingHours;

final readonly class UpdateWorkingHoursDTO
{
    public function __construct(public array $ranges) {}

    public static function fromArray(array $data): self
    {
        return new self($data['ranges']);
    }
}
