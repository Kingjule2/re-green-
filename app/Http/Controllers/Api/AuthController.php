<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Session authentication for the same-origin app.
 *
 * The SPA is served by this same Laravel application, so a session cookie is
 * the whole mechanism: no token to store in the browser, and the CSRF token
 * rendered into the Blade layout covers every mutating request. The API routes
 * therefore run in the `web` middleware group.
 */
class AuthController extends Controller
{
    /**
     * Create an account and sign it in. The role decides what the account can
     * see: a farmer their own lands, pemda/NGO and corporate accounts the
     * aggregate picture.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return UserResource::make($user)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::guard('web')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok.',
            ]);
        }

        $request->session()->regenerate();

        return UserResource::make($request->user())->response();
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
