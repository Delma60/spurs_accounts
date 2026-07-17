import { router } from '@inertiajs/react';
import AuthShell from '@/Components/AuthShell';

export default function Consent({ client, user, scopes, authToken, state, clientId }) {
    const payload = { state, client_id: clientId, auth_token: authToken };
    const approve = () => router.post('/oauth/authorize', payload);
    const deny = () => router.delete('/oauth/authorize', { data: payload });

    const initial = (client.name || '?').charAt(0).toUpperCase();

    return (
        <AuthShell title="Authorize">
            <div className="head">
                <h1>Sign in with Spurs</h1>
                <p>{client.name} is requesting access to your account</p>
            </div>

            <div className="consent-app">
                <span className="consent-app__logo">{initial}</span>
                <div className="consent-app__meta">
                    <div className="consent-app__name">{client.name}</div>
                    <div className="consent-app__sub">Signed in as {user.email}</div>
                </div>
            </div>

            <p className="muted-text">This will allow {client.name} to:</p>
            <ul className="scopes">
                <li>
                    <span className="scopes__ico">👤</span>
                    See your name and profile info
                </li>
                <li>
                    <span className="scopes__ico">✉️</span>
                    See your email address
                </li>
                {scopes.map((s) => (
                    <li key={s.id}>
                        <span className="scopes__ico">🔑</span>
                        {s.description}
                    </li>
                ))}
            </ul>

            <div className="row">
                <button className="btn btn--outline" onClick={deny}>Cancel</button>
                <button className="btn btn--primary btn--lg" onClick={approve}>Allow</button>
            </div>
        </AuthShell>
    );
}
