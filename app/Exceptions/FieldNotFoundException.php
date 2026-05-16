<?php

namespace App\Exceptions;

use RuntimeException;

class FieldNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Lapangan tidak ditemukan.')
    {
        parent::__construct($message);
    }
}