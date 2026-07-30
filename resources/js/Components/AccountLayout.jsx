import { useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    House, User, ShieldCheck, Blocks, LogOut, Mail, Check,
    ChevronRight, ChevronLeft, BadgeCheck, Wallet, ArrowLeftRight, RefreshCw, Gift,
} from 'lucide-react';

/**
 * The shell every /me page shares: topbar, avatar menu, verify banner and the
 * section navigation. Each section is its own route now, so the sidebar uses
 * real links (back/forward and deep links work).
 */
export const NAV = [
    { id: 'home', href: '/me', label: 'Home', Icon: House },
    { id: 'personal', href: '/me/personal', label: 'Personal info', Icon: User, desc: 'Your name and email' },
    { id: 'security', href: '/me/security', label: 'Security & sign-in', Icon: ShieldCheck, desc: 'Password and protection' },
    { id: 'kyc', href: '/me/kyc', label: 'Identity verification', Icon: BadgeCheck, desc: 'Verify to unlock full access' },
    { id: 'payments', href: '/me/payments', label: 'Payments & subscriptions', Icon: Wallet, desc: 'Balances, transactions and recurring payments' },
    { id: 'referrals', href: '/me/referrals', label: 'Invite & earn', Icon: Gift, desc: 'Share your code and earn rewards' },
    { id: 'apps', href: '/me/apps', label: 'Connected apps', Icon: Blocks, desc: 'Apps with account access' },
];

export default function AccountLayout({ user, current = 'home', title, kyc, children }) {
    const initial = (user?.name || '?').charAt(0).toUpperCase();

    return (
        <div className="acct-shell">
            <Head title={title ? `${title} — Spurs Account` : 'Your Spurs Account'} />

            <header className="acct-topbar">
                <div className="acct-topbar__brand">
                    <span className="brand__mark">S</span>
                    <span>Spurs <small>Account</small></span>
                </div>
                <AvatarMenu user={user} initial={initial} />
            </header>

            {!user?.email_verified && <VerifyBanner />}

            <div className="acct-body">
                <nav className="acct-nav">
                    {NAV.map(({ id, href, label, Icon }) => (
                        <Link key={id} href={href} className={`acct-nav__item${current === id ? ' is-active' : ''}`}>
                            <span className="acct-nav__ico"><Icon size={19} /></span>
                            {label}
                            {id === 'kyc' && <KycDot kyc={kyc} />}
                        </Link>
                    ))}
                    <button className="acct-nav__item" onClick={() => router.post('/logout')}>
                        <span className="acct-nav__ico"><LogOut size={19} /></span>
                        Sign out
                    </button>
                </nav>

                <main className="acct-main">
                    {current !== 'home' && (
                        <Link href="/me" className="acct-back"><ChevronLeft size={18} /> Back</Link>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}

/** Small status dot so an unverified user is nudged toward KYC. */
function KycDot({ kyc }) {
    const status = kyc?.status ?? 'unverified';
    if (status === 'verified') return <span className="badge badge--ok" style={{ marginLeft: 'auto' }}><Check size={12} /> Tier {kyc?.level ?? 1}</span>;
    if (status === 'pending') return <span className="badge badge--warn" style={{ marginLeft: 'auto' }}>In review</span>;
    return <span className="badge badge--warn" style={{ marginLeft: 'auto' }}>Verify</span>;
}

/** The section list shown as cards on small screens (sidebar is hidden there). */
export function MobileNav() {
    return (
        <div className="acct-card navlist-card only-mobile">
            {NAV.filter((n) => n.id !== 'home').map(({ id, href, label, Icon, desc }) => (
                <Link key={id} href={href} className="navlist__row">
                    <span className="navlist__ico"><Icon size={20} /></span>
                    <span className="navlist__text">
                        <span className="navlist__label">{label}</span>
                        <span className="navlist__desc">{desc}</span>
                    </span>
                    <ChevronRight size={18} className="navlist__chev" />
                </Link>
            ))}
            <button className="navlist__row" onClick={() => router.post('/logout')}>
                <span className="navlist__ico"><LogOut size={20} /></span>
                <span className="navlist__text"><span className="navlist__label">Sign out</span></span>
                <ChevronRight size={18} className="navlist__chev" />
            </button>
        </div>
    );
}

function AvatarMenu({ user, initial }) {
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
            <button className="acct-avatar" title={user?.email} aria-haspopup="menu" aria-expanded={open}
                onClick={() => setOpen((o) => !o)}>
                {initial}
            </button>
            {open && (
                <div className="acct-menu__pop" role="menu">
                    <div className="acct-menu__id">
                        <div className="acct-menu__name">{user?.name}</div>
                        <div className="acct-menu__email">{user?.email}</div>
                    </div>
                    <Link href="/me" className="acct-menu__item" onClick={() => setOpen(false)}>
                        <User size={16} /> Manage your account
                    </Link>
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
    const { flash } = usePage().props;
    const status = flash?.status;

    const resend = () => post('/email/verification-notification', {
        preserveScroll: true,
    });

    const sent = status === 'verification-link-sent';
    const failed = status === 'verification-link-failed';

    return (
        <div className="verify-banner">
            <Mail size={18} />
            <span>Verify your email address to fully secure your account.</span>
            {sent ? (
                <span className="verify-banner__sent"><Check size={15} /> Link sent — check your inbox</span>
            ) : failed ? (
                <span className="verify-banner__sent" style={{ color: '#b45309' }}>
                    We couldn’t send the verification link right now. Please try again in a moment.
                </span>
            ) : (
                <button className="verify-banner__btn" onClick={resend} disabled={processing}>
                    {processing ? 'Sending…' : 'Send verification link'}
                </button>
            )}
        </div>
    );
}
