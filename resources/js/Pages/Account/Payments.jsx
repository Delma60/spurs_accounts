import { useState } from 'react';
import { Wallet, ArrowLeftRight, RefreshCw, ArrowUpRight, ArrowDownLeft, ExternalLink, Blocks } from 'lucide-react';
import AccountLayout from '@/Components/AccountLayout';
import { usePager } from '@/Components/Pager';

const TABS = [
    { id: 'wallet', label: 'Wallet', Icon: Wallet },
    { id: 'transactions', label: 'Transactions', Icon: ArrowLeftRight },
    { id: 'subscriptions', label: 'Subscriptions', Icon: RefreshCw },
];

const WALLET_URL = 'http://127.0.0.1:3200';

export default function Payments({ user, kyc, balances = [], transactions = [], subscriptions = [], connectedApps = [] }) {
    const [tab, setTab] = useState('wallet');

    return (
        <AccountLayout user={user} kyc={kyc} current="payments" title="Payments & subscriptions">
            <div className="page-head">
                <h1>Payments &amp; subscriptions</h1>
                <p>Your Spurs balances, money movement and recurring payments — all in one place.</p>
            </div>

            <div className="seg">
                {TABS.map((t) => (
                    <button key={t.id} onClick={() => setTab(t.id)}
                        className={`seg__btn${tab === t.id ? ' is-active' : ''}`}>
                        <t.Icon size={15} /> {t.label}
                    </button>
                ))}
            </div>

            {tab === 'wallet' && <WalletTab balances={balances} />}
            {tab === 'transactions' && <TransactionsTab items={transactions} />}
            {tab === 'subscriptions' && <SubscriptionsTab subscriptions={subscriptions} apps={connectedApps} />}
        </AccountLayout>
    );
}

function WalletTab({ balances }) {
    const fiat = balances.filter((b) => b.kind === 'fiat');
    const crypto = balances.filter((b) => b.kind === 'crypto');

    return (
        <>
            <div className="acct-card">
                <div className="acct-card__head">
                    <h2>Balances</h2>
                    <p>Held in your Spurs Wallet across every currency</p>
                </div>

                {balances.length === 0 ? (
                    <div className="empty">
                        <div className="empty__ico"><Wallet size={26} /></div>
                        No wallet yet — open Spurs Wallet to get started.
                    </div>
                ) : (
                    <div style={{ marginTop: 8 }}>
                        {[['Cash', fiat], ['Crypto', crypto]].map(([label, group]) => group.length > 0 && (
                            <div key={label} style={{ marginTop: 6 }}>
                                <div className="fld__label" style={{ marginTop: 10 }}>{label}</div>
                                {group.map((b) => (
                                    <div className="info-row" key={b.asset}>
                                        <span className="info-row__label">{b.asset}</span>
                                        <span className="info-row__value" style={{ fontWeight: 600 }}>{b.display}</span>
                                    </div>
                                ))}
                            </div>
                        ))}
                    </div>
                )}

                <div className="form-actions">
                    <a className="btn btn--outline btn--sm" href={WALLET_URL} target="_blank" rel="noreferrer">
                        Open Spurs Wallet <ExternalLink size={14} />
                    </a>
                </div>
            </div>
        </>
    );
}

function TransactionsTab({ items }) {
    const [rows, pager] = usePager(items, 10);

    return (
        <div className="acct-card">
            <div className="acct-card__head">
                <h2>Recent transactions</h2>
                <p>Money in and out of your Spurs Wallet</p>
            </div>

            {items.length === 0 ? (
                <div className="empty">
                    <div className="empty__ico"><ArrowLeftRight size={26} /></div>
                    No transactions yet.
                </div>
            ) : (
                <div style={{ marginTop: 8 }}>
                    {rows.map((t) => {
                        const credit = t.direction === 'credit';
                        return (
                            <div className="signin-row" key={t.reference}>
                                <span className="signin-row__ico" style={{
                                    color: credit ? 'var(--ok)' : 'var(--fg-muted)',
                                    background: credit ? 'var(--ok-soft)' : 'var(--card-2)',
                                }}>
                                    {credit ? <ArrowDownLeft size={18} /> : <ArrowUpRight size={18} />}
                                </span>
                                <div className="signin-row__meta">
                                    <div className="signin-row__label">
                                        {t.description || t.source.replace(/_/g, ' ')}
                                    </div>
                                    <div className="signin-row__value">
                                        {new Date(t.createdAt).toLocaleString()} · {t.reference}
                                    </div>
                                </div>
                                <span style={{ fontWeight: 650, fontSize: 14, color: credit ? 'var(--ok)' : 'var(--fg)' }}>
                                    {t.display}
                                </span>
                            </div>
                        );
                    })}
                    {pager}
                </div>
            )}
        </div>
    );
}

function SubscriptionsTab({ subscriptions, apps }) {
    return (
        <>
            <div className="acct-card">
                <div className="acct-card__head">
                    <h2>Subscriptions</h2>
                    <p>Recurring payments charged to your Spurs Account</p>
                </div>

                {subscriptions.length === 0 ? (
                    <div className="empty">
                        <div className="empty__ico"><RefreshCw size={26} /></div>
                        You have no active subscriptions.
                    </div>
                ) : (
                    <div style={{ marginTop: 8 }}>
                        {subscriptions.map((s) => (
                            <div className="signin-row" key={s.id}>
                                <span className="signin-row__ico"><RefreshCw size={18} /></span>
                                <div className="signin-row__meta">
                                    <div className="signin-row__label">{s.name}</div>
                                    <div className="signin-row__value">{s.interval} · next {s.next_charge}</div>
                                </div>
                                <span style={{ fontWeight: 650, fontSize: 14 }}>{s.display}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <div className="acct-card">
                <div className="acct-card__head">
                    <h2>Apps that can charge you</h2>
                    <p>Third-party apps you've authorised. Removing access stops future charges.</p>
                </div>
                {apps.length === 0 ? (
                    <div className="empty">
                        <div className="empty__ico"><Blocks size={26} /></div>
                        No third-party apps connected.
                    </div>
                ) : (
                    <div style={{ marginTop: 8 }}>
                        {apps.map((a) => (
                            <div className="app-item" key={a.client_id}>
                                <span className="app-item__logo">{a.name.charAt(0).toUpperCase()}</span>
                                <div className="app-item__meta">
                                    <div className="app-item__name">{a.name}</div>
                                    <div className="app-item__scopes">{a.authorized_at ? `Connected ${a.authorized_at}` : 'Connected'}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
