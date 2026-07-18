import { useEffect, useRef, useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import {
    House, User, ShieldCheck, Blocks, LogOut, Mail, KeyRound,
    Smartphone, Check, ChevronRight, ChevronLeft, BadgeCheck,
    LogIn, UserPlus, History,
} from 'lucide-react';

const EVENT_ICON = {
    registered: UserPlus,
    login: LogIn,
    password_changed: KeyRound,
    password_reset: KeyRound,
    email_verified: BadgeCheck,
    app_connected: Blocks,
    app_revoked: Blocks,
};
import Field from '@/Components/Field';

const NAV = [
    { id: 'home', label: 'Home', Icon: House },
    { id: 'personal', label: 'Personal info', Icon: User, desc: 'Your name and email' },
    { id: 'security', label: 'Security & sign-in', Icon: ShieldCheck, desc: 'Password and protection' },
    { id: 'apps', label: 'Connected apps', Icon: Blocks, desc: 'Apps with account access' },
];

export default function AccountHome({ user, connectedApps, securityEvents }) {
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
                <AvatarMenu user={user} initial={initial} onManage={() => setSection('home')} />
            </header>

            {!user.email_verified && <VerifyBanner />}

            <div className="acct-body">
                {/* md/lg: sidebar navigation */}
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
                    <button className="acct-nav__item" onClick={() => router.post('/logout')}>
                        <span className="acct-nav__ico"><LogOut size={19} /></span>
                        Sign out
                    </button>
                </nav>

                <main className="acct-main">
                    {/* sm/xs only: back to the card menu */}
                    {section !== 'home' && (
                        <button className="acct-back" onClick={() => setSection('home')}>
                            <ChevronLeft size={18} /> Back
                        </button>
                    )}
                    {section === 'home' && <HomeSection user={user} apps={connectedApps} onGo={setSection} />}
                    {section === 'personal' && <PersonalSection user={user} />}
                    {section === 'security' && <SecuritySection user={user} apps={connectedApps} events={securityEvents} onGo={setSection} />}
                    {section === 'apps' && <AppsSection apps={connectedApps} />}
                </main>
            </div>
        </div>
    );
}

function AvatarMenu({ user, initial, onManage }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        if (!open) return;
        const onDown = (e) => ref.current && !ref.current.contains(e.target) && setOpen(false);
        const onKey = (e) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    return (
        <div className="acct-menu" ref={ref}>
            <button className="acct-avatar" title={user.email} aria-haspopup="menu" aria-expanded={open}
                onClick={() => setOpen((o) => !o)}>
                {initial}
            </button>
            {open && (
                <div className="acct-menu__pop" role="menu">
                    <div className="acct-menu__id">
                        <div className="acct-menu__name">{user.name}</div>
                        <div className="acct-menu__email">{user.email}</div>
                    </div>
                    <button className="acct-menu__item" onClick={() => { setOpen(false); onManage(); }}>
                        <User size={16} /> Manage your account
                    </button>
                    <button className="acct-menu__item" onClick={() => router.post('/logout')}>
                        <LogOut size={16} /> Sign out
                    </button>
                </div>
            )}
        </div>
    );
}

function VerifyBanner() {
    const { post, processing } = useForm({});
    const [sent, setSent] = useState(false);
    const resend = () => post('/email/verification-notification', {
        preserveScroll: true,
        onSuccess: () => setSent(true),
    });

    return (
        <div className="verify-banner">
            <Mail size={18} />
            <span>Verify your email address to fully secure your account.</span>
            {sent ? (
                <span className="verify-banner__sent"><Check size={15} /> Link sent — check your inbox</span>
            ) : (
                <button className="verify-banner__btn" onClick={resend} disabled={processing}>
                    {processing ? 'Sending…' : 'Send verification link'}
                </button>
            )}
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
                <p>Manage your info, security and connected apps for Spurs.</p>
            </div>

            {/* sm/xs: the sidebar's items render here as a card of section rows */}
            <div className="acct-card navlist-card only-mobile">
                {NAV.filter((n) => n.id !== 'home').map(({ id, label, Icon, desc }) => (
                    <button key={id} className="navlist__row" onClick={() => onGo(id)}>
                        <span className="navlist__ico"><Icon size={20} /></span>
                        <span className="navlist__text">
                            <span className="navlist__label">{label}</span>
                            <span className="navlist__desc">{desc}</span>
                        </span>
                        <ChevronRight size={18} className="navlist__chev" />
                    </button>
                ))}
                <button className="navlist__row" onClick={() => router.post('/logout')}>
                    <span className="navlist__ico"><LogOut size={20} /></span>
                    <span className="navlist__text"><span className="navlist__label">Sign out</span></span>
                    <ChevronRight size={18} className="navlist__chev" />
                </button>
            </div>

            {/* md/lg: summary cards beside the sidebar */}
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

function SecuritySection({ user, apps, events = [], onGo }) {
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

            <div className="acct-card">
                <div className="acct-card__head">
                    <h2>Recent security activity</h2>
                    <p>Recent events on your Spurs account</p>
                </div>
                {events.length === 0 ? (
                    <div className="empty">
                        <div className="empty__ico"><History size={30} /></div>
                        No recent activity yet.
                    </div>
                ) : (
                    events.map((ev) => {
                        const Icon = EVENT_ICON[ev.type] ?? History;
                        const meta = [ev.device, ev.ip].filter(Boolean).join(' · ');
                        return (
                            <div className="signin-row" key={ev.id}>
                                <span className="signin-row__ico"><Icon size={20} /></span>
                                <div className="signin-row__meta">
                                    <div className="signin-row__label">{ev.label}</div>
                                    <div className="signin-row__value">{meta || 'Unknown device'}</div>
                                </div>
                                <span className="event-time">{ev.at}</span>
                            </div>
                        );
                    })
                )}
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
