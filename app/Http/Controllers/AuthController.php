<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\LoginRequest;
use App\Models\LoginHistory;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Tampilkan form login.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Proses login.
     */
    public function login(LoginRequest $request)
    {
        try {
            $response = $this->authService->login(
                $request->input('email'),
                $request->input('password')
            );

            // Simpan ke session
            $this->authService->storeSession($response);

            // Catat login history (upsert berdasarkan perangkat: IP + User Agent)
            $user = $response['data'] ?? $response['user'] ?? [];
            LoginHistory::updateOrCreate(
                [
                    'email'      => $user['email'] ?? $request->input('email'),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
                [
                    'user_id'  => $user['id'] ?? null,
                    'name'     => $user['name'] ?? '-',
                    'role'     => $user['role'] ?? '-',
                    'login_at' => now(),
                ]
            );

            return redirect()->route('dashboard')
                ->with('success', 'Selamat datang, ' . ($user['name'] ?? 'User') . '!');
        } catch (ApiException $e) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Logout — hapus session, hapus riwayat login perangkat ini, redirect ke login.
     */
    public function logout(Request $request)
    {
        // Hapus riwayat login untuk perangkat saat ini (IP + User Agent)
        $user = session('user', []);
        LoginHistory::where('email', $user['email'] ?? '')
            ->where('ip_address', $request->ip())
            ->where('user_agent', $request->userAgent())
            ->delete();

        $this->authService->clearSession();

        return redirect()->route('login')
            ->with('success', 'Anda telah berhasil keluar.');
    }
}
