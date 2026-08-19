<?php

namespace App\Enums;

enum WorkingHoursMode: string
{
    case IN_PERSON = 'in_person';
    case ONLINE = 'online';
    case BOTH = 'both';

    public function supports(AppointmentType $type): bool
    {
        return $this === self::BOTH || $this->value === $type->value;
    }
}
