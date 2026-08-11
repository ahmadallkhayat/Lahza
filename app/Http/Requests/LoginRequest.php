<?php

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signing in, from either door.
 *
 * Shared by the studio's sign-in and the customer one: the credentials, the
 * throttle and the deliberately vague failure are the same problem whichever
 * side is asking, and two copies of a lockout rule is one copy that quietly
 * stops matching the other.
 */
class LoginRequest extends FormRequest
{
    /** Attempts allowed per username + IP pair before lockout. */
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        // Deliberately loose beyond "present": enforcing a username format here
        // would tell an attacker what shape a real one takes.
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('username', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // Deliberately vague: naming which field was wrong tells an
            // attacker which usernames are real.
            throw ValidationException::withMessages([
                'username' => 'Those credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => sprintf(
                'Too many attempts. Try again in %d second%s.',
                $seconds,
                $seconds === 1 ? '' : 's'
            ),
        ]);
    }

    /** Throttle per username + IP, so one account cannot lock out another. */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}
