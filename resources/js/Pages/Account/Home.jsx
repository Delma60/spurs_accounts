import { Link } from '@inertiajs/react';
import { ChevronRight, BadgeCheck, ShieldCheck, Blocks, User } from 'lucide-react';
import AccountLayout, { MobileNav } from '@/Components/AccountLayout';

export default function AccountHome({ user, kyc, appCount = 0 }) {
    const initial = (user.name || '?').charAt(0).toUpperCase();
    const kycStatus = kyc?.status ?? 'unverified';

    return (
        <AccountLayout user={user} kyc={kyc} current="home">
            <div className="acct-hero">
                <div className="acct-hero__avatar">{initial}</div>
                <h1>Welcome, {user.name.split(' ')[0]}</h1>
                <p>Your Spurs Account is the one identity behind Wallet, Pay and Cloud. Manage your info, security and verification here.</p>
            </div>

            {/* Verification nudge — the highest-value action for most people */}
            {kycStatus !== 'verified' && (
                <div className="acct-card" style={{ display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap' }}>
                    <span className="navlist__ico"><BadgeCheck size={20} /></span>
                    <div style={{ flex: 1, minWidth: 200 }}>
                        <div style={{ fontSize: 14, fontWeight: 600 }}>
                            {kycStatus === 'pending' ? 'Verification in review' : 'Verify your identity'}
                        </div>
                        <div style={{ fontSize: 13, color: 'var(--fg-muted)', marginTop: 2 }}>
                            {kycStatus === 'pending'
                                ? "We're checking your details — we'll email you when it's done."
                                : 'Unlock higher limits and withdrawals across Spurs.'}
                        </div>
                    </div>
                    {kycStatus !== 'pending' && (
                        <Link href="/me/kyc" className="btn btn--primary btn--sm">Verify now</Link>
                    )}
                </div>
            )}

            {/* sm/xs: the sidebar renders here as a card of rows */}
            <MobileNav />

            {/* md/lg: summary cards */}
            <div className="only-desktop" style={{ display: 'grid', gap: 16 }}>
                <SummaryCard
                    Icon={User} title="Personal info" desc="Basic info like your name and email"
                    href="/me/personal" cta="Manage"
                    rows={[['Name', user.name], ['Email', user.email]]}
                />
                <SummaryCard
                    Icon={BadgeCheck} title="Identity verification" desc="Verification unlocks full access"
                    href="/me/kyc" cta={kycStatus === 'verified' ? 'View' : 'Verify'}
                    rows={[['Status', <StatusBadge key="s" kyc={kyc} />]]}
                />
                <SummaryCard
                    Icon={ShieldCheck} title="Security & sign-in" desc="Password, activity and protection"
                    href="/me/security" cta="Review"
                    rows={[['Email', user.email_verified ? 'Verified' : 'Unverified']]}
                />
                <SummaryCard
                    Icon={Blocks} title="Connected apps" desc={`${appCount} app${appCount === 1 ? '' : 's'} with access to your account`}
                    href="/me/apps" cta="Manage access" rows={[]}
                />
            </div>
        </AccountLayout>
    );
}

function StatusBadge({ kyc }) {
    const s = kyc?.status ?? 'unverified';
    if (s === 'verified') return <span className="badge badge--ok"><BadgeCheck size={13} /> Tier {kyc?.level ?? 1}</span>;
    if (s === 'pending') return <span className="badge badge--warn">In review</span>;
    if (s === 'rejected') return <span className="badge badge--danger">Rejected</span>;
    return <span className="badge">Not verified</span>;
}

function SummaryCard({ Icon, title, desc, href, cta, rows = [] }) {
    return (
        <div className="acct-card">
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
                <span className="navlist__ico"><Icon size={20} /></span>
                <div className="acct-card__head" style={{ flex: 1 }}>
                    <h2>{title}</h2>
                    <p>{desc}</p>
                </div>
                <Link href={href} className="btn btn--outline btn--sm">
                    {cta} <ChevronRight size={15} />
                </Link>
            </div>

            {rows.length > 0 && (
                <div style={{ marginTop: 8 }}>
                    {rows.map(([label, value]) => (
                        <div className="info-row" key={label}>
                            <span className="info-row__label">{label}</span>
                            <span className="info-row__value">{value}</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
