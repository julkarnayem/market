<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show()
    {
        return view('dashboard.profile', ['user' => Auth::user()]);
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
        return view('dashboard.security');
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
