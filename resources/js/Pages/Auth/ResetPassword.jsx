import { useForm } from '@inertiajs/react';
import AuthShell from '@/Components/AuthShell';
import Field from '@/Components/Field';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email: email || '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/reset-password');
    };

    return (
        <AuthShell title="Set new password">
            <div className="head">
                <h1>Set a new password</h1>
                <p>Choose a strong password you don’t use anywhere else.</p>
            </div>

            <form onSubmit={submit} noValidate>
                <Field
                    label="Email"
                    type="email"
                    autoComplete="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />
                <Field
                    label="New password"
                    type="password"
                    autoComplete="new-password"
                    autoFocus
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />
                <Field
                    label="Confirm new password"
                    type="password"
                    autoComplete="new-password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                />

                <div className="row row--end">
                    <button className="btn btn--primary btn--lg" type="submit" disabled={processing}>
                        {processing ? 'Saving…' : 'Reset password'}
                    </button>
                </div>
            </form>
        </AuthShell>
    );
}
