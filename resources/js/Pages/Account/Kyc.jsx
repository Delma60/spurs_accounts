import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { BadgeCheck, Clock, CircleX, ShieldCheck, Upload, Check, IdCard, Camera, Home } from 'lucide-react';
import AccountLayout from '@/Components/AccountLayout';

const PROOF_TYPES = {
    utility_bill: 'Utility bill (PHCN, water, waste)',
    bank_statement: 'Bank statement',
    tenancy_agreement: 'Tenancy agreement',
};

const STEPS = [
    { n: 1, label: 'Identity', Icon: IdCard, blurb: 'BVN or NIN — basic limits' },
    { n: 2, label: 'Document', Icon: Camera, blurb: 'ID photo + selfie — higher limits' },
    { n: 3, label: 'Address', Icon: Home, blurb: 'Proof of address — full access' },
];

export default function Kyc({ user, kyc, idTypes, tiers }) {
    const { props } = usePage();
    const status = kyc?.status ?? 'unverified';
    const level = kyc?.level ?? 0;
    const submitted = !!kyc?.submitted_at;

    // Land on the first tier they haven't cleared yet.
    const [step, setStep] = useState(Math.min(3, (level || 0) + 1));

    const badge = {
        verified: { cls: 'badge badge--ok', Icon: BadgeCheck, text: `Verified — tier ${level}` },
        pending: { cls: 'badge badge--warn', Icon: Clock, text: 'In review' },
        rejected: { cls: 'badge badge--danger', Icon: CircleX, text: 'Rejected' },
        unverified: { cls: 'badge', Icon: ShieldCheck, text: 'Not verified' },
    }[status];

    return (
        <AccountLayout user={user} kyc={kyc} current="kyc" title="Identity verification">
            <div className="page-head">
                <h1>Identity verification</h1>
                <p>Complete as much as you need — each tier unlocks more across Spurs. You can stop at any step and come back later.</p>
            </div>

            <div className="acct-card">
                <div className="kyc-status">
                    <span className={badge.cls}><badge.Icon size={13} /> {badge.text}</span>
                    {kyc?.id_masked && (
                        <span style={{ fontSize: 13, color: 'var(--fg-muted)' }}>
                            BVN · {kyc.id_masked}
                        </span>
                    )}
                    {kyc?.tier2_id_masked && (
                        <span style={{ fontSize: 13, color: 'var(--fg-muted)' }}>
                            {(idTypes?.[kyc.tier2_id_type] ?? kyc.tier2_id_type)} · {kyc.tier2_id_masked}
                        </span>
                    )}
                </div>
                {status === 'rejected' && kyc?.rejection_reason && (
                    <div className="notice notice--err" style={{ marginTop: 12 }}>{kyc.rejection_reason}</div>
                )}
                {status === 'pending' && (
                    <p style={{ marginTop: 10, fontSize: 13, color: 'var(--fg-muted)' }}>
                        Submitted {kyc?.submitted_at}. We'll email you when it's reviewed.
                    </p>
                )}
                {props?.flash?.status && <div className="notice notice--ok" style={{ marginTop: 12 }}>{props.flash.status}</div>}

                {/* Step rail */}
                <div className="kyc-steps">
                    {STEPS.map((s) => {
                        const done = level >= s.n;
                        const active = step === s.n;
                        return (
                            <button key={s.n} type="button" onClick={() => setStep(s.n)}
                                className={`kyc-step${active ? ' is-active' : ''}${done ? ' is-done' : ''}`}>
                                <span className="kyc-step__dot">{done ? <Check size={14} /> : s.n}</span>
                                <span className="kyc-step__text">
                                    <span className="kyc-step__label">Tier {s.n} · {s.label}</span>
                                    <span className="kyc-step__blurb">{s.blurb}</span>
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {step === 1 && <Tier1 kyc={kyc} user={user} onDone={() => setStep(2)} />}
            {step === 2 && <Tier2 locked={!submitted} onDone={() => setStep(3)} />}
            {step === 3 && <Tier3 kyc={kyc} locked={!submitted} />}
        </AccountLayout>
    );
}

/* ------------------------------- tier 1 --------------------------------- */

function Tier1({ kyc, user, onDone }) {
    const form = useForm({
        step: 1,
        id_number: '',
        full_name: kyc?.full_name ?? user?.name ?? '',
        date_of_birth: '',
        phone: kyc?.phone ?? '',
    });
    const submit = (e) => {
        e.preventDefault();
        form.post('/me/kyc', { preserveScroll: true, onSuccess: () => onDone?.() });
    };

    return (
        <form onSubmit={submit} className="acct-card">
            <div className="acct-card__head">
                <h2>Tier 1 — your BVN</h2>
                <p>We store a one-way hash of your BVN, never the full number.</p>
            </div>
            <div style={{ marginTop: 14 }}>
                <F label="BVN" error={form.errors.id_number} hint="Your Bank Verification Number is 11 digits.">
                    <input inputMode="numeric" value={form.data.id_number}
                        onChange={(e) => form.setData('id_number', e.target.value)} placeholder="•••••••••••" />
                </F>
                <F label="Full name (as on your BVN)" error={form.errors.full_name}>
                    <input value={form.data.full_name} onChange={(e) => form.setData('full_name', e.target.value)} />
                </F>
                <div className="fld-row">
                    <F label="Date of birth" error={form.errors.date_of_birth}>
                        <input type="date" value={form.data.date_of_birth} onChange={(e) => form.setData('date_of_birth', e.target.value)} />
                    </F>
                    <F label="Phone number" error={form.errors.phone}>
                        <input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} placeholder="+234…" />
                    </F>
                </div>
            </div>
            <Actions processing={form.processing} label="Submit tier 1" note="Phone number and BVN are both required to complete tier 1." />
        </form>
    );
}

/* ------------------------------- tier 2 --------------------------------- */

function Tier2({ locked, idTypes, onDone }) {
    const form = useForm({
        step: 2,
        id_type: Object.keys(idTypes ?? {})[0] ?? 'nin',
        id_number: '',
        document: null,
        selfie: null,
    });
    const submit = (e) => {
        e.preventDefault();
        form.post('/me/kyc', { preserveScroll: true, forceFormData: true, onSuccess: () => onDone?.() });
    };

    if (locked) return <Locked step={1} />;

    return (
        <form onSubmit={submit} className="acct-card">
            <div className="acct-card__head">
                <h2>Tier 2 — national ID, document &amp; selfie</h2>
                <p>Provide a second national ID (not BVN), a photo of it, and a selfie to match.</p>
            </div>
            <div style={{ marginTop: 14 }}>
                <div className="fld-row">
                    <F label="ID type" error={form.errors.id_type}>
                        <select value={form.data.id_type} onChange={(e) => form.setData('id_type', e.target.value)}>
                            {Object.entries(idTypes ?? {}).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </select>
                    </F>
                    <F label="ID number" error={form.errors.id_number}>
                        <input value={form.data.id_number} onChange={(e) => form.setData('id_number', e.target.value)} placeholder="•••••••••••" />
                    </F>
                </div>
                <div className="fld-row">
                    <F label="ID document" error={form.errors.document} hint="JPG, PNG or PDF · max 5MB">
                        <input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(e) => form.setData('document', e.target.files[0])} />
                    </F>
                    <F label="Selfie" error={form.errors.selfie} hint="JPG or PNG · max 5MB">
                        <input type="file" accept=".jpg,.jpeg,.png" onChange={(e) => form.setData('selfie', e.target.files[0])} />
                    </F>
                </div>
            </div>
            <Actions processing={form.processing} progress={form.progress} label="Submit tier 2" note="Required for higher limits." />
        </form>
    );
}
/* ------------------------------- tier 3 --------------------------------- */

function Tier3({ kyc, locked }) {
    const form = useForm({
        step: 3,
        address: kyc?.address ?? '',
        state: kyc?.state ?? '',
        address_proof: null,
        address_proof_type: 'utility_bill',
    });
    const submit = (e) => {
        e.preventDefault();
        form.post('/me/kyc', { preserveScroll: true, forceFormData: true });
    };

    if (locked) return <Locked step={1} />;

    return (
        <form onSubmit={submit} className="acct-card">
            <div className="acct-card__head">
                <h2>Tier 3 — proof of address</h2>
                <p>A document from the last 3 months showing your name and address.</p>
            </div>
            <div style={{ marginTop: 14 }}>
                <F label="Residential address" error={form.errors.address}>
                    <input value={form.data.address} onChange={(e) => form.setData('address', e.target.value)}
                        placeholder="12 Adeola Odeku St, Victoria Island" />
                </F>
                <div className="fld-row">
                    <F label="State" error={form.errors.state}>
                        <input value={form.data.state} onChange={(e) => form.setData('state', e.target.value)} placeholder="Lagos" />
                    </F>
                    <F label="Document type" error={form.errors.address_proof_type}>
                        <select value={form.data.address_proof_type} onChange={(e) => form.setData('address_proof_type', e.target.value)}>
                            {Object.entries(PROOF_TYPES).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </select>
                    </F>
                </div>
                <F label="Proof of address" error={form.errors.address_proof} hint="JPG, PNG or PDF · max 5MB">
                    <input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(e) => form.setData('address_proof', e.target.files[0])} />
                </F>
            </div>
            <Actions processing={form.processing} progress={form.progress} label="Submit tier 3" note="Unlocks full access, including higher withdrawals." />
        </form>
    );
}

/* ------------------------------- shared --------------------------------- */

function Locked({ step }) {
    return (
        <div className="acct-card">
            <div className="empty">
                <div className="empty__ico"><ShieldCheck size={26} /></div>
                Complete tier {step} first — then this step opens up.
            </div>
        </div>
    );
}

function Actions({ processing, progress, label, note }) {
    return (
        <>
            {progress && <p style={{ fontSize: 12, color: 'var(--fg-muted)', marginTop: 12 }}>Uploading… {progress.percentage}%</p>}
            <div className="form-actions">
                <button type="submit" className="btn btn--primary" disabled={processing}>
                    <Upload size={15} /> {processing ? 'Submitting…' : label}
                </button>
            </div>
            <p style={{ fontSize: 12, color: 'var(--fg-faint)', margin: '10px 0 0' }}>{note}</p>
        </>
    );
}

function F({ label, hint, error, children }) {
    return (
        <label className="fld">
            <span className="fld__label">{label}</span>
            {children}
            {hint && !error && <span className="fld__hint">{hint}</span>}
            {error && <span className="fld__err">{error}</span>}
        </label>
    );
}
