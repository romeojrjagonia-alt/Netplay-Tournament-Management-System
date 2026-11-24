<?php

namespace App\Support\Profile;

use App\Mail\PasswordChanged;
use App\Rules\AccountPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * The three-step "Security Details" password change shared by every role's
 * profile modal: current password, new password, confirm. Pair it with the
 * `<x-profile.security-form>` component in the modal's view.
 */
trait ChangesPassword
{
    public bool $securityView = false;

    /** 1 = current password, 2 = new password, 3 = confirm it. */
    public int $passwordStep = 1;

    /** Set only on the server once the current password was checked. */
    #[Locked]
    public bool $currentPasswordVerified = false;

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    /**
     * Accounts created with Google have no password yet, so there is nothing
     * to check in step 1 and they go straight to setting one.
     */
    #[Computed]
    public function hasPassword(): bool
    {
        return filled(auth()->user()->password);
    }

    public function openSecurity(): void
    {
        $this->resetPasswordFlow();
        $this->securityView = true;
    }

    public function closeSecurity(): void
    {
        $this->resetPasswordFlow();
        $this->securityView = false;
    }

    /**
     * Step 1: the player proves it is them by entering the current password.
     */
    public function verifyCurrentPassword(): void
    {
        $this->validate(['currentPassword' => ['required', 'string']], ['currentPassword.required' => 'Enter your current password.']);

        $throttleKey = 'player-password:'.auth()->id();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('currentPassword', 'Too many attempts. Please wait a minute and try again.');

            return;
        }

        if (! Hash::check($this->currentPassword, (string) auth()->user()->password)) {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('currentPassword', 'That is not your current password.');

            return;
        }

        RateLimiter::clear($throttleKey);

        $this->currentPassword = '';
        $this->currentPasswordVerified = true;
        $this->passwordStep = 2;
    }

    /**
     * Step 2: choose a new password that follows the account password rules.
     */
    public function submitNewPassword(): void
    {
        abort_unless($this->currentPasswordVerified, 403);

        $this->validate(['newPassword' => ['required', 'string', new AccountPassword]]);

        if ($this->hasPassword && Hash::check($this->newPassword, (string) auth()->user()->password)) {
            $this->addError('newPassword', 'Your new password must be different from your current one.');

            return;
        }

        $this->newPasswordConfirmation = '';
        $this->passwordStep = 3;
    }

    public function backToNewPassword(): void
    {
        $this->newPasswordConfirmation = '';
        $this->resetValidation();
        $this->passwordStep = 2;
    }

    /**
     * Step 3: confirm the new password, save it and tell the player by email.
     */
    public function confirmNewPassword(): void
    {
        abort_unless($this->currentPasswordVerified && $this->passwordStep === 3, 403);

        $this->validate(['newPassword' => ['required', 'string', new AccountPassword]]);

        if (! hash_equals($this->newPassword, $this->newPasswordConfirmation)) {
            $this->addError('newPasswordConfirmation', 'The passwords do not match.');

            return;
        }

        $user = auth()->user();
        $user->update(['password' => $this->newPassword]);

        try {
            Mail::to($user->email)->send(new PasswordChanged($user, now()->format('F j, Y \a\t g:i A')));
        } catch (\Throwable $e) {
            // The password is already changed; a mail problem must not undo it.
            report($e);
        }

        $this->closeSecurity();

        $this->dispatch('system-message', title: 'Password changed', message: 'Your password was changed successfully. We sent a confirmation to your email.', variant: 'success');
    }

    private function resetPasswordFlow(): void
    {
        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');
        $this->resetValidation();
        $this->currentPasswordVerified = ! $this->hasPassword;
        $this->passwordStep = $this->hasPassword ? 1 : 2;
    }
}
