<?php
namespace App\Http\Requests\Dashboard;

use App\Models\WithdrawalMethod;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A payout request.
 *
 * Only the amount, the method and that method's own account fields are accepted.
 * The user, the wallet and the balance are never read from here — WithdrawalService
 * derives them from the authenticated user and re-checks the balance under a lock,
 * so a forged user_id, wallet_id or current_balance in the payload is inert.
 *
 * The set of methods is admin-managed (withdrawal_methods table), so the rules
 * are built from the active rows: `method` must be an active key, and which
 * account fields are required follows the chosen method's type. A method the
 * admin has switched off is rejected here, not just hidden from the form.
 */
class StoreWithdrawalRequest extends FormRequest
{
    private ?WithdrawalMethod $resolvedMethod = null;

    public function authorize(): bool
    {
        // Any authenticated account may request against its own balance; whether
        // there is anything to withdraw is a balance question, not a role one.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $minBdt = Money::toBdt(app(SettingsService::class)->minWithdrawal());

        // The one source of truth for what may be picked and what each needs.
        $active   = WithdrawalMethod::query()->active()->get(['key', 'type']);
        $keys     = $active->pluck('key')->all();
        $mfsKeys  = $active->where('type', WithdrawalMethod::TYPE_MFS)->pluck('key')->all();
        $bankKeys = $active->where('type', WithdrawalMethod::TYPE_BANK)->pluck('key')->all();

        $isBank = fn () => in_array($this->input('method'), $bankKeys, true);
        $isMfs  = fn () => in_array($this->input('method'), $mfsKeys, true);

        return [
            // The floor comes from settings, so the message and the service agree.
            'amount_bdt' => ['required', 'numeric', "min:{$minBdt}", 'max:999999'],
            'method'     => ['required', Rule::in($keys)],

            // Mobile money: the wallet number to send to.
            'mfs_number' => [Rule::requiredIf($isMfs), 'nullable', 'string', 'regex:/^01[3-9]\d{8}$/'],

            // Bank transfer: who the account belongs to and where it is.
            'bank_account_name'   => [Rule::requiredIf($isBank), 'nullable', 'string', 'min:3', 'max:120'],
            'bank_account_number' => [Rule::requiredIf($isBank), 'nullable', 'string', 'min:6', 'max:64', 'regex:/^[0-9- ]+$/'],
            'bank_name'           => [Rule::requiredIf($isBank), 'nullable', 'string', 'min:2', 'max:120'],
            'bank_branch'         => ['nullable', 'string', 'max:120'],

            // Dedupe key for a double-submitted form; see the unique index on
            // (user_id, client_request_id).
            'client_request_id'   => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'mfs_number.regex'          => 'Enter a valid Bangladeshi mobile number (e.g. 01XXXXXXXXX).',
            'mfs_number.required'       => 'Enter the mobile number to send the payout to.',
            'bank_account_number.regex' => 'Account number may only contain digits, spaces and dashes.',
            'amount_bdt.min'            => 'Minimum withdrawal is ' . Money::format(app(SettingsService::class)->minWithdrawal()) . '.',
        ];
    }

    /** The chosen method row. Resolved once; validation has already confirmed it is active. */
    public function resolvedMethod(): WithdrawalMethod
    {
        return $this->resolvedMethod ??= WithdrawalMethod::query()
            ->where('key', $this->validated('method'))
            ->firstOrFail();
    }

    /** The account fields for the chosen method, ready for the service. */
    public function accountDetails(): array
    {
        return $this->resolvedMethod()->isMobileMoney()
            ? ['mfs_number' => $this->validated('mfs_number')]
            : [
                'bank_account_name'   => $this->validated('bank_account_name'),
                'bank_account_number' => $this->validated('bank_account_number'),
                'bank_name'           => $this->validated('bank_name'),
                'bank_branch'         => $this->validated('bank_branch'),
            ];
    }
}
