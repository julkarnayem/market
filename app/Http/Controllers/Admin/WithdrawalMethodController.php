<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin management of the payout-method set (Admin → Settings). All actions
 * require settings.manage — the same permission that gates saving the rest of
 * the settings page.
 *
 * Two safety rules protect the user-facing withdrawal form and history:
 *   - a method that withdrawals have used cannot be deleted (switch it off);
 *   - the last active method cannot be switched off or deleted.
 */
class WithdrawalMethodController extends Controller
{
    /** Add a payout method (a new mobile-money provider, or another bank option). */
    public function store(Request $request)
    {
        $this->authorize('settings.manage');

        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'key'   => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('withdrawal_methods', 'key')],
            'type'  => ['required', Rule::in([WithdrawalMethod::TYPE_MFS, WithdrawalMethod::TYPE_BANK])],
        ], [
            'key.regex'  => 'The key may use only lowercase letters, digits and underscores, and must start with a letter.',
            'key.unique' => 'A method with this key already exists.',
        ]);

        WithdrawalMethod::create([
            'key'        => $data['key'],
            'label'      => $data['label'],
            'type'       => $data['type'],
            'is_active'  => true,
            'sort_order' => (int) WithdrawalMethod::max('sort_order') + 1,
        ]);

        return back()->with('success', "Withdrawal method “{$data['label']}” added.");
    }

    /** Rename / reorder, or switch a method on or off. Key is immutable. */
    public function update(Request $request, WithdrawalMethod $method)
    {
        $this->authorize('settings.manage');

        $data = $request->validate([
            'label'      => ['sometimes', 'required', 'string', 'max:60'],
            'is_active'  => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);

        // Switching the last active method off would leave the withdrawal form
        // with nothing to offer.
        if (($data['is_active'] ?? true) === false && $method->is_active && ! $this->hasOtherActive($method)) {
            return back()->with('error', 'At least one withdrawal method must stay active.');
        }

        $method->update($data);

        return back()->with('success', 'Withdrawal method updated.');
    }

    /** Delete — only when unused, so a stored withdrawal never loses its label. */
    public function destroy(WithdrawalMethod $method)
    {
        $this->authorize('settings.manage');

        if (Withdrawal::where('method_key', $method->key)->exists()) {
            return back()->with('error', 'This method has been used by withdrawals — switch it off instead of deleting it.');
        }

        if ($method->is_active && ! $this->hasOtherActive($method)) {
            return back()->with('error', 'At least one withdrawal method must stay active.');
        }

        $method->delete();

        return back()->with('success', 'Withdrawal method deleted.');
    }

    /** Is there another active method besides this one? */
    private function hasOtherActive(WithdrawalMethod $method): bool
    {
        return WithdrawalMethod::query()->active()->whereKeyNot($method->getKey())->exists();
    }
}
