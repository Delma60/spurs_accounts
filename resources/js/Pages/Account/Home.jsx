import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import {
    House, User, ShieldCheck, Blocks, LogOut, Mail, KeyRound,
    Smartphone, Check, ChevronRight, ChevronLeft, BadgeCheck,
} from 'lucide-react';
import Field from '@/Components/Field';

const NAV = [
    { id: 'home', label: 'Home', Icon: House },
    { id: 'personal', label: 'Personal info', Icon: User },
    { id: 'security', label: 'Security & sign-in', Icon: ShieldCheck },
    { id: 'apps', label: 'Connected apps', Icon: Blocks },
];

export default function AccountHome({ user, connectedApps }) {
    const [section, setSection] = useState('home');
    const initial = (user.name || '?').charAt(0).toUpperCase();

    return (
        <div className="acct-shell">
            <Head title="Your Spurs Account" />

            <header className="acct-topbar">
                <div className="acct-topbar__brand">
                    <span className="brand__mark">S</span>
                    <span>Spurs <small>Account</small></span>
                </div>
                <button className="acct-avatar" title={user.email} onClick={() => setSection('personal')}>
                    {initial}
                </button>
            </header>

            <div className="acct-body">
                <nav className="acct-nav">
                    {NAV.map(({ id, label, Icon }) => (
                        <button
                            key={id}
                            className={`acct-nav__item${section === id ? ' is-active' : ''}`}
                            onClick={() => setSection(id)}
                        >
                            <span className="acct-nav__ico"><Icon size={19} /></span>
                            {label}
                        </button>
                    ))}
                    <button className="acct-nav__item is-signout" onClick={() => router.post('/logout')}>
                        <span className="acct-nav__ico"><LogOut size={19} /></span>
                        Sign out
                    </button>
                </nav>

                <main className="acct-main">
                    {section !== 'home' && (
                        <button className="acct-back" onClick={() => setSection('home')}>
                            <ChevronLeft size={18} /> Back
                        </button>
                    )}
                    {section === 'home' && <HomeSection user={user} apps={connectedApps} onGo={setSection} />}
                    {section === 'personal' && <PersonalSection user={user} />}
                    {section === 'security' && <SecuritySection user={user} apps={connectedApps} onGo={setSection} />}
                    {section === 'apps' && <AppsSection apps={connectedApps} />}
                </main>
            </div>
        </div>
    );
}

function HomeSection({ user, apps, onGo }) {
    const initial = (user.name || '?').charAt(0).toUpperCase();
    return (
        <>
            <div className="acct-hero">
                <div className="acct-hero__avatar">{initial}</div>
                <h1>Welcome, {user.name.split(' ')[0]}</h1>
                <p>Manage your info, security and connected apps for Spurs Cloud.</p>
            </div>

            {/* sm/xs: the sidebar shows here as a card of section rows (like Google My Account) */}
            <div className="acct-card navlist-card only-mobile">
                {NAV.filter((n) => n.id !== 'home').map(({ id, label, Icon }) => (
                    <button key={id} className="navlist__row" onClick={() => onGo(id)}>
                        <span className="navlist__ico"><Icon size={20} /></span>
                        <span className="navlist__label">{label}</span>
                        <ChevronRight size={18} className="navlist__chev" />
                    </button>
                ))}
                <button className="navlist__row" onClick={() => router.post('/logout')}>
                    <span className="navlist__ico"><LogOut size={20} /></span>
                    <span className="navlist__label">Sign out</span>
                    <ChevronRight size={18} className="navlist__chev" />
                </button>
            </div>

            {/* md/lg: richer summary cards alongside the sidebar */}
            <div className="only-desktop">
                <div className="acct-card">
                    <div className="acct-card__head"><h2>Personal info</h2><p>Basic info like your name and email</p></div>
                    <div className="info-row">
                        <span className="info-row__label">Name</span>
                        <span className="info-row__value">{user.name}</span>
                        <button className="btn btn--outline" onClick={() => onGo('personal')}>Manage</button>
                    </div>
                    <div className="info-row">
                        <span className="info-row__label">Email</span>
                        <span className="info-row__value">{user.email}</span>
                        <span />
                    </div>
                </div>

                <div className="acct-card">
                    <div className="acct-card__head"><h2>Security &amp; sign-in</h2><p>Keep your account safe</p></div>
                    <button className="btn btn--outline" onClick={() => onGo('security')}>
                        Review security <ChevronRight size={16} />
                    </button>
                </div>

                <div className="acct-card">
                    <div className="acct-card__head">
                        <h2>Connected apps</h2>
                        <p>{apps.length} app{apps.length === 1 ? '' : 's'} with access to your account</p>
                    </div>
                    <button className="btn btn--outline" onClick={() => onGo('apps')}>Manage access</button>
                </div>
            </div>
        </>
    );
}

function PersonalSection({ user }) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({ name: user.name });
    const save = (e) => { e.preventDefault(); put('/me/profile', { preserveScroll: true }); };

    return (
        <div className="acct-card">
            <div className="acct-card__head"><h2>Personal info</h2><p>Info about you across Spurs Cloud</p></div>

            <form onSubmit={save} noValidate style={{ marginTop: 12 }}>
                <Field label="Name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} />
                <div className="form-actions">
                    {recentlySuccessful && <span className="saved-note"><Check size={15} /> Saved</span>}
                    <button className="btn btn--primary" type="submit" disabled={processing}>
                        {processing ? 'Saving…' : 'Save'}
                    </button>
                </div>
            </form>

            <div className="info-row">
                <span className="info-row__label">Email</span>
                <span className="info-row__value">
                    {user.email}
                    <span className="sub">Sign-in email — used to access your account</span>
                </span>
                {user.email_verified
                    ? <span className="badge badge--ok"><BadgeCheck size={13} /> Verified</span>
                    : <span className="badge badge--warn">Unverified</span>}
            </div>
            <div className="info-row">
                <span className="info-row__label">Member since</span>
                <span className="info-row__value">{user.created_at}</span>
                <span />
            </div>
        </div>
    );
}

function SecuritySection({ user, apps, onGo }) {
    const { data, setData, put, processing, errors, reset, recentlySuccessful } = useForm({
        current_password: '', password: '', password_confirmation: '',
    });
    const save = (e) => {
        e.preventDefault();
        put('/me/password', { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <>
            <div className="acct-card">
                <div className="acct-card__head">
                    <h2>How you sign in to Spurs</h2>
                    <p>Your sign-in methods and account protection</p>
                </div>

                <div className="signin-row">
                    <span className="signin-row__ico"><Mail size={20} /></span>
                    <div className="signin-row__meta">
                        <div className="signin-row__label">Email</div>
                        <div className="signin-row__value">{user.email}</div>
                    </div>
                    {user.email_verified
                        ? <span className="badge badge--ok"><BadgeCheck size={13} /> Verified</span>
                        : <span className="badge badge--warn">Unverified</span>}
                </div>

                <div className="signin-row">
                    <span className="signin-row__ico"><KeyRound size={20} /></span>
                    <div className="signin-row__meta">
                        <div className="signin-row__label">Password</div>
                        <div className="signin-row__value"><span className="dots">••••••••</span></div>
                    </div>
                    <span className="badge badge--ok"><Check size={13} /> Set</span>
                </div>

                <div className="signin-row">
                    <span className="signin-row__ico"><Smartphone size={20} /></span>
                    <div className="signin-row__meta">
                        <div className="signin-row__label">2-Step Verification</div>
                        <div className="signin-row__value">Add a second step when signing in</div>
                    </div>
                    <span className="badge badge--warn">Coming soon</span>
                </div>

                <div className="signin-row">
                    <span className="signin-row__ico"><Blocks size={20} /></span>
                    <div className="signin-row__meta">
                        <div className="signin-row__label">Connected apps</div>
                        <div className="signin-row__value">{apps.length} app{apps.length === 1 ? '' : 's'} can access your account</div>
                    </div>
                    <button className="btn btn--outline" onClick={() => onGo('apps')}>Review</button>
                </div>
            </div>

            <div className="acct-card">
                <div className="acct-card__head"><h2>Change password</h2><p>Use a strong password you don’t reuse elsewhere</p></div>
                <form onSubmit={save} noValidate style={{ marginTop: 12 }}>
                    <Field label="Current password" type="password" autoComplete="current-password"
                        value={data.current_password} onChange={(e) => setData('current_password', e.target.value)} error={errors.current_password} />
                    <Field label="New password" type="password" autoComplete="new-password"
                        value={data.password} onChange={(e) => setData('password', e.target.value)} error={errors.password} />
                    <Field label="Confirm new password" type="password" autoComplete="new-password"
                        value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                    <div className="form-actions">
                        {recentlySuccessful && <span className="saved-note"><Check size={15} /> Password updated</span>}
                        <button className="btn btn--primary" type="submit" disabled={processing}>
                            {processing ? 'Updating…' : 'Update password'}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}

function AppsSection({ apps }) {
    const revoke = (clientId) => {
        if (confirm('Remove this app’s access to your Spurs account?')) {
            router.delete(`/me/apps/${clientId}`, { preserveScroll: true });
        }
    };

    return (
        <div className="acct-card">
            <div className="acct-card__head">
                <h2>Connected apps</h2>
                <p>Apps you’ve allowed to access your Spurs account</p>
            </div>

            {apps.length === 0 ? (
                <div className="empty">
                    <div className="empty__ico"><Blocks size={34} /></div>
                    You haven’t connected any apps yet.
                </div>
            ) : (
                apps.map((app) => (
                    <div className="app-item" key={app.client_id}>
                        <span className="app-item__logo">{app.name.charAt(0).toUpperCase()}</span>
                        <div className="app-item__meta">
                            <div className="app-item__name">{app.name}</div>
                            <div className="app-item__scopes">
                                {app.scopes.length ? `Access: ${app.scopes.join(', ')}` : 'Basic sign-in'}
                            </div>
                            <div className="app-item__date">Connected {app.authorized_at}</div>
                        </div>
                        <button className="btn btn--outline" onClick={() => revoke(app.client_id)}>
                            Remove access
                        </button>
                    </div>
                ))
            )}
        </div>
    );
}
