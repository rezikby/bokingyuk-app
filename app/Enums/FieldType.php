<?php

namespace App\Enums;

enum FieldType: string
{
    case Futsal   = 'futsal';
    case Badminton = 'badminton';
    case Basketball = 'basketball';
    case Tennis   = 'tennis';

    public function label(): string
    {
        return match($this) {
            self::Futsal     => 'Futsal',
            self::Badminton  => 'Badminton',
            self::Basketball => 'Basket',
            self::Tennis     => 'Tenis',
        };
    }
}
