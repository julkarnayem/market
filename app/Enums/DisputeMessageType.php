<?php
namespace App\Enums;

/**
 * What a row in the dispute thread is.
 *
 * `InternalNote` rows are staff-only and are filtered out of every payload a
 * buyer or seller can reach — see DisputeService::threadFor().
 */
enum DisputeMessageType: string
{
    /** Something a participant typed. */
    case Text = 'text';
    /** A message carrying an uploaded evidence file. */
    case Evidence = 'evidence';
    /** An event the system recorded (opened, escalated, evidence requested…). */
    case System = 'system';
    /** An admin's decision, shown to both sides. */
    case AdminDecision = 'admin_decision';
    /** Staff-only. Never leaves the admin screen. */
    case InternalNote = 'internal_note';

    /** Only these are authored by a person and count as conversation. */
    public function isFromParticipant(): bool
    {
        return in_array($this, [self::Text, self::Evidence], true);
    }
}
