import { useForm, router } from '@inertiajs/react';
import AuthShell from '@/Components/AuthShell';

export default function VerifyEmail({ status, email }) {
    const { post, processing } = useForm({});
    const sent = status === 'verification-link-sent';

    const resend = (e) => {
        e.preventDefault();
        post('/email/verification-notification', { preserveScroll: true });
    };

    return (
        <AuthShell title="Verify email">
            <div className="head">
                <h1>Verify your email</h1>
                <p>We sent a verification link to <strong>{email}</strong>. Click it to confirm this is you.</p>
            </div>

            {sent && (
                <div className="alert alert--info">
                    A new verification link has been sent to your email.
                </div>
            )}

            <form onSubmit={resend} noValidate>
                <div className="row">
                    <button type="button" className="link" onClick={() => router.visit('/me')}>
                        Continue to account
                    </button>
                    <button className="btn btn--primary btn--lg" type="submit" disabled={processing}>
                        {processing ? 'Sending…' : 'Resend link'}
                    </button>
                </div>
            </form>
        </AuthShell>
    );
}
