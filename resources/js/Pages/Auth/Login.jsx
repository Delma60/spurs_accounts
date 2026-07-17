import { useForm, router } from '@inertiajs/react';
import AuthShell from '@/Components/AuthShell';
import Field from '@/Components/Field';

export default function Login({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <AuthShell title="Sign in">
            <div className="head">
                <h1>Sign in</h1>
                <p>to continue to your Spurs account</p>
            </div>

            {status && <div className="alert alert--info">{status}</div>}

            <form onSubmit={submit} noValidate>
                <Field
                    label="Email or phone"
                    type="email"
                    autoComplete="email"
                    autoFocus
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />
                <Field
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />
                <button type="button" className="link forgot" onClick={() => router.visit('/forgot-password')}>
                    Forgot password?
                </button>

                <div className="row">
                    <button type="button" className="link" onClick={() => router.visit('/register')}>
                        Create account
                    </button>
                    <button className="btn btn--primary btn--lg" type="submit" disabled={processing}>
                        {processing ? 'Signing in…' : 'Sign in'}
                    </button>
                </div>
            </form>
        </AuthShell>
    );
}
