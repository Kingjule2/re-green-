/**
 * The app: routes, the signed-in shell, and the guards between them.
 *
 * Public pages (landing, login, register) render on their own; everything under
 * `/app` requires a session and shows up inside {@see AppShell}. The route table
 * is the single list of pages this application has.
 */
import React, { useEffect } from 'react';
import AppShell from '@/react/components/layout/AppShell';
import { AuthProvider, useAuth } from '@/react/contexts/AuthContext';
import { RouterProvider, matchPath, navigate, useRouter } from '@/react/lib/router';
import AnalysisDetailPage from '@/react/pages/farmer/AnalysisDetailPage';
import LandDetailPage from '@/react/pages/farmer/LandDetailPage';
import LandsPage from '@/react/pages/farmer/LandsPage';
import AuthPage from '@/react/pages/AuthPage';
import DashboardPage from '@/react/pages/DashboardPage';
import CarbonPortfolioPage from '@/react/pages/institution/CarbonPortfolioPage';
import MapPage from '@/react/pages/institution/MapPage';
import ReportsPage from '@/react/pages/institution/ReportsPage';
import LandingPage from '@/react/pages/LandingPage';

const ROUTES = [
    { pattern: '/', page: LandingPage, public: true },
    { pattern: '/login', page: () => <AuthPage mode="login" />, public: true },
    { pattern: '/register', page: () => <AuthPage mode="register" />, public: true },
    { pattern: '/app', page: DashboardPage, title: 'Dasbor' },
    { pattern: '/app/lahan', page: LandsPage, title: 'Lahan' },
    { pattern: '/app/lahan/:id', page: LandDetailPage, title: 'Detail Lahan' },
    { pattern: '/app/analisis/:id', page: AnalysisDetailPage, title: 'Hasil Analisis' },
    { pattern: '/app/peta', page: MapPage, title: 'Peta Sebaran Lahan' },
    { pattern: '/app/laporan', page: ReportsPage, title: 'Laporan' },
    { pattern: '/app/karbon', page: CarbonPortfolioPage, title: 'Portofolio Karbon' },
];

function NotFound() {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-3 bg-background px-6 text-center">
            <span className="material-symbols-outlined text-[40px] text-outline" aria-hidden="true">
                explore_off
            </span>
            <h1 className="font-headline-md text-headline-md text-on-surface">Halaman tidak ditemukan</h1>
            <p className="font-body-sm text-body-sm text-on-surface-variant">
                Alamat yang dibuka tidak ada di platform ini.
            </p>
            <a
                href="/"
                onClick={(event) => {
                    event.preventDefault();
                    navigate('/');
                }}
                className="mt-2 rounded-lg bg-primary px-4 py-2 font-body-sm text-body-sm font-semibold text-on-primary"
            >
                Kembali ke beranda
            </a>
        </div>
    );
}

function Routes() {
    const { path } = useRouter();
    const { user, loading } = useAuth();

    const route = ROUTES.find((candidate) => matchPath(candidate.pattern, path) !== null);
    const redirectToLogin = Boolean(route && !route.public && !loading && !user);

    useEffect(() => {
        if (redirectToLogin) {
            navigate('/login', { replace: true });
        }
    }, [redirectToLogin]);

    if (!route) {
        return <NotFound />;
    }

    const Page = route.page;

    if (route.public) {
        return <Page />;
    }

    // The session is still being checked: showing the login form now would flash
    // for a user who is already signed in.
    if (loading || redirectToLogin) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-background">
                <span className="font-body-sm text-body-sm text-on-surface-variant">Memuat…</span>
            </div>
        );
    }

    return (
        <AppShell title={route.title ?? 're:green'}>
            <Page />
        </AppShell>
    );
}

export default function RegreenApp() {
    return (
        <RouterProvider>
            <AuthProvider>
                <Routes />
            </AuthProvider>
        </RouterProvider>
    );
}
