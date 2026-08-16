<?php
namespace App\Services;

use App\Models\FraudEvent;
use App\Models\FraudReview;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Anti-fraud risk scoring.
 *
 * Scores are advisory — no automatic bans.
 * High-risk signals are sent to the Admin fraud review queue.
 *
 * Signals and their score impact:
 *   duplicate_nid_hash     = 50 (high)
 *   self_purchase_attempt  = 30 (medium-high)
 *   repeated_payment_fail  = 15 (medium) per event
 *   rapid_account_creation = 20 (medium) same IP in 24h
 *   repeated_disputes      = 20 per event
 *   repeated_withdrawals   = 10 per event
 *   same_payout_account    = 25
 */
class FraudService
{
    public const SIGNALS = [
        'duplicate_nid_hash'       => 50,
        'self_purchase_attempt'    => 30,
        'rapid_account_creation'   => 20,
        'same_payout_account'      => 25,
        'repeated_payment_failure' => 15,
        'repeated_disputes'        => 20,
        'suspicious_withdrawal'    => 15,
        'mass_message_attempt'     => 10,
        'rate_limit_triggered'     => 5,
    ];

    // Score thresholds
    public const THRESHOLD_REVIEW = 30;  // flag for review
    public const THRESHOLD_HIGH   = 70;  // high risk, prioritize review

    public function record(User $user, string $signal, array $context = []): void
    {
        if (!array_key_exists($signal, self::SIGNALS)) {
            Log::warning('Unknown fraud signal: '.$signal);
            return;
        }

        $impact = self::SIGNALS[$signal];

        // Log the event
        FraudEvent::create([
            'user_id'      => $user->id,
            'signal'       => $signal,
            'score_impact' => $impact,
            'context'      => json_encode($context, JSON_UNESCAPED_SLASHES), // no secrets
            'ip_address'   => request()->ip(),
        ]);

        // Recompute risk score
        $totalScore = FraudEvent::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30)) // rolling 30 days
            ->sum('score_impact');

        // Update user risk_score
        $flags = $user->risk_flags ?? [];
        if (!in_array($signal, $flags)) $flags[] = $signal;
        $user->update(['risk_score' => min($totalScore, 999), 'risk_flags' => $flags]);

        // Enqueue for review if crosses threshold
        if ($totalScore >= self::THRESHOLD_REVIEW) {
            FraudReview::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'risk_score' => $totalScore,
                    'risk_flags' => $flags,
                    'status'     => $totalScore >= self::THRESHOLD_HIGH ? 'escalated' : 'pending',
                ]
            );
        }
    }

    /** Clear risk score after admin review. */
    public function clear(User $user, string $adminNote, int $adminId): void
    {
        $user->update(['risk_score' => 0, 'risk_flags' => []]);
        FraudReview::where('user_id', $user->id)->update([
            'status'      => 'cleared',
            'admin_notes' => $adminNote,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
        ]);
    }

    /** Restrict account — sets review status. */
    public function restrict(User $user, string $reason, int $adminId): void
    {
        FraudReview::where('user_id', $user->id)->update([
            'status'      => 'restricted',
            'admin_notes' => $reason,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
        ]);
    }
}
