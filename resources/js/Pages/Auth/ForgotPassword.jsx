import { useForm, router } from '@inertiajs/react';
import AuthShell from '@/Components/AuthShell';
import Field from '@/Components/Field';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <AuthShell title="Reset password">
            <div className="head">
                <h1>Reset password</h1>
                <p>Enter your email and we’ll send you a link to get back into your account.</p>
            </div>

            {status && <div className="alert alert--info">{status}</div>}

            <form onSubmit={submit} noValidate>
                <Field
                    label="Email"
                    type="email"
                    autoComplete="email"
                    autoFocus
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />

                <div className="row">
                    <button type="button" className="link" onClick={() => router.visit('/login')}>
                        Back to sign in
                    </button>
                    <button className="btn btn--primary btn--lg" type="submit" disabled={processing}>
                        {processing ? 'Sending…' : 'Send link'}
                    </button>
                </div>
            </form>
        </AuthShell>
    );
}
