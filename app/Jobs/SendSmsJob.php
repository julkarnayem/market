<?php
namespace App\Jobs;

use App\Contracts\SmsServiceInterface;
use App\Models\SmsLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60; // seconds between retries

    public function __construct(
        public readonly int    $smsLogId,
        public readonly string $phone,
        public readonly string $message,
    ) {
        $this->onQueue('sms');
    }

    public function handle(SmsServiceInterface $sms): void
    {
        $log = SmsLog::find($this->smsLogId);
        if (!$log) return;

        // Idempotency: skip if already sent
        if ($log->status === 'sent') return;

        $log->increment('attempts');
        $result = $sms->send($this->phone, $this->message);

        if ($result['success']) {
            $log->update([
                'status'             => 'sent',
                'provider_reference' => $result['reference'],
                'sent_at'            => now(),
                'error_message'      => null,
            ]);
        } else {
            $log->update([
                'status'        => $this->attempts >= $this->tries ? 'failed' : 'pending',
                'error_message' => $result['error'],
                'failed_at'     => $this->attempts >= $this->tries ? now() : null,
            ]);

            if ($this->attempts >= $this->tries) {
                Log::warning('SMS permanently failed', ['log_id' => $this->smsLogId]);
                $this->fail(new \RuntimeException($result['error'] ?? 'SMS failed after max retries'));
            } else {
                $this->release($this->backoff * $this->attempts);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        SmsLog::where('id', $this->smsLogId)->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
            'failed_at'     => now(),
        ]);
    }
}
