<?php

namespace App\Http\Controllers;

use App\Models\KycProfile;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Identity verification. KYC is collected once, here on the identity provider,
 * and the resulting tier rides the SSO claims out to every Spurs service.
 *
 * The raw BVN/NIN is never persisted — we store a masked value for display and
 * a hash so duplicate national IDs across accounts can be detected.
 */
class KycController extends Controller
{
    /** ID types the platform currently accepts (admin-managed setting). */
    private function acceptedIdTypes(): array
    {
        $allowed = array_filter(array_map('trim', explode(',', (string) \App\Support\Settings::get('kyc.id_types'))));
        $types = array_intersect_key(KycProfile::ID_TYPES, array_flip($allowed));

        return $types ?: KycProfile::ID_TYPES;
    }

    public function show(Request $request)
    {
        $kyc = $request->user()->kyc()->first();

        return Inertia::render('Account/Kyc', [
            'kyc' => $kyc ? [
                'level' => $kyc->level,
                'status' => $kyc->status,
                'tier' => $kyc->tierLabel(),
                'id_type' => $kyc->id_type,
                'id_masked' => $kyc->id_masked,
                'full_name' => $kyc->full_name,
                'phone' => $kyc->phone,
                'state' => $kyc->state,
                'rejection_reason' => $kyc->rejection_reason,
                'submitted_at' => $kyc->submitted_at?->format('M j, Y'),
            ] : null,
            'idTypes' => $this->acceptedIdTypes(),
            'tiers' => KycProfile::TIERS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_type' => ['required', Rule::in(array_keys($this->acceptedIdTypes()))],
            'id_number' => ['required', 'string', 'max:32'],
            'full_name' => ['required', 'string', 'max:180'],
            'date_of_birth' => ['required', 'date', 'before:-16 years'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:60'],
        ]);

        // Nigerian BVN/NIN are both 11 digits — catch typos before review.
        $digits = preg_replace('/\D/', '', $data['id_number']);
        if (in_array($data['id_type'], KycProfile::NATIONAL_IDS, true) && strlen($digits) !== 11) {
            return back()->withErrors(['id_number' => strtoupper($data['id_type']).' must be 11 digits']);
        }

        $user = $request->user();
        $user->kyc()->updateOrCreate(['user_id' => $user->getKey()], [
            'status' => 'pending',
            'id_type' => $data['id_type'],
            'id_masked' => KycProfile::mask($data['id_number']),
            'id_hash' => KycProfile::hashId($data['id_type'], $data['id_number']),
            'full_name' => $data['full_name'],
            'date_of_birth' => $data['date_of_birth'],
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => 'NG',
            'provider' => 'manual',
            'rejection_reason' => null,
            'submitted_at' => now(),
        ]);

        // Admin can opt into straight-through tier 1 for national IDs.
        $auto = (bool) \App\Support\Settings::get('kyc.auto_approve_tier1');
        if ($auto && \in_array($data['id_type'], KycProfile::NATIONAL_IDS, true)) {
            $user->kyc()->update([
                'status' => 'verified', 'level' => 1,
                'reviewed_by' => 'auto', 'reviewed_at' => now(),
            ]);
            SecurityEvent::record($user, 'kyc_verified', $request);

            return back()->with('status', 'Your identity is verified.');
        }

        SecurityEvent::record($user, 'kyc_submitted', $request);

        return back()->with('status', 'Your details are in review.');
    }
}
