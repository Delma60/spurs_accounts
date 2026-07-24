import { useForm } from '@inertiajs/react';
import { Check, BadgeCheck } from 'lucide-react';
import AccountLayout from '@/Components/AccountLayout';
import Field from '@/Components/Field';

export default function Personal({ user, kyc }) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({ name: user.name });
    const save = (e) => { e.preventDefault(); put('/me/profile', { preserveScroll: true }); };

    return (
        <AccountLayout user={user} kyc={kyc} current="personal" title="Personal info">
            <div className="page-head">
                <h1>Personal info</h1>
                <p>Info about you across Spurs Cloud.</p>
            </div>

            <div className="acct-card">
                <div className="acct-card__head"><h2>Your name</h2><p>Shown to apps you sign in to</p></div>
                <form onSubmit={save} noValidate style={{ marginTop: 12 }}>
                    <Field label="Name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} />
                    <div className="form-actions">
                        {recentlySuccessful && <span className="saved-note"><Check size={15} /> Saved</span>}
                        <button className="btn btn--primary" type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save'}
                        </button>
                    </div>
                </form>
            </div>

            <div className="acct-card">
                <div className="acct-card__head"><h2>Account details</h2><p>How you're identified on Spurs</p></div>
                <div style={{ marginTop: 8 }}>
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
                    </div>
                </div>
            </div>
        </AccountLayout>
    );
}
