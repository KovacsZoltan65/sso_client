<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ProfileService
{
    /**
     * @return array<string, mixed>
     */
    public function profilePageData(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }

    /**
     * @param  array{name: string, email: string}  $attributes
     */
    public function updateProfile(User $user, array $attributes): void
    {
        $originalEmail = $user->email;

        $user->fill($attributes);

        if ($originalEmail !== $attributes['email']) {
            $user->email_verified_at = null;
        }

        $user->save();

        activity('account')
            ->causedBy($user)
            ->performedOn($user)
            ->event('profile.updated')
            ->withProperties([
                'roles' => $user->getRoleNames()->values()->all(),
            ])
            ->log('User profile updated');
    }

    public function deleteAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        /** @var User $user */
        $user = $request->user();

        activity('account')
            ->causedBy($user)
            ->performedOn($user)
            ->event('account.deleted')
            ->log('User account deleted');

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::route('welcome')->with('success', 'Your account has been removed.');
    }
}
