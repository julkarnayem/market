<?php
namespace App\Enums;

/**
 * Why the buyer opened the dispute. A fixed list so the admin queue can be
 * filtered and read at a glance; the buyer's own account of it goes in
 * disputes.description, which is required for every reason.
 */
enum DisputeReason: string
{
    case NotDelivered     = 'not_delivered';
    case WrongAsset       = 'wrong_asset';
    case NotWorking       = 'not_working';
    case AccessProblem    = 'access_problem';
    case FalseInformation = 'false_information';
    case MissingFeatures  = 'missing_features';
    case Other            = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NotDelivered     => 'Item not delivered',
            self::WrongAsset       => 'Wrong asset delivered',
            self::AccessProblem    => 'Account / access problem',
            self::NotWorking       => "Asset doesn't work as described",
            self::FalseInformation => 'Seller gave false information',
            self::MissingFeatures  => 'Missing promised features',
            self::Other            => 'Other',
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $r) => ['value' => $r->value, 'label' => $r->label()],
            self::cases(),
        );
    }

    /** Validation rule body for a reason_code field. */
    public static function rule(): string
    {
        return 'in:' . implode(',', array_column(self::cases(), 'value'));
    }
}
