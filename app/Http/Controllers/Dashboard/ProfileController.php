<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();

        return Inertia::render('Dashboard/Profile', [
            // name/username/email/phone/avatar arrive via shared auth.user;
            // only the fields the whitelist omits are passed here.
            'bio'         => $user->bio,
            'memberSince' => $user->created_at->format('F Y'),
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'username'      => 'required|alpha_dash|max:50|unique:users,username,'.$user->id,
            'bio'           => 'nullable|string|max:500',
            'profile_photo' => 'nullable|image|max:5120',
        ]);

        // Handle photo upload
        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            // Delete old photo
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $path = $file->store('avatars', 'public');
            $data['avatar_path'] = $path;
        }

        unset($data['profile_photo']);
        $user->update($data);
        return back()->with('success', 'Profile updated.');
    }

    public function security()
    {
        $lastLogin = Auth::user()->last_login_at?->diffForHumans() ?? 'First session';

        return Inertia::render('Dashboard/Security', [
            'lastLogin' => $lastLogin,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', function($attr,$val,$fail) {
                if (!Hash::check($val, Auth::user()->password)) $fail('Current password is incorrect.');
            }],
            'password' => ['required','string','min:6','confirmed'],
        ]);
        Auth::user()->update(['password' => $request->password]);
        return back()->with('success', 'Password updated.');
    }
}
