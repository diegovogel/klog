<?php

namespace App\Services;

use RuntimeException;

class InviteAlreadyConsumedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This invite has already been used.');
    }
}
