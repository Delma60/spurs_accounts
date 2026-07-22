import { useForm, Link, usePage } from '@inertiajs/react';
import { ShieldCheck, ArrowLeft, Clock, CircleCheck, CircleX } from 'lucide-react';

/**
 * Identity verification. Collected once here on the identity provider; the
 * resulting tier travels with the Spurs session to every service.
 */
export default function Kyc({ kyc, idTypes, tiers }) {
    const { props } = usePage();
    const status = kyc?.status ?? 'unverified';
    const locked = status === 'pending' || status === 'verified';

    const form = useForm({
        id_type: kyc?.id_type ?? 'bvn',
        id_number: '',
        full_name: kyc?.full_name ?? '',
        date_of_birth: '',
        phone: kyc?.phone ?? '',
        address: '',
        state: kyc?.state ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/me/kyc', { preserveScroll: true });
    };

    const badge = {
        verified: { cls: 'kyc-badge is-ok', Icon: CircleCheck, text: kyc?.tier ?? 'Verified' },
        pending: { cls: 'kyc-badge is-wait', Icon: Clock, text: 'In review' },
        rejected: { cls: 'kyc-badge is-bad', Icon: CircleX, text: 'Rejected' },
        unverified: { cls: 'kyc-badge', Icon: ShieldCheck, text: 'Not verified' },
    }[status];

    return (
        <div className="acct-shell">
            <main className="acct-main" style={{ maxWidth: 620, margin: '0 auto', padding: '24px 16px' }}>
                <Link href="/me" className="acct-back"><ArrowLeft size={16} /> Back to account</Link>

                <header style={{ margin: '12px 0 20px' }}>
                    <h1 style={{ fontSize: 22, fontWeight: 600, margin: 0 }}>Identity verification</h1>
                    <p style={{ color: 'var(--muted, #6b7280)', fontSize: 14, marginTop: 4 }}>
                        Verify once — it unlocks higher limits across Spurs Wallet, Pay and Cloud.
                    </p>
                </header>

                <div className="card" style={{ padding: 16, marginBottom: 16 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        <span className={badge.cls} style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                            <badge.Icon size={15} /> {badge.text}
                        </span>
                        {kyc?.id_masked && (
                            <span style={{ fontSize: 13, color: 'var(--muted, #6b7280)' }}>
                                {(idTypes?.[kyc.id_type] ?? kyc.id_type)} · {kyc.id_masked}
                            </span>
                        )}
                    </div>
                    {status === 'rejected' && kyc?.rejection_reason && (
                        <p style={{ marginTop: 10, fontSize: 13, color: '#b91c1c' }}>{kyc.rejection_reason}</p>
                    )}
                    {status === 'pending' && (
                        <p style={{ marginTop: 10, fontSize: 13, color: 'var(--muted, #6b7280)' }}>
                            Submitted {kyc?.submitted_at}. We'll email you when it's reviewed.
                        </p>
                    )}
                    {props?.flash?.status && (
                        <p style={{ marginTop: 10, fontSize: 13, color: '#047857' }}>{props.flash.status}</p>
                    )}
                </div>

                {!locked && (
                    <form onSubmit={submit} className="card" style={{ padding: 16, display: 'grid', gap: 12 }}>
                        <Field label="ID type" error={form.errors.id_type}>
                            <select value={form.data.id_type} onChange={(e) => form.setData('id_type', e.target.value)}>
                                {Object.entries(idTypes ?? {}).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                        </Field>

                        <Field label="ID number" error={form.errors.id_number} hint="BVN and NIN are 11 digits. We never store the full number.">
                            <input inputMode="numeric" value={form.data.id_number}
                                onChange={(e) => form.setData('id_number', e.target.value)} placeholder="•••••••••••" />
                        </Field>

                        <Field label="Full name (as on your ID)" error={form.errors.full_name}>
                            <input value={form.data.full_name} onChange={(e) => form.setData('full_name', e.target.value)} />
                        </Field>

                        <Field label="Date of birth" error={form.errors.date_of_birth}>
                            <input type="date" value={form.data.date_of_birth} onChange={(e) => form.setData('date_of_birth', e.target.value)} />
                        </Field>

                        <Field label="Phone number" error={form.errors.phone}>
                            <input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} placeholder="+234…" />
                        </Field>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                            <Field label="State" error={form.errors.state}>
                                <input value={form.data.state} onChange={(e) => form.setData('state', e.target.value)} placeholder="Lagos" />
                            </Field>
                            <Field label="Address (optional)" error={form.errors.address}>
                                <input value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                            </Field>
                        </div>

                        <button type="submit" disabled={form.processing} className="btn btn--primary" style={{ marginTop: 4 }}>
                            {form.processing ? 'Submitting…' : 'Submit for verification'}
                        </button>
                        <p style={{ fontSize: 12, color: 'var(--muted, #6b7280)', margin: 0 }}>
                            Your ID is stored as a one-way hash — we keep only the last 4 digits for reference.
                        </p>
                    </form>
                )}

                <div style={{ marginTop: 20 }}>
                    <h2 style={{ fontSize: 13, textTransform: 'uppercase', letterSpacing: '.04em', color: 'var(--muted, #6b7280)' }}>Tiers</h2>
                    <ul style={{ listStyle: 'none', padding: 0, margin: '8px 0 0', display: 'grid', gap: 6 }}>
                        {Object.entries(tiers ?? {}).map(([lvl, label]) => (
                            <li key={lvl} style={{ display: 'flex', gap: 8, fontSize: 13, opacity: Number(lvl) <= (kyc?.level ?? 0) ? 1 : 0.6 }}>
                                <ShieldCheck size={15} /> {label}
                            </li>
                        ))}
                    </ul>
                </div>
            </main>
        </div>
    );
}

function Field({ label, hint, error, children }) {
    return (
        <label style={{ display: 'block' }}>
            <span style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--muted, #6b7280)', marginBottom: 5 }}>{label}</span>
            {children}
            {hint && !error && <span style={{ display: 'block', fontSize: 12, color: 'var(--muted, #9ca3af)', marginTop: 4 }}>{hint}</span>}
            {error && <span style={{ display: 'block', fontSize: 12, color: '#b91c1c', marginTop: 4 }}>{error}</span>}
        </label>
    );
}
