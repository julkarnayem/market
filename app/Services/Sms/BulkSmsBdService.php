<?php
namespace App\Services\Sms;

use App\Contracts\SmsServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BulkSMSBD.net SMS provider implementation.
 *
 * Credentials are read from environment — never hardcoded.
 * API documentation: https://bulksmsbd.net/api-documentation
 *
 * IMPORTANT: This service is scaffolded but requires valid BulkSMSBD
 * credentials and testing before production use.
 * Set BULKSMSBD_API_KEY and BULKSMSBD_SENDER_ID in .env.
 *
 * NOTE: BulkSMSBD does not expose a sandboxed test mode.
 * Integration must be verified with live credentials + a test number.
 */
class BulkSmsBdService implements SmsServiceInterface
{
    public function send(string $phone, string $message): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'reference' => null, 'error' => 'SMS provider is disabled.'];
        }

        try {
            $response = Http::timeout(config('bulksmsbd.timeout', 15))
                ->post(config('bulksmsbd.api_url'), [
                    'api_key'  => config('bulksmsbd.api_key'),
                    'senderid' => config('bulksmsbd.sender_id'),
                    'number'   => $phone,
                    'message'  => $message,
                ]);

            $data = $response->json();

            // BulkSMSBD returns response_code 202 for accepted
            if (isset($data['response_code']) && $data['response_code'] == 202) {
                return [
                    'success'   => true,
                    'reference' => $data['response_code'] ?? null,
                    'error'     => null,
                ];
            }

            $error = $data['error_message'] ?? $data['success_message'] ?? 'Unknown response';
            Log::warning('BulkSMSBD API non-success', ['code' => $data['response_code'] ?? null]);
            return ['success' => false, 'reference' => null, 'error' => $error];

        } catch (\Throwable $e) {
            Log::error('BulkSMSBD send failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'reference' => null, 'error' => $e->getMessage()];
        }
    }

    public function isEnabled(): bool
    {
        return config('bulksmsbd.enabled', false)
            && !empty(config('bulksmsbd.api_key'))
            && !empty(config('bulksmsbd.sender_id'));
    }
}
