<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /** Resend the email verification link. */
    public function store(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/me');
        }

        $sent = $request->user()->sendEmailVerificationNotification();

        return back()->with('status', $sent ? 'verification-link-sent' : 'verification-link-failed');
    }
}
