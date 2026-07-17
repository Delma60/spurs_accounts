import { Head } from '@inertiajs/react';

/** Page frame for every accounts screen: brand + card + footer. */
export default function AuthShell({ title, brand = 'Spurs', children }) {
    return (
        <div className="auth-shell">
            {title && <Head title={title} />}
            <div className="auth-card">
                <div className="brand">
                    <span className="brand__mark">S</span>
                    <span className="brand__name">{brand}</span>
                </div>
                {children}
            </div>
            <div className="auth-foot">
                <button type="button" className="auth-foot__lang">English (United States) ▾</button>
                <div className="auth-foot__links">
                    <a href="#">Help</a>
                    <a href="#">Privacy</a>
                    <a href="#">Terms</a>
                </div>
            </div>
        </div>
    );
}
