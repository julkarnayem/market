<?php
namespace App\Services;

use App\Contracts\SmsServiceInterface;
use App\Jobs\SendSmsJob;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Sms\SmsTemplates;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Support\Str;

/**
 * Central notification dispatch. Handles in-app (database), email, and SMS.
 *
 * Core transactions must NOT wait for notification delivery.
 * SMS and email are always queued.
 * In-app notifications are fast (single DB insert) and run inline.
 *
 * Failed notification channels never roll back business transactions.
 */
class NotificationService
{
    public function __construct(private readonly SmsServiceInterface $sms) {}

    /**
     * Send an in-app notification (stored in `notifications` table via Laravel).
     */
    public function inApp(User $user, string $type, string $title, string $message, array $data = []): void
    {
        try {
            $user->notify(new \App\Notifications\GenericNotification($type, $title, $message, $data));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('In-app notification failed', [
                'user' => $user->id, 'type' => $type, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Queue an SMS. Uses idempotency key to prevent duplicate sends.
     * Does NOT throw — SMS failure never blocks business logic.
     */
    public function sms(User $user, string $template, array $vars = [], ?string $idempotencyKey = null): void
    {
        if (!$this->sms->isEnabled()) return;
        if (empty($user->phone)) return;

        $ikey = $idempotencyKey ?? "sms:{$template}:{$user->id}:" . now()->format('YmdH');

        // Idempotency: skip if same key already sent/pending
        if (SmsLog::where('idempotency_key', $ikey)->whereIn('status',['sent','pending'])->exists()) {
            return;
        }

        $message = SmsTemplates::render($template, $vars);
        $log = SmsLog::create([
            'user_id'         => $user->id,
            'phone'           => $user->phone,
            'template'        => $template,
            'message'         => $message,
            'provider'        => 'bulksmsbd',
            'status'          => 'pending',
            'idempotency_key' => $ikey,
        ]);

        SendSmsJob::dispatch($log->id, $user->phone, $message);
    }

    /**
     * Notify both in-app and SMS together.
     */
    public function notify(User $user, string $type, string $title, string $message,
                           array $data = [], ?string $smsTemplate = null, array $smsVars = []): void
    {
        $this->inApp($user, $type, $title, $message, $data);
        if ($smsTemplate) {
            $this->sms($user, $smsTemplate, $smsVars, "notify:{$type}:{$user->id}:" . now()->format('YmdH'));
        }
    }
}
