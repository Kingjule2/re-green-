/**
 * Sign in and sign up.
 *
 * Both modes share one two-column layout: the left panel says what the platform
 * is, the right panel is the form. The page renders outside {@see AppShell}, so
 * there is no navigation to fall back on — the cross-link between the two modes
 * and the brand link are the only ways out.
 *
 * Validation is the server's job: the form submits, then renders whatever
 * `ApiError` returns per field. Some of Laravel's default messages are still in
 * English, so they pass through a small dictionary before they reach the screen
 * — user-facing text is Indonesian throughout.
 */
import React, { useState } from 'react';
import { useAuth } from '@/react/contexts/AuthContext';
import { ApiError } from '@/react/lib/api';
import { ROLES } from '@/react/lib/i18n';
import { Link, navigate } from '@/react/lib/router';

const DEMO_ACCOUNTS = [
    { email: 'petani@regreen.id', label: 'Petani' },
    { email: 'bappeda@regreen.id', label: 'Pemerintah daerah / NGO' },
    { email: 'csr@regreen.id', label: 'Korporasi (CSR/ESG)' },
];

const DEMO_PASSWORD = 'password';

const ROLE_ICONS = {
    farmer: 'agriculture',
    institution: 'account_balance',
    corporate: 'corporate_fare',
};

/** Laravel's default (English) validation strings this form can produce. */
const DEFAULT_VALIDATION_MESSAGES = {
    'The name field is required.': 'Nama wajib diisi.',
    'The name field must not be greater than 255 characters.': 'Nama maksimal 255 karakter.',
    'The email field is required.': 'Email wajib diisi.',
    'The email field must be a valid email address.': 'Format email tidak valid.',
    'The email field must not be greater than 255 characters.': 'Email maksimal 255 karakter.',
    'The password field is required.': 'Kata sandi wajib diisi.',
    'The password field must be at least 8 characters.': 'Kata sandi minimal 8 karakter.',
    'The organization field must not be greater than 255 characters.': 'Nama organisasi maksimal 255 karakter.',
    'The selected role is invalid.': 'Peran yang dipilih tidak dikenali.',
};

const FIELD_FALLBACKS = {
    name: 'Nama belum sesuai.',
    email: 'Email belum sesuai.',
    password: 'Kata sandi belum sesuai.',
    password_confirmation: 'Konfirmasi kata sandi belum sesuai.',
    role: 'Peran belum sesuai.',
    organization: 'Organisasi belum sesuai.',
};

const FIELD_IDS = {
    name: 'auth-name',
    email: 'auth-email',
    password: 'auth-password',
    password_confirmation: 'auth-password-confirmation',
    organization: 'auth-organization',
};

function indonesianMessage(field, message) {
    if (!message) {
        return null;
    }

    if (DEFAULT_VALIDATION_MESSAGES[message]) {
        return DEFAULT_VALIDATION_MESSAGES[message];
    }

    // Anything else the server wrote itself (`Email ini sudah terdaftar.`) is
    // already Indonesian; an unlisted English default falls back per field.
    return message.startsWith('The ') ? FIELD_FALLBACKS[field] ?? 'Nilai belum sesuai.' : message;
}

function mapFieldErrors(errors) {
    return Object.fromEntries(
        Object.entries(errors).map(([field, messages]) => [field, indonesianMessage(field, messages?.[0])]),
    );
}

function requestFailureMessage(error) {
    if (error instanceof ApiError) {
        if (error.isUnauthenticated) {
            return 'Email atau kata sandi tidak cocok.';
        }
        if (error.status >= 500) {
            return 'Terjadi gangguan di server. Coba lagi sebentar lagi.';
        }

        return 'Permintaan tidak bisa diproses. Periksa kembali data yang diisi.';
    }

    return 'Tidak bisa menghubungi server. Periksa koneksi lalu coba lagi.';
}

function Field({ id, label, error, hint, children }) {
    return (
        <div>
            <label htmlFor={id} className="block font-label-md text-label-md text-on-surface-variant">
                {label}
            </label>
            <div className="mt-1.5">{children}</div>
            {hint && !error && <p className="mt-1 font-body-sm text-xs text-outline">{hint}</p>}
            {error && (
                <p id={`${id}-error`} className="mt-1 flex items-center gap-1 font-body-sm text-xs text-critical">
                    <span className="material-symbols-outlined text-[14px]" aria-hidden="true">
                        error
                    </span>
                    {error}
                </p>
            )}
        </div>
    );
}

export default function AuthPage({ mode = 'login' }) {
    const { login, register } = useAuth();
    const isRegister = mode === 'register';

    const [values, setValues] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'farmer',
        organization: '',
        remember: true,
    });
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const setField = (field) => (event) => {
        const value = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
        setValues((current) => ({ ...current, [field]: value }));
        setErrors((current) => (current[field] ? { ...current, [field]: null } : current));
        setFormError(null);
    };

    const fillDemoAccount = (email) => {
        setValues((current) => ({ ...current, email, password: DEMO_PASSWORD }));
        setErrors({});
        setFormError(null);
    };

    const handleSubmit = async (event) => {
        event.preventDefault();

        if (submitting) {
            return;
        }

        setSubmitting(true);
        setErrors({});
        setFormError(null);

        try {
            if (isRegister) {
                const payload = {
                    name: values.name.trim(),
                    email: values.email.trim(),
                    password: values.password,
                    password_confirmation: values.password_confirmation,
                    role: values.role,
                };

                if (values.role !== 'farmer' && values.organization.trim() !== '') {
                    payload.organization = values.organization.trim();
                }

                await register(payload);
            } else {
                await login({ email: values.email.trim(), password: values.password, remember: values.remember });
            }

            navigate('/app');
        } catch (error) {
            const fieldErrors = error instanceof ApiError ? error.errors : null;

            if (fieldErrors && Object.keys(fieldErrors).length > 0) {
                setErrors(mapFieldErrors(fieldErrors));
            } else {
                setFormError(requestFailureMessage(error));
            }
        } finally {
            setSubmitting(false);
        }
    };

    const inputClass = (field) =>
        `w-full rounded-lg border bg-surface-container-lowest px-3 py-2.5 font-body-md text-body-md text-on-surface outline-none transition-colors placeholder:text-outline focus:border-primary focus:ring-2 focus:ring-primary/20 ${
            errors[field] ? 'border-critical' : 'border-border-subtle'
        }`;

    const describe = (field) => (errors[field] ? `${FIELD_IDS[field]}-error` : undefined);

    return (
        <div className="min-h-screen bg-background text-on-surface antialiased lg:grid lg:grid-cols-[1fr_1.1fr]">
            {/* Platform panel */}
            <aside className="relative overflow-hidden bg-primary px-6 py-8 text-on-primary md:px-10 md:py-12 lg:flex lg:flex-col lg:justify-between">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -right-24 -top-32 h-[360px] w-[360px] rounded-full"
                    style={{ background: 'radial-gradient(circle, rgba(197,234,223,0.20), transparent 70%)' }}
                />

                <div className="relative">
                    <Link to="/" className="inline-flex items-center gap-2">
                        <span className="material-symbols-outlined text-[26px] text-primary-fixed" aria-hidden="true">
                            forest
                        </span>
                        <span className="font-headline-sm text-headline-sm font-bold text-on-primary">re:green</span>
                    </Link>

                    <h1 className="mt-8 max-w-md font-headline-xl text-headline-lg-mobile text-on-primary md:text-headline-xl">
                        Bukti lapangan untuk restorasi yang bisa dipertanggungjawabkan
                    </h1>
                    <p className="mt-4 max-w-md font-body-md text-body-md text-on-primary/80">
                        Satu alur dari foto lahan sampai laporan: deteksi keparahan kebakaran, rekomendasi tanaman, riwayat progres, dan estimasi
                        karbon.
                    </p>
                </div>

                <ul className="relative mt-10 hidden space-y-4 lg:block">
                    {[
                        { icon: 'add_a_photo', text: 'Unggah foto lahan dari ponsel atau drone' },
                        { icon: 'center_focus_strong', text: 'Deteksi keparahan kebakaran dan sisa vegetasi' },
                        { icon: 'rule', text: 'Rekomendasi tanaman sesuai tanah dan curah hujan' },
                        { icon: 'monitoring', text: 'Riwayat analisis sebagai bukti progres dan bahan laporan' },
                    ].map((item) => (
                        <li key={item.text} className="flex items-start gap-3">
                            <span className="material-symbols-outlined text-[20px] text-primary-fixed" aria-hidden="true">
                                {item.icon}
                            </span>
                            <span className="font-body-sm text-body-sm text-on-primary/80">{item.text}</span>
                        </li>
                    ))}
                </ul>
            </aside>

            {/* Form panel */}
            <main className="flex items-center justify-center px-4 py-10 md:px-10 md:py-14">
                <div className="w-full max-w-md">
                    <h2 className="font-headline-lg text-headline-lg-mobile text-on-surface md:text-headline-lg">
                        {isRegister ? 'Buat akun baru' : 'Masuk ke akun Anda'}
                    </h2>
                    <p className="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                        {isRegister
                            ? 'Pilih peran yang paling sesuai — peran menentukan data yang bisa Anda lihat.'
                            : 'Gunakan email dan kata sandi akun Anda.'}
                    </p>

                    {formError && (
                        <div
                            role="alert"
                            className="mt-5 flex items-start gap-2 rounded-lg border border-critical/30 bg-error-container px-3 py-2.5"
                        >
                            <span className="material-symbols-outlined text-[18px] text-on-error-container" aria-hidden="true">
                                error
                            </span>
                            <p className="font-body-sm text-body-sm text-on-error-container">{formError}</p>
                        </div>
                    )}

                    <form className="mt-6 space-y-4" onSubmit={handleSubmit} noValidate>
                        {isRegister && (
                            <Field id={FIELD_IDS.name} label="Nama lengkap" error={errors.name}>
                                <input
                                    id={FIELD_IDS.name}
                                    name="name"
                                    type="text"
                                    autoComplete="name"
                                    required
                                    value={values.name}
                                    onChange={setField('name')}
                                    aria-invalid={Boolean(errors.name)}
                                    aria-describedby={describe('name')}
                                    placeholder="Nama Anda"
                                    className={inputClass('name')}
                                />
                            </Field>
                        )}

                        <Field id={FIELD_IDS.email} label="Email" error={errors.email}>
                            <input
                                id={FIELD_IDS.email}
                                name="email"
                                type="email"
                                autoComplete="email"
                                required
                                value={values.email}
                                onChange={setField('email')}
                                aria-invalid={Boolean(errors.email)}
                                aria-describedby={describe('email')}
                                placeholder="nama@contoh.id"
                                className={inputClass('email')}
                            />
                        </Field>

                        <Field
                            id={FIELD_IDS.password}
                            label="Kata sandi"
                            error={errors.password}
                            hint={isRegister ? 'Minimal 8 karakter.' : undefined}
                        >
                            <div className="relative">
                                <input
                                    id={FIELD_IDS.password}
                                    name="password"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete={isRegister ? 'new-password' : 'current-password'}
                                    required
                                    value={values.password}
                                    onChange={setField('password')}
                                    aria-invalid={Boolean(errors.password)}
                                    aria-describedby={describe('password')}
                                    placeholder="••••••••"
                                    className={`${inputClass('password')} pr-11`}
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((current) => !current)}
                                    aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                                    className="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-outline transition-colors hover:text-primary"
                                >
                                    <span className="material-symbols-outlined text-[20px]" aria-hidden="true">
                                        {showPassword ? 'visibility_off' : 'visibility'}
                                    </span>
                                </button>
                            </div>
                        </Field>

                        {isRegister && (
                            <Field
                                id={FIELD_IDS.password_confirmation}
                                label="Ulangi kata sandi"
                                error={errors.password_confirmation}
                            >
                                <input
                                    id={FIELD_IDS.password_confirmation}
                                    name="password_confirmation"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="new-password"
                                    required
                                    value={values.password_confirmation}
                                    onChange={setField('password_confirmation')}
                                    aria-invalid={Boolean(errors.password_confirmation)}
                                    aria-describedby={describe('password_confirmation')}
                                    placeholder="••••••••"
                                    className={inputClass('password_confirmation')}
                                />
                            </Field>
                        )}

                        {isRegister && (
                            <fieldset>
                                <legend className="font-label-md text-label-md text-on-surface-variant">Peran akun</legend>
                                <div className="mt-2 space-y-2">
                                    {ROLES.map((role) => {
                                        const selected = values.role === role.id;

                                        return (
                                            <label
                                                key={role.id}
                                                className={`flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors ${
                                                    selected
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-border-subtle bg-surface-container-lowest hover:border-outline-variant'
                                                }`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="role"
                                                    value={role.id}
                                                    checked={selected}
                                                    onChange={setField('role')}
                                                    className="sr-only"
                                                />
                                                <span
                                                    className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${
                                                        selected ? 'bg-primary text-on-primary' : 'bg-surface-container text-outline'
                                                    }`}
                                                >
                                                    <span className="material-symbols-outlined text-[20px]" aria-hidden="true">
                                                        {ROLE_ICONS[role.id] ?? 'person'}
                                                    </span>
                                                </span>
                                                <span className="min-w-0">
                                                    <span className="block font-body-sm text-body-sm font-semibold text-on-surface">
                                                        {role.label}
                                                    </span>
                                                    <span className="mt-0.5 block font-body-sm text-xs text-on-surface-variant">
                                                        {role.description}
                                                    </span>
                                                </span>
                                                <span
                                                    className={`material-symbols-outlined ml-auto text-[20px] ${
                                                        selected ? 'text-primary' : 'text-outline-variant'
                                                    }`}
                                                    aria-hidden="true"
                                                >
                                                    {selected ? 'radio_button_checked' : 'radio_button_unchecked'}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                                {errors.role && (
                                    <p className="mt-1.5 font-body-sm text-xs text-critical">{errors.role}</p>
                                )}
                            </fieldset>
                        )}

                        {isRegister && values.role !== 'farmer' && (
                            <Field
                                id={FIELD_IDS.organization}
                                label="Organisasi (opsional)"
                                error={errors.organization}
                                hint="Nama instansi, lembaga, atau perusahaan."
                            >
                                <input
                                    id={FIELD_IDS.organization}
                                    name="organization"
                                    type="text"
                                    autoComplete="organization"
                                    value={values.organization}
                                    onChange={setField('organization')}
                                    aria-invalid={Boolean(errors.organization)}
                                    aria-describedby={describe('organization')}
                                    placeholder="Contoh: Dinas Lingkungan Hidup"
                                    className={inputClass('organization')}
                                />
                            </Field>
                        )}

                        {!isRegister && (
                            <label className="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    checked={values.remember}
                                    onChange={setField('remember')}
                                    className="control-checkbox"
                                />
                                Ingat saya di perangkat ini
                            </label>
                        )}

                        <button
                            type="submit"
                            disabled={submitting}
                            className="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 font-body-md text-body-md font-semibold text-on-primary transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {submitting && (
                                <span className="material-symbols-outlined animate-spin text-[18px]" aria-hidden="true">
                                    progress_activity
                                </span>
                            )}
                            {isRegister ? 'Daftar' : 'Masuk'}
                        </button>
                    </form>

                    <p className="mt-5 text-center font-body-sm text-body-sm text-on-surface-variant">
                        {isRegister ? 'Sudah punya akun? ' : 'Belum punya akun? '}
                        <Link
                            to={isRegister ? '/login' : '/register'}
                            className="font-semibold text-primary underline-offset-2 hover:underline"
                        >
                            {isRegister ? 'Masuk' : 'Daftar'}
                        </Link>
                    </p>

                    <div className="mt-8 rounded-xl border border-border-subtle bg-surface-container-low p-4">
                        <p className="flex items-center gap-2 font-label-md text-label-md uppercase tracking-wider text-outline">
                            <span className="material-symbols-outlined text-[16px]" aria-hidden="true">
                                key
                            </span>
                            Akun demo
                        </p>
                        <p className="mt-1.5 font-body-sm text-xs text-on-surface-variant">
                            {isRegister
                                ? 'Untuk mencoba platform, masuk memakai salah satu akun demo dengan kata sandi'
                                : 'Pilih akun untuk mengisi formulir otomatis. Kata sandi semuanya'}
                            <span className="mx-1 rounded bg-surface-container px-1.5 py-0.5 font-mono text-[11px] text-on-surface">
                                {DEMO_PASSWORD}
                            </span>
                        </p>

                        <ul className="mt-3 space-y-1.5">
                            {DEMO_ACCOUNTS.map((account) => (
                                <li key={account.email}>
                                    {isRegister ? (
                                        <div className="flex items-center justify-between gap-3 rounded-lg bg-surface-container-lowest px-3 py-2">
                                            <span className="truncate font-body-sm text-body-sm text-on-surface">{account.email}</span>
                                            <span className="shrink-0 font-body-sm text-xs text-outline">{account.label}</span>
                                        </div>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => fillDemoAccount(account.email)}
                                            className="flex w-full items-center justify-between gap-3 rounded-lg bg-surface-container-lowest px-3 py-2 text-left transition-colors hover:bg-surface-container"
                                        >
                                            <span className="truncate font-body-sm text-body-sm text-on-surface">{account.email}</span>
                                            <span className="shrink-0 font-body-sm text-xs text-outline">{account.label}</span>
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    );
}
