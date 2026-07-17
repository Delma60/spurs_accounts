import { router } from '@inertiajs/react';
import AuthShell from '@/Components/AuthShell';

export default function Home({ user }) {
    if (!user) {
        return (
            <AuthShell title="Welcome">
                <div className="head">
                    <h1>Welcome to Spurs</h1>
                    <p>One account for everything across Spurs Cloud.</p>
                </div>
                <div className="row row--end">
                    <button className="btn btn--primary btn--lg" onClick={() => router.visit('/login')}>
                        Sign in
                    </button>
                </div>
            </AuthShell>
        );
    }

    const initial = (user.name || '?').charAt(0).toUpperCase();

    return (
        <AuthShell title="Your account">
            <div className="head">
                <h1>Your Spurs account</h1>
            </div>

            <div className="me">
                <span className="me__avatar">{initial}</span>
                <div>
                    <div className="me__name">{user.name}</div>
                    <div className="me__email">{user.email}</div>
                </div>
            </div>

            <p className="muted-text">
                You’re signed in. Apps across Spurs Cloud can now sign you in
                automatically with this account.
            </p>

            <div className="row row--end">
                <button className="btn btn--outline" onClick={() => router.post('/logout')}>
                    Sign out
                </button>
            </div>
        </AuthShell>
    );
}
