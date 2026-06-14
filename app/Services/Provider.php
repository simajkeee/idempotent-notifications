<?php

declare(strict_types=1);

namespace App\Services;

interface Provider
{
    public function send(): void;
}
