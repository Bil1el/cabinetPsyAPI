<?php

namespace App\Support;

final class PatientIdentityNormalizer
{
    public static function attributes(array $attributes): array
    {
        if (array_key_exists('email', $attributes) && $attributes['email'] !== null) {
            $attributes['email'] = self::email($attributes['email']);
        }

        if (array_key_exists('phone', $attributes) && $attributes['phone'] !== null) {
            $attributes['phone'] = self::phone($attributes['phone']);
        }

        return $attributes;
    }

    public static function email(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function phone(string $phone): string
    {
        return str_replace([' ', '-', '.', '(', ')'], '', trim($phone));
    }
}
