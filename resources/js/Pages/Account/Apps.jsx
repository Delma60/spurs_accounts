import { router } from '@inertiajs/react';
import { Blocks } from 'lucide-react';
import AccountLayout from '@/Components/AccountLayout';

export default function Apps({ user, kyc, connectedApps = [] }) {
    const revoke = (clientId, name) => {
        if (confirm(`Remove ${name}'s access to your Spurs account?`)) {
            router.delete(`/me/apps/${clientId}`, { preserveScroll: true });
        }
    };

    return (
        <AccountLayout user={user} kyc={kyc} current="apps" title="Connected apps">
            <div className="page-head">
                <h1>Connected apps</h1>
                <p>Apps you've allowed to access your Spurs Account. First-party Spurs apps use your session and aren't listed here.</p>
            </div>

            <div className="acct-card">
                {connectedApps.length === 0 ? (
                    <div className="empty">
                        <div className="empty__ico"><Blocks size={28} /></div>
                        You haven't connected any apps yet.
                    </div>
                ) : (
                    connectedApps.map((app) => (
                        <div className="app-item" key={app.client_id}>
                            <span className="app-item__logo">{app.name.charAt(0).toUpperCase()}</span>
                            <div className="app-item__meta">
                                <div className="app-item__name">{app.name}</div>
                                <div className="app-item__scopes">
                                    {(app.scopes ?? []).join(', ') || 'Basic profile'}
                                    {app.authorized_at ? ` · since ${app.authorized_at}` : ''}
                                </div>
                            </div>
                            <button className="btn btn--danger btn--sm" onClick={() => revoke(app.client_id, app.name)}>
                                Remove access
                            </button>
                        </div>
                    ))
                )}
            </div>
        </AccountLayout>
    );
}
