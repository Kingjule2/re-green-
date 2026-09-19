/**
 * The signed-in shell: navigation on the left, the page on the right.
 *
 * Which navigation appears depends on the account, not on the page: a farmer
 * manages their own lands, while a pemda/NGO or corporate account reads the
 * aggregate and exports reports.
 */
import React, { useEffect, useRef, useState } from 'react';
import { gsap } from '@/gsap';
import { Link, navigate, useRouter } from '@/react/lib/router';
import { useAuth } from '@/react/contexts/AuthContext';

const FARMER_NAV = [
    { to: '/app', label: 'Dasbor', icon: 'dashboard', exact: true },
    { to: '/app/lahan', label: 'Lahan Saya', icon: 'landscape' },
    { to: '/app/peta', label: 'Peta Lahan', icon: 'map' },
    { to: '/app/laporan', label: 'Laporan', icon: 'description' },
];

const AGGREGATE_NAV = [
    { to: '/app', label: 'Dasbor Agregat', icon: 'dashboard', exact: true },
    { to: '/app/lahan', label: 'Lahan Terpantau', icon: 'landscape' },
    { to: '/app/peta', label: 'Peta Sebaran', icon: 'map' },
    { to: '/app/laporan', label: 'Laporan & Ekspor', icon: 'description' },
    { to: '/app/karbon', label: 'Portofolio Karbon', icon: 'eco' },
];

const ROLE_TONES = {
    farmer: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    institution: 'bg-sky-50 text-sky-800 border-sky-200',
    corporate: 'bg-violet-50 text-violet-800 border-violet-200',
};

export default function AppShell({ title, subtitle = null, actions = null, children }) {
    const { user, logout, viewsAllLands } = useAuth();
    const { path } = useRouter();
    const [mobileOpen, setMobileOpen] = useState(false);
    const contentRef = useRef(null);
    const nav = viewsAllLands ? AGGREGATE_NAV : FARMER_NAV;

    useEffect(() => {
        setMobileOpen(false);
    }, [path]);

    useEffect(() => {
        if (!contentRef.current) {
            return;
        }

        gsap.fromTo(
            contentRef.current,
            { opacity: 0, y: 10 },
            { opacity: 1, y: 0, duration: 0.32, ease: 'power2.out', clearProps: 'transform' },
        );
    }, [path]);

    const isActive = (item) =>
        item.exact ? path === item.to : path === item.to || path.startsWith(`${item.to}/`);

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    const navigation = (
        <nav className="flex-1 space-y-1 px-3" aria-label="Navigasi utama">
            {nav.map((item) => (
                <Link
                    key={item.to}
                    to={item.to}
                    className={`flex items-center gap-3 rounded-lg px-3 py-2.5 font-body-sm text-body-sm transition-colors ${
                        isActive(item)
                            ? 'bg-primary text-on-primary font-semibold'
                            : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface'
                    }`}
                    aria-current={isActive(item) ? 'page' : undefined}
                >
                    <span className="material-symbols-outlined text-[20px]" aria-hidden="true">
                        {item.icon}
                    </span>
                    {item.label}
                </Link>
            ))}
        </nav>
    );

    return (
        <div className="flex min-h-screen flex-col bg-background text-on-surface antialiased md:flex-row">
            {/* Desktop sidebar */}
            <aside className="hidden w-sidebar-width shrink-0 flex-col border-r border-border-subtle bg-surface-container-lowest md:flex">
                <Link to="/" className="flex items-center gap-2 px-5 py-5">
                    <span className="material-symbols-outlined text-[26px] text-primary" aria-hidden="true">
                        forest
                    </span>
                    <span className="font-headline-sm text-headline-sm font-bold text-primary">re:green</span>
                </Link>
                {navigation}
                <div className="mt-auto border-t border-border-subtle p-3">
                    <div className="flex items-center gap-3 rounded-lg px-2 py-2">
                        <span className="flex h-9 w-9 items-center justify-center rounded-full bg-primary-container font-label-md text-label-md text-on-primary">
                            {user?.initials ?? 'RG'}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-body-sm text-body-sm font-medium text-on-surface">{user?.name}</p>
                            <p className="truncate font-body-sm text-xs text-on-surface-variant">
                                {user?.organization ?? user?.role_short_label}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={handleLogout}
                        className="mt-1 flex w-full items-center gap-2 rounded-lg px-2 py-2 font-body-sm text-body-sm text-on-surface-variant transition-colors hover:bg-surface-container hover:text-critical"
                    >
                        <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                            logout
                        </span>
                        Keluar
                    </button>
                </div>
            </aside>

            {/* Mobile drawer */}
            {mobileOpen && (
                <div className="fixed inset-0 z-[900] flex md:hidden">
                    <button
                        type="button"
                        aria-label="Tutup navigasi"
                        className="absolute inset-0 bg-charcoal/40"
                        onClick={() => setMobileOpen(false)}
                    />
                    <div className="relative flex h-full w-64 flex-col bg-surface-container-lowest">
                        <div className="flex items-center justify-between px-4 py-4">
                            <span className="font-headline-sm text-headline-sm font-bold text-primary">re:green</span>
                            <button type="button" onClick={() => setMobileOpen(false)} aria-label="Tutup navigasi">
                                <span className="material-symbols-outlined" aria-hidden="true">
                                    close
                                </span>
                            </button>
                        </div>
                        {navigation}
                    </div>
                </div>
            )}

            <div className="flex min-h-screen flex-1 flex-col">
                <header className="sticky top-0 z-[500] flex items-center gap-3 border-b border-border-subtle bg-surface/95 px-4 py-3 backdrop-blur md:px-margin-desktop">
                    <button
                        type="button"
                        className="md:hidden"
                        onClick={() => setMobileOpen(true)}
                        aria-label="Buka navigasi"
                    >
                        <span className="material-symbols-outlined" aria-hidden="true">
                            menu
                        </span>
                    </button>

                    <div className="min-w-0 flex-1">
                        <h1 className="truncate font-headline-sm text-headline-sm text-on-surface">{title}</h1>
                        {subtitle && <p className="truncate font-body-sm text-xs text-on-surface-variant">{subtitle}</p>}
                    </div>

                    {actions}

                    {user && (
                        <span
                            className={`hidden shrink-0 rounded-full border px-3 py-1 font-label-md text-[11px] uppercase tracking-wider sm:inline-flex ${
                                ROLE_TONES[user.role] ?? ROLE_TONES.farmer
                            }`}
                        >
                            {user.role_short_label}
                        </span>
                    )}
                </header>

                <main className="mx-auto w-full max-w-max-width flex-1 px-4 py-6 md:px-margin-desktop">
                    <div ref={contentRef}>{children}</div>
                </main>
            </div>
        </div>
    );
}
