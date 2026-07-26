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
        $user = $request->user();
    $kyc = $user->kyc()->first();

        return Inertia::render('Account/Kyc', [
            'user' => [
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'created_at' => $user->created_at?->format('M j, Y'),
        ],
            'kyc' => $kyc ? [
                'level' => $kyc->level,
                'status' => $kyc->status,
                'tier' => $kyc->tierLabel(),
                'id_type' => $kyc->id_type,
                'id_masked' => $kyc->id_masked,
                'full_name' => $kyc->full_name,
                'phone' => $kyc->phone,
                'address' => $kyc->address,
                'state' => $kyc->state,
                'rejection_reason' => $kyc->rejection_reason,
                'submitted_at' => $kyc->submitted_at?->format('M j, Y'),
            ] : null,
            'idTypes' => $this->acceptedIdTypes(),
            'tiers' => KycProfile::TIERS,
        ]);
    }

    /**
     * Submit one tier at a time. Each step stands on its own, so a user can
     * finish at tier 1 and come back for 2 and 3 later (or never).
     */
    public function store(Request $request)
    {
        $step = (int) $request->input('step', 1);
        $user = $request->user();
        $existing = $user->kyc()->first();

        if ($step > 1 && ! $existing) {
            return back()->withErrors(['step' => 'Complete tier 1 first']);
        }

        $payload = match ($step) {
            2 => $this->tier2($request),
            3 => $this->tier3($request),
            default => $this->tier1($request),
        };
        if ($payload instanceof \Illuminate\Http\RedirectResponse) {
            return $payload; // validation bounced
        }

        // Store any documents on the private disk — never a public URL.
        foreach (['document' => 'document_ref', 'selfie' => 'selfie_ref', 'address_proof' => 'address_proof_ref'] as $input => $column) {
            if ($request->hasFile($input)) {
                $payload[$column] = $request->file($input)->store("kyc/{$user->getKey()}", 'local');
            }
        }

        $user->kyc()->updateOrCreate(['user_id' => $user->getKey()], [
            ...$payload,
            'status' => 'pending',
            'country' => 'NG',
            'provider' => 'manual',
            'rejection_reason' => null,
            'submitted_at' => now(),
        ]);

        // Admin can opt into straight-through tier 1 for national IDs.
        $auto = (bool) \App\Support\Settings::get('kyc.auto_approve_tier1');
        if ($step === 1 && $auto && \in_array($request->input('id_type'), KycProfile::NATIONAL_IDS, true)) {
            $user->kyc()->update([
                'status' => 'verified', 'level' => 1,
                'reviewed_by' => 'auto', 'reviewed_at' => now(),
            ]);
            SecurityEvent::record($user, 'kyc_verified', $request);

            return back()->with('status', 'Your identity is verified.');
        }

        SecurityEvent::record($user, 'kyc_submitted', $request);

        return back()->with('status', "Tier {$step} submitted — it's in review.");
    }

    /** Tier 1 — national ID and personal details. */
    private function tier1(Request $request): array|\Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'id_type' => ['required', Rule::in(array_keys($this->acceptedIdTypes()))],
            'id_number' => ['required', 'string', 'max:32'],
            'full_name' => ['required', 'string', 'max:180'],
            'date_of_birth' => ['required', 'date', 'before:-16 years'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        // Nigerian BVN/NIN are both 11 digits — catch typos before review.
        $digits = preg_replace('/\D/', '', $data['id_number']);
        if (\in_array($data['id_type'], KycProfile::NATIONAL_IDS, true) && \strlen($digits) !== 11) {
            return back()->withErrors(['id_number' => strtoupper($data['id_type']).' must be 11 digits']);
        }

        return [
            'id_type' => $data['id_type'],
            'id_masked' => KycProfile::mask($data['id_number']),
            'id_hash' => KycProfile::hashId($data['id_type'], $data['id_number']),
            'full_name' => $data['full_name'],
            'date_of_birth' => $data['date_of_birth'],
            'phone' => $data['phone'],
        ];
    }

    /** Tier 2 — government ID document plus a selfie. */
    private function tier2(Request $request): array|\Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        return []; // files are attached by the caller
    }

    /** Tier 3 — proof of address. */
    private function tier3(Request $request): array|\Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'address' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:60'],
            'address_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'address_proof_type' => ['required', 'in:utility_bill,bank_statement,tenancy_agreement'],
        ]);

        return [
            'address' => $data['address'],
            'state' => $data['state'],
            'address_proof_type' => $data['address_proof_type'],
        ];
    }
}
