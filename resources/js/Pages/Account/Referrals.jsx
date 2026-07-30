import { useState } from 'react';
import { Gift, Copy, Check, Users, Coins } from 'lucide-react';
import AccountLayout from '@/Components/AccountLayout';

const naira = (n) => '₦' + Number(n || 0).toLocaleString();

export default function Referrals({ user, kyc, referral }) {
    const [copied, setCopied] = useState(null);

    const copy = (text, which) => {
        navigator.clipboard?.writeText(text).then(() => {
            setCopied(which);
            setTimeout(() => setCopied(null), 1500);
        });
    };

    return (
        <AccountLayout user={user} kyc={kyc} current="referrals" title="Invite & earn">
            <div className="page-head">
                <h1>Invite &amp; earn</h1>
                <p>Share your code — when a friend signs up, your reward lands in your Spurs Wallet.</p>
            </div>

            {!referral.enabled && (
                <div className="acct-card" style={{ borderColor: 'var(--warn, #b45309)' }}>
                    <div className="acct-card__head">
                        <h2>Referrals are paused</h2>
                        <p>The program isn&apos;t running right now. Your code still works and will reward you once it&apos;s back on.</p>
                    </div>
                </div>
            )}

            {/* Share card */}
            <div className="acct-card">
                <div className="acct-card__head">
                    <h2>Your invite</h2>
                    <p>{referral.bonus > 0
                        ? `You earn ${naira(referral.bonus)} for every friend who joins with your code.`
                        : 'Share your code with friends to bring them onto Spurs.'}</p>
                </div>

                <div className="info-row">
                    <span className="info-row__label">Referral code</span>
                    <span className="info-row__value" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <strong style={{ letterSpacing: 1 }}>{referral.code}</strong>
                        <button className="btn btn--outline btn--sm" onClick={() => copy(referral.code, 'code')}>
                            {copied === 'code' ? <Check size={14} /> : <Copy size={14} />} {copied === 'code' ? 'Copied' : 'Copy'}
                        </button>
                    </span>
                </div>

                <div className="fld__label" style={{ marginTop: 12 }}>Share link</div>
                <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
                    <input className="fld__input" readOnly value={referral.link} style={{ flex: 1 }} onFocus={(e) => e.target.select()} />
                    <button className="btn btn--primary btn--sm" onClick={() => copy(referral.link, 'link')}>
                        {copied === 'link' ? <Check size={14} /> : <Copy size={14} />} {copied === 'link' ? 'Copied' : 'Copy link'}
                    </button>
                </div>
            </div>

            {/* Stats */}
            <div className="acct-card">
                <div className="acct-card__head">
                    <h2>Your rewards</h2>
                    <p>People you&apos;ve brought to Spurs and what you&apos;ve earned.</p>
                </div>

                <div style={{ display: 'flex', gap: 12, marginTop: 8 }}>
                    <Stat Icon={Users} label="Invited" value={referral.invited} />
                    <Stat Icon={Coins} label="Earned" value={naira(referral.earnedNaira)} />
                </div>

                {referral.people.length === 0 ? (
                    <div className="empty" style={{ marginTop: 12 }}>
                        <div className="empty__ico"><Gift size={26} /></div>
                        No referrals yet — share your link to get started.
                    </div>
                ) : (
                    <div style={{ marginTop: 12 }}>
                        {referral.people.map((p, i) => (
                            <div className="signin-row" key={i}>
                                <span className="signin-row__ico"><Users size={18} /></span>
                                <div className="signin-row__meta">
                                    <div className="signin-row__label">{p.name}</div>
                                    <div className="signin-row__value">{p.joined ? `Joined ${p.joined}` : 'Joined'}</div>
                                </div>
                                <span style={{ fontWeight: 650, fontSize: 14, color: p.status === 'paid' ? 'var(--ok)' : 'var(--fg-muted)' }}>
                                    {p.status === 'paid' ? `+${naira(p.amountNaira)}` : p.status === 'failed' ? 'Retry pending' : 'Pending'}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AccountLayout>
    );
}

function Stat({ Icon, label, value }) {
    return (
        <div className="acct-card" style={{ flex: 1, margin: 0, padding: '14px 16px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: 'var(--fg-muted)', fontSize: 13 }}>
                <Icon size={16} /> {label}
            </div>
            <div style={{ fontSize: 22, fontWeight: 700, marginTop: 4 }}>{value}</div>
        </div>
    );
}
