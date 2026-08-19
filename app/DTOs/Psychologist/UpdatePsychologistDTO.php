<?php

namespace App\DTOs\Psychologist;

final readonly class UpdatePsychologistDTO
{
    public function __construct(public array $profile) {}

    public static function fromArray(array $data): self
    {
        unset($data['email']);

        return new self($data);
    }
}
