<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Intentar autenticar solo si el usuario está ACTIVO.
     */
    public function authenticate(): void
{
    $this->ensureIsNotRateLimited();

    $remember = $this->boolean('remember');
    $email    = trim((string) $this->input('email'));
    $password = (string) $this->input('password');

    // 1) Buscar usuario por email
    $user = User::where('email', $email)->first();

    // 2) Correo no existe o contraseña incorrecta
    if (!$user || !Hash::check($password, $user->password)) {
        \Illuminate\Support\Facades\RateLimiter::hit($this->throttleKey());
        throw ValidationException::withMessages([
            'email' => 'Correo o contraseña incorrectos.',
        ]);
    }

    // 3) Usuario inactivo (ajusta el nombre de la columna)
    $activo = (bool) ($user->activo ?? $user->active ?? $user->is_active);
    if (!$activo) {
        \Illuminate\Support\Facades\RateLimiter::hit($this->throttleKey());
        throw ValidationException::withMessages([
            'email' => 'Tu cuenta no se encuentra activa.',
        ]);
    }

    // 4) Todo OK → iniciar sesión
    Auth::login($user, $remember);

    \Illuminate\Support\Facades\RateLimiter::clear($this->throttleKey());
}

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
