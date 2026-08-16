<?php
namespace App\Services\Sms;

/**
 * Safe SMS template registry.
 * Variables are a whitelist of scalar values — no arbitrary PHP/template execution.
 */
class SmsTemplates
{
    public const TEMPLATES = [
        'order_paid'             => 'Your order #{order_number} payment is confirmed. Seller will deliver soon.',
        'order_delivered'        => 'Asset delivered for order #{order_number}. Please review and complete.',
        'order_completed'        => 'Order #{order_number} completed. Your earning will be available in 8 hours.',
        'order_auto_completed'   => 'Order #{order_number} auto-completed. Earnings releasing shortly.',
        'listing_approved'       => "Your listing '{listing_title}' has been approved and is now live.",
        'listing_rejected'       => "Your listing '{listing_title}' was rejected. Check your dashboard for details.",
        'verification_approved'  => 'Your seller verification has been approved. You can now create listings.',
        'verification_rejected'  => 'Your seller verification was not approved. Please re-submit with correct documents.',
        'withdrawal_approved'    => 'Your withdrawal of {amount} BDT has been approved and is being processed.',
        'withdrawal_completed'   => 'Your withdrawal of {amount} BDT has been completed via {provider}.',
        'withdrawal_rejected'    => 'Your withdrawal request was rejected. Funds have been returned to your wallet.',
        'offer_accepted'         => "Your offer on '{listing_title}' was accepted. Please complete payment.",
        'promotion_purchased'    => "Your {days}-day promotion for '{listing_title}' is now active until {end_date}.",
        'promotion_expiring'     => "Your promotion for '{listing_title}' expires in 24 hours.",
        'promotion_expired'      => "Your promotion for '{listing_title}' has expired.",
        'dispute_resolved'       => 'Your dispute for order #{order_number} has been resolved.',
    ];

    /** Allowed variable names — never allow arbitrary code. */
    private const ALLOWED_VARS = [
        'order_number','listing_title','amount','provider','days',
        'end_date','user_name','status','provider_reference',
    ];

    /**
     * Render a template with safe variable substitution.
     */
    public static function render(string $template, array $vars = []): string
    {
        $text = self::TEMPLATES[$template] ?? $template;

        foreach ($vars as $key => $value) {
            if (in_array($key, self::ALLOWED_VARS, true)) {
                $text = str_replace('{' . $key . '}', (string) $value, $text);
            }
        }
        return $text;
    }

    public static function has(string $template): bool
    {
        return isset(self::TEMPLATES[$template]);
    }
}
