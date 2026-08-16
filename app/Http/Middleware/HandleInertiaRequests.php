<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root Blade template that boots Inertia (resources/views/app.blade.php).
     */
    protected $rootView = 'app';

    /**
     * Props shared with EVERY Inertia response.
     *
     * NOTE: everything here is the current user's OWN data or public config.
     * These values drive what the UI *shows*; they are NEVER the authority for
     * what an action is *allowed* to do — Laravel policies/middleware/validation
     * remain the sole authority (see migration plan guardrails).
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'appName' => config('app.name'),

            'auth' => [
                'user' => $user ? $this->shareUser($user) : null,
            ],

            // Lazy so the session is only touched when actually present.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'status'  => fn () => $request->session()->get('status'),
            ],

            // Ziggy named routes for the Vue client (and for SSR via ssr.ts).
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    /**
     * Whitelist of the authenticated user's own attributes.
     * Roles/permissions are only exposed for admin accounts (keeps regular-user
     * payloads lean and avoids leaking the permission taxonomy to the client).
     */
    protected function shareUser($user): array
    {
        $isAdmin = $user->isAdmin();

        return [
            'id'                  => $user->id,
            'name'                => $user->name,
            'username'            => $user->username,
            'email'               => $user->email,
            'phone'               => $user->phone,
            'avatar'              => $user->profile_photo_path
                ? Storage::disk('public')->url($user->profile_photo_path)
                : null,
            'is_admin'            => $isAdmin,
            'can_sell'            => $user->canSell(),
            'can_transact'        => $user->canTransact(),
            'is_verified_seller'  => $user->isVerifiedSeller(),
            'verification_status' => $user->verification_status?->value,
            'status'              => $user->status?->value,
            'roles'               => $isAdmin
                ? $user->roles->pluck('name')->values()->all()
                : [],
            'permissions'         => $isAdmin
                ? $user->roles->loadMissing('permissions')
                    ->pluck('permissions')->flatten()
                    ->pluck('name')->unique()->values()->all()
                : [],
        ];
    }
}
