<?php
namespace App\Enums;

/**
 * Where a payout goes.
 *
 * The four mobile-money providers were previously a bare `in:` list inside the
 * controller's validate() call, with the `method` column always holding 'mfs'.
 * Collecting them here is what makes the set extensible: a new provider is one
 * case plus its arm in requiredFields(), and every rule, label and option list
 * follows from that rather than from a string repeated across the app.
 */
enum WithdrawalMethod: string
{
    case Bkash  = 'bkash';
    case Nagad  = 'nagad';
    case Rocket = 'rocket';
    case Upay   = 'upay';
    case Bank   = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Bkash  => 'bKash',
            self::Nagad  => 'Nagad',
            self::Rocket => 'Rocket',
            self::Upay   => 'Upay',
            self::Bank   => 'Bank transfer',
        };
    }

    /** Mobile-money payouts need a wallet number; a bank transfer does not. */
    public function isMobileMoney(): bool
    {
        return $this !== self::Bank;
    }

    /**
     * The account fields this method requires, so validation and the form are
     * driven by one list instead of agreeing with each other by hand.
     *
     * @return list<string>
     */
    public function requiredFields(): array
    {
        return match ($this) {
            self::Bank => ['bank_account_name', 'bank_account_number', 'bank_name'],
            default    => ['mfs_number'],
        };
    }

    /**
     * Stored in `withdrawals.method`. Mobile money keeps the historic 'mfs'
     * value — the provider itself lives in `mfs_provider`, and rewriting rows
     * that already say 'mfs' would be a migration with nothing to gain.
     */
    public function storageKey(): string
    {
        return $this->isMobileMoney() ? 'mfs' : 'bank';
    }

    /** @return list<array{value:string,label:string,is_bank:bool}> */
    public static function options(): array
    {
        return array_map(
            fn (self $m) => ['value' => $m->value, 'label' => $m->label(), 'is_bank' => !$m->isMobileMoney()],
            self::cases(),
        );
    }

    /** Validation rule body for a method field. */
    public static function rule(): string
    {
        return 'in:' . implode(',', array_column(self::cases(), 'value'));
    }
}
