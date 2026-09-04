import React, { useState, useEffect, useRef } from 'react';
import Sidebar from './components/layout/Sidebar';
import TopBar from './components/layout/TopBar';
import OverviewPage from './pages/OverviewPage';
import MonitoringPage from './pages/MonitoringPage';
import LandIntelligencePage from './pages/LandIntelligencePage';
import DroneAnalysisPage from './pages/DroneAnalysisPage';
import RestorationProjectsPage from './pages/RestorationProjectsPage';
import ImpactReportsPage from './pages/ImpactReportsPage';
import CommunityImpactPage from './pages/CommunityImpactPage';
import { gsap } from '@/gsap';

const PAGE_TITLES = {
    overview: 'Overview Dashboard',
    'restoration-projects': 'Restoration Projects',
    'land-intelligence': 'Land Intelligence & GIS',
    'drone-analysis': 'Drone Land Analysis',
    monitoring: 'Live Monitoring',
    community: 'Community & Social Impact',
    'impact-reports': 'Impact & Carbon Reports',
};

export default function RegreenApp() {
    const [activePage, setActivePage] = useState('overview');
    const [selectedProject, setSelectedProject] = useState('kalimantan');
    const [darkMode, setDarkMode] = useState(false);
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const contentAreaRef = useRef(null);

    // Toggle dark mode class on root html
    useEffect(() => {
        if (darkMode) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }, [darkMode]);

    // Animate page transitions with GSAP
    const handleNavigate = (pageId) => {
        if (pageId === activePage) return;

        if (contentAreaRef.current) {
            gsap.to(contentAreaRef.current, {
                opacity: 0,
                y: 8,
                duration: 0.15,
                ease: 'power1.in',
                onComplete: () => {
                    setActivePage(pageId);
                    window.scrollTo({ top: 0, behavior: 'instant' });
                    gsap.fromTo(
                        contentAreaRef.current,
                        { opacity: 0, y: 12 },
                        { opacity: 1, y: 0, duration: 0.35, ease: 'power2.out' }
                    );
                },
            });
        } else {
            setActivePage(pageId);
        }
    };

    const renderPage = () => {
        switch (activePage) {
            case 'overview':
                return <OverviewPage onNavigate={handleNavigate} />;
            case 'monitoring':
                return <MonitoringPage />;
            case 'land-intelligence':
                return <LandIntelligencePage />;
            case 'drone-analysis':
                return <DroneAnalysisPage />;
            case 'restoration-projects':
                return <RestorationProjectsPage />;
            case 'impact-reports':
                return <ImpactReportsPage />;
            case 'community':
                return <CommunityImpactPage />;
            default:
                return <OverviewPage onNavigate={handleNavigate} />;
        }
    };

    return (
        <div className="bg-background text-on-surface font-body-md min-h-screen antialiased flex flex-col md:flex-row selection:bg-primary/20">
            {/* Navigation Sidebar */}
            <Sidebar
                activePage={activePage}
                onNavigate={handleNavigate}
                mobileOpen={mobileNavOpen}
                onCloseMobile={() => setMobileNavOpen(false)}
            />

            {/* Main Content Area */}
            <div className="flex-1 md:ml-[260px] min-h-screen flex flex-col w-full">
                {/* Top Sticky Bar */}
                <TopBar
                    pageTitle={PAGE_TITLES[activePage] || 'Dashboard'}
                    onOpenMobile={() => setMobileNavOpen(true)}
                    selectedProject={selectedProject}
                    onSelectProject={setSelectedProject}
                    darkMode={darkMode}
                    onToggleDarkMode={() => setDarkMode(!darkMode)}
                />

                {/* Main Scrollable Canvas */}
                <main className="flex-1 p-4 md:p-margin-desktop overflow-x-hidden max-w-max-width mx-auto w-full">
                    <div ref={contentAreaRef}>
                        {renderPage()}
                    </div>
                </main>
            </div>
        </div>
    );
}
