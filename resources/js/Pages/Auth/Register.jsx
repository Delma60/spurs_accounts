import { useForm, router } from '@inertiajs/react';
import AuthShell from '@/Components/AuthShell';
import Field from '@/Components/Field';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
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
            </div>

            <form onSubmit={submit} noValidate>
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
