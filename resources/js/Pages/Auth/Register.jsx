import { useForm, router } from '@inertiajs/react';
import AuthShell from '@/Components/AuthShell';
import Field from '@/Components/Field';

const ACCOUNT_TYPES = [
    { value: 'personal', label: 'Personal', hint: 'For you' },
    { value: 'business', label: 'Business', hint: 'A company' },
    { value: 'merchant', label: 'Merchant', hint: 'Sell online' },
    { value: 'developer', label: 'Developer', hint: 'Build on Spurs' },
];

export default function Register({ referral }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        account_type: 'personal',
        phone: '',
        password: '',
        password_confirmation: '',
        ref: referral || '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/register');
    };

    return (
        <AuthShell title="Create account">
            <div className="head">
                <h1>Create your Spurs account</h1>
                <p>One account for everything across Spurs Cloud</p>
                {referral && (
                    <p className="note">You were invited with code <strong>{referral}</strong></p>
                )}
            </div>

            <form onSubmit={submit} noValidate>
                <input type="hidden" name="ref" value={data.ref} />

                <div className="acct-type">
                    <span className="acct-type__label">I&apos;m signing up as</span>
                    <div className="acct-type__opts">
                        {ACCOUNT_TYPES.map((t) => (
                            <button
                                key={t.value}
                                type="button"
                                className={`acct-type__opt${data.account_type === t.value ? ' is-active' : ''}`}
                                onClick={() => setData('account_type', t.value)}
                                aria-pressed={data.account_type === t.value}
                            >
                                <span className="acct-type__name">{t.label}</span>
                                <span className="acct-type__hint">{t.hint}</span>
                            </button>
                        ))}
                    </div>
                    {errors.account_type && <p className="field-error">{errors.account_type}</p>}
                </div>

                <Field
                    label="Full name"
                    autoComplete="name"
                    autoFocus
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                />
                <Field
                    label="Email"
                    type="email"
                    autoComplete="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />
                <Field
                    label="Phone number"
                    type="tel"
                    autoComplete="tel"
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                    error={errors.phone}
                />
                <Field
                    label="Password"
                    type="password"
                    autoComplete="new-password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />
                <Field
                    label="Confirm password"
                    type="password"
                    autoComplete="new-password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                />

                <div className="row">
                    <button type="button" className="link" onClick={() => router.visit('/login')}>
                        Sign in instead
                    </button>
                    <button className="btn btn--primary btn--lg" type="submit" disabled={processing}>
                        {processing ? 'Creating…' : 'Create account'}
                    </button>
                </div>
            </form>
        </AuthShell>
    );
}
