import { useId, useState } from 'react';

/** Floating-label field with error state + optional password reveal. */
export default function Field({ label, type = 'text', error, value, onChange, ...props }) {
    const id = useId();
    const [show, setShow] = useState(false);
    const isPassword = type === 'password';
    const inputType = isPassword && show ? 'text' : type;

    return (
        <div className={`field${error ? ' field--error' : ''}`}>
            <div className="field__box">
                <input
                    id={id}
                    type={inputType}
                    placeholder=" "
                    value={value}
                    onChange={onChange}
                    {...props}
                />
                <label htmlFor={id}>{label}</label>
                {isPassword && (
                    <button
                        type="button"
                        className="field__toggle"
                        onClick={() => setShow((s) => !s)}
                        tabIndex={-1}
                    >
                        {show ? 'Hide' : 'Show'}
                    </button>
                )}
            </div>
            {error && (
                <div className="field-error">
                    <span aria-hidden>⚠</span> {error}
                </div>
            )}
        </div>
    );
}
