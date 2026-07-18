import { Head } from '@inertiajs/react';
import AuthShell from '@/Components/AuthShell';

export default function Consent({ client, user, scopes, authToken, state, clientId, csrf }) {
    const initial = (client.name || '?').charAt(0).toUpperCase();

    // OAuth approve/deny must be NATIVE form posts: Passport responds with a
    // 302 to the client's redirect_uri, which needs a full-page navigation
    // (not an Inertia XHR). Hidden fields carry the auth token + CSRF.
    const hidden = (
        <>
            <input type="hidden" name="_token" value={csrf} />
            <input type="hidden" name="state" value={state ?? ''} />
            <input type="hidden" name="client_id" value={clientId} />
            <input type="hidden" name="auth_token" value={authToken} />
        </>
    );

    return (
        <AuthShell title="Authorize">
            <Head title="Authorize" />
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
                <li><span className="scopes__ico">👤</span>See your name and profile info</li>
                <li><span className="scopes__ico">✉️</span>See your email address</li>
                {scopes.map((s) => (
                    <li key={s.id}><span className="scopes__ico">🔑</span>{s.description}</li>
                ))}
            </ul>

            <div className="row">
                {/* Deny */}
                <form method="POST" action="/oauth/authorize">
                    {hidden}
                    <input type="hidden" name="_method" value="DELETE" />
                    <button className="btn btn--outline" type="submit">Cancel</button>
                </form>
                {/* Approve */}
                <form method="POST" action="/oauth/authorize">
                    {hidden}
                    <button className="btn btn--primary btn--lg" type="submit">Allow</button>
                </form>
            </div>
        </AuthShell>
    );
}
