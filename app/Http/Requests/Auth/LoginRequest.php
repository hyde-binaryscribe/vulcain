<?php

namespace App\Http\Requests\Auth;

use App\Domain\Identity\LoginThrottle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * Authentifie l'utilisateur, cloisonné à l'organisation courante, avec
     * blocage temporaire après trop d'échecs.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $throttle = app(LoginThrottle::class);
        $organisationId = app(TenantContext::class)->id();
        $email = (string) $this->input('email');

        if ($throttle->tooManyAttempts($organisationId, $email)) {
            event(new Lockout($this));

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => $throttle->availableIn($organisationId, $email),
                ]),
            ]);
        }

        $authenticated = Auth::attempt([
            'email' => $email,
            'password' => (string) $this->input('password'),
            'is_active' => true,
        ], $this->boolean('remember'));

        if (! $authenticated) {
            $throttle->record($organisationId, $email, $this->ip(), $this->userAgent(), false);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $throttle->record($organisationId, $email, $this->ip(), $this->userAgent(), true);
        $throttle->clear($organisationId, $email);
    }
}
