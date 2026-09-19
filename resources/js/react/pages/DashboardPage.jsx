/**
 * The dashboard route renders whichever dashboard the account is entitled to:
 * a farmer's own lands, or the aggregate picture across every monitored land.
 */
import React from 'react';
import { useAuth } from '@/react/contexts/AuthContext';
import FarmerDashboardPage from '@/react/pages/farmer/FarmerDashboardPage';
import InstitutionDashboardPage from '@/react/pages/institution/InstitutionDashboardPage';

export default function DashboardPage() {
    const { viewsAllLands } = useAuth();

    return viewsAllLands ? <InstitutionDashboardPage /> : <FarmerDashboardPage />;
}
