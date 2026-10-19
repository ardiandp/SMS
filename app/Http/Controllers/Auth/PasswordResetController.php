<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Pemulihan akses akun portal (PRD-06).
 *
 * Respons dibuat netral ("bila email terdaftar...") agar tidak membocorkan
 * akun mana yang ada — tetapi di belakang layar Password::sendResetLink
 * hanya mengirim bila email benar-benar terdaftar. Token kedaluwarsa 60
 * menit, throttle 60 detik antar permintaan (config/auth.php).
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.password-request');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Bila email terdaftar, tautan pemulihan sudah dikirim.');
    }

    public function reset(string $token): View
    {
        return view('auth.password-reset', ['token' => $token]);
    }
