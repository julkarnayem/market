<?php
namespace App\Contracts;

interface SmsServiceInterface
{
    /**
     * Send an SMS message.
     *
     * @param string $phone  Recipient phone (e.g. 01XXXXXXXXX)
     * @param string $message Message text
     * @return array{success: bool, reference: ?string, error: ?string}
     */
    public function send(string $phone, string $message): array;

    /** Whether this provider is configured and enabled. */
    public function isEnabled(): bool;
}
