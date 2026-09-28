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
        $tenant = app(TenantContext::class);
        // Accès par compte : l'e-mail est unique au global, l'organisation est
        // déduite du compte. Le throttle est donc indexé par e-mail seul.
        $email = (string) $this->input('email');

        if ($throttle->tooManyAttempts(null, $email)) {
            event(new Lockout($this));

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => $throttle->availableIn(null, $email),
                ]),
            ]);
        }

        // Recherche de l'utilisateur hors scope d'organisation (aucun tenant
        // n'est encore défini avant la connexion).
        $authenticated = $tenant->runCrossTenant(fn () => Auth::attempt([
            'email' => $email,
            'password' => (string) $this->input('password'),
            'is_active' => true,
        ], $this->boolean('remember')));

        if (! $authenticated) {
            $throttle->record(null, $email, $this->ip(), $this->userAgent(), false);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Organisation suspendue : accès refusé.
        $user = Auth::guard('web')->user();
        if ($user->organisation === null || ! $user->organisation->isActive()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'Cette organisation est suspendue. Contactez le support.',
            ]);
        }

        $throttle->record(null, $email, $this->ip(), $this->userAgent(), true);
        $throttle->clear(null, $email);
    }
}
