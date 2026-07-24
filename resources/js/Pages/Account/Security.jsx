import { useForm, Link } from '@inertiajs/react';
import {
    Mail, KeyRound, Smartphone, Blocks, Check, BadgeCheck,
    History, LogIn, UserPlus, ShieldAlert, MapPin,
} from 'lucide-react';
import AccountLayout from '@/Components/AccountLayout';
import Field from '@/Components/Field';
import { usePager } from '@/Components/Pager';

const EVENT_ICON = {
    registered: UserPlus,
    login: LogIn,
    login_failed: ShieldAlert,
    password_changed: KeyRound,
    password_reset: KeyRound,
    email_verified: BadgeCheck,
    app_connected: Blocks,
    app_revoked: Blocks,
    kyc_submitted: BadgeCheck,
    kyc_verified: BadgeCheck,
};

export default function Security({ user, kyc, connectedApps = [], securityEvents = [] }) {
    const { data, setData, put, processing, errors, reset, recentlySuccessful } = useForm({
        current_password: '', password: '', password_confirmation: '',
    });
    const save = (e) => {
        e.preventDefault();
        put('/me/password', { preserveScroll: true, onSuccess: () => reset() });
    };
    const [events, pager] = usePager(securityEvents, 8);

    return (
        <AccountLayout user={user} kyc={kyc} current="security" title="Security">
            <div className="page-head">
                <h1>Security &amp; sign-in</h1>
                <p>How you sign in, and what's been happening on your account.</p>
            </div>

            <div className="acct-card">
                <div className="acct-card__head"><h2>Sign-in methods</h2><p>Your credentials and protection</p></div>
                <div style={{ marginTop: 8 }}>
                    <Row Icon={Mail} label="Email" value={user.email}
                        right={user.email_verified
                            ? <span className="badge badge--ok"><BadgeCheck size={13} /> Verified</span>
                            : <span className="badge badge--warn">Unverified</span>} />
                    <Row Icon={KeyRound} label="Password" value="••••••••" right={<span className="badge badge--ok"><Check size={13} /> Set</span>} />
                    <Row Icon={Smartphone} label="2-Step Verification" value="Add a second step when signing in"
                        right={<span className="badge badge--warn">Coming soon</span>} />
                    <Row Icon={Blocks} label="Connected apps" value={`${connectedApps.length} app${connectedApps.length === 1 ? '' : 's'} can access your account`}
                        right={<Link href="/me/apps" className="btn btn--outline btn--sm">Review</Link>} />
                </div>
            </div>

            <div className="acct-card">
                <div className="acct-card__head"><h2>Change password</h2><p>Use a strong password you don't reuse elsewhere</p></div>
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
                <div className="acct-card__head"><h2>Recent activity</h2><p>Sign-ins and changes on your Spurs account</p></div>
                {securityEvents.length === 0 ? (
                    <div className="empty">
                        <div className="empty__ico"><History size={28} /></div>
                        No recent activity yet.
                    </div>
                ) : (
                    <div style={{ marginTop: 8 }}>
                        {events.map((ev) => {
                            const Icon = EVENT_ICON[ev.type] ?? History;
                            const meta = [ev.device, ev.location, ev.ip].filter(Boolean).join(' · ');
                            return (
                                <div className="signin-row" key={ev.id}>
                                    <span className="signin-row__ico"><Icon size={18} /></span>
                                    <div className="signin-row__meta">
                                        <div className="signin-row__label">
                                            {ev.label}
                                            {ev.flagged && <span className="badge badge--danger" style={{ marginLeft: 8 }}>Flagged</span>}
                                        </div>
                                        <div className="signin-row__value">
                                            {ev.location && <MapPin size={11} style={{ verticalAlign: -1, marginRight: 4 }} />}
                                            {meta || 'Unknown device'}
                                        </div>
                                    </div>
                                    <span className="event-time">{ev.at}</span>
                                </div>
                            );
                        })}
                        {pager}
                    </div>
                )}
            </div>
        </AccountLayout>
    );
}

function Row({ Icon, label, value, right }) {
    return (
        <div className="signin-row">
            <span className="signin-row__ico"><Icon size={18} /></span>
            <div className="signin-row__meta">
                <div className="signin-row__label">{label}</div>
                <div className="signin-row__value">{value}</div>
            </div>
            {right}
        </div>
    );
}
