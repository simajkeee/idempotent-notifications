<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;

class ProviderResolver
{
    public function __construct(
        private readonly EmailProviderMock $emailProviderMock,
        private readonly SmsProviderMock $smsProviderMock
    ) {}

    public function resolve(Channel $channel): Provider
    {
        return match ($channel) {
            Channel::EMAIL => $this->emailProviderMock,
            Channel::SMS => $this->smsProviderMock,
        };
    }
}
