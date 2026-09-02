import React, { useRef, useEffect } from 'react';
import KpiCard from '../components/shared/KpiCard';
import { gsap } from '@/gsap';

export default function CommunityImpactPage() {
    const pageRef = useRef(null);

    useEffect(() => {
        if (pageRef.current) {
            gsap.fromTo(
                pageRef.current.querySelectorAll('.stagger-comm'),
                { opacity: 0, y: 15 },
                { opacity: 1, y: 0, duration: 0.5, stagger: 0.07, ease: 'power2.out' }
            );
        }
    }, []);

    const monthlyIncome = [
        { month: 'Jan', height: '25%', amount: '$12.4k' },
        { month: 'Feb', height: '35%', amount: '$18.2k' },
        { month: 'Mar', height: '30%', amount: '$15.8k' },
        { month: 'Apr', height: '45%', amount: '$24.1k' },
        { month: 'May', height: '40%', amount: '$21.0k' },
        { month: 'Jun', height: '60%', amount: '$31.5k' },
        { month: 'Jul', height: '55%', amount: '$29.0k' },
        { month: 'Aug', height: '70%', amount: '$38.2k' },
        { month: 'Sep', height: '65%', amount: '$34.8k' },
        { month: 'Oct', height: '80%', amount: '$42.3k' },
        { month: 'Nov', height: '75%', amount: '$39.6k' },
        { month: 'Dec', height: '90%', amount: '$48.2k' },
    ];

    const team = [
        {
            name: 'Siti Aminah',
            role: 'Sector A Lead • Seedlings & Nursery',
            members: '12 Members',
            avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuBaIongOwwzFj9N4ilUsiStk985cZf4-1hWoDg6odolq3B_Kw1coDrmERjqwEOhKGEp-eQ7X-HHGUsYLCBEGbAJ7t7HqxW81UjCuI3WWyiRzg87Vt-ZpdXjMlTcfr-Lx8KlbhiHItBBFrOGZGZR-g3kCSVzFAJL3FnmvHuY5eWJLQbgVhU6x7na-lEdDrFc-K2GutfORvsv_JB414sMf13upO_bG6cISJWJTVzMvFXQ1lGSizp7B5eR',
        },
        {
            name: 'Budi Santoso',
            role: 'Sector B Lead • Planting & Hydrology',
            members: '18 Members',
            avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuA0CUoPVjsNeoaCMCViFrxM-pR1ayOE61cY9jfrylUXg__MqXtVghUBFvpe1GMGuiR_hx5E4ctVDggqk0jAwDkMceX9DN9x9dPaaaCeyalKzTy8YO8jw-MQP-us6DxEEilTJimK83zpTtZgy0X1xDMdbwBYHGckKQkgPgMedyCXLzrfbq7OWSd85YJ1Z77fw2Zj1nE97pfwFNc-qQ0VQ1bc5L-ZaULvzuV59BsJnwPxKAaipCwWILcK',
        },
        {
            name: 'Rizki Aditya',
            role: 'Sector C Lead • Peatland Sentinel',
            members: '8 Members',
            avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuAd1_Ofw4RynsOI5stQAwrLSxRQpfY4vn8Q2reh41sEIueN2xakegwHO8W3T3djj9JZ7gyJaJHcUJ6VxtFyj9JFa-4csPEexad6RJJM--lVIBFmg4416CfJl-lly15L0NG-0xzMshIlECEa4AniNZYjNJxohLrrSWE4kvtYRoGVZftX03p6rdm7kN_ArPrIgR3GVsWvw7DLfqqDLeuY1zR1eThjeJdonYTwF8vocl_FO3H71Nl8D7HW',
        },
    ];

    const timeline = [
        {
            date: 'Nov 15, 2026',
            title: 'Agroforestry & Beekeeping Workshop',
            desc: '45 attendees from local villages. Practical training on non-timber forest honey harvesting.',
            active: true,
        },
        {
            date: 'Oct 28, 2026',
            title: 'Participatory Boundary Mapping',
            desc: 'Community consensus reached on conservation buffer corridors in Sector B riparian area.',
            active: false,
        },
        {
            date: 'Oct 10, 2026',
            title: 'Indigenous Seedling Distribution Phase 2',
            desc: 'Distributed 10,000 mixed saplings (Shorea & Melaleuca) to decentralized community nurseries.',
            active: false,
        },
    ];

    return (
        <div ref={pageRef} className="space-y-8 animate-in fade-in duration-300">
            {/* Header */}
            <div className="stagger-comm pb-2">
                <h2 className="font-headline-xl text-3xl md:text-4xl font-bold text-primary dark:text-primary-fixed">
                    Community & Social Livelihood Impact
                </h2>
                <p className="font-body-md text-sm md:text-base text-on-surface-variant max-w-2xl mt-1">
                    Direct local economic empowerment, fair wage distribution, indigenous nursery cooperatives, and participatory stewardship.
                </p>
            </div>

            {/* KPI Row */}
            <div className="stagger-comm grid grid-cols-1 sm:grid-cols-3 gap-6">
                <KpiCard
                    title="Active Community Stewards"
                    value="84"
                    unit="people"
                    trend="+16 enrolled this quarter"
                    isPositive={true}
                    icon="groups"
                    trendIcon="person_add"
                    delay={0.1}
                />
                <KpiCard
                    title="Total Direct Wages Disbursed"
                    value="48.2"
                    unit="k USD"
                    trend="100% fair-wage verified"
                    isPositive={true}
                    icon="payments"
                    trendIcon="verified"
                    delay={0.2}
                />
                <KpiCard
                    title="Saplings Distributed"
                    value="10,000"
                    unit="units"
                    trend="Phase 2 Completed"
                    isPositive={true}
                    icon="yard"
                    trendIcon="check_circle"
                    delay={0.3}
                />
            </div>

            {/* Income Chart & Details */}
            <div className="stagger-comm bg-surface-container-lowest rounded-xl border border-border-subtle p-6 card-shadow flex flex-col">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h3 className="font-headline-md text-lg font-bold text-on-surface">
                            Community Direct Income Generation
                        </h3>
                        <p className="font-body-sm text-xs text-on-surface-variant mt-0.5">
                            Monthly aggregate wages and non-timber product yields (12 Months)
                        </p>
                    </div>
                    <div className="bg-surface-container-low px-3 py-1.5 rounded-full border border-border-subtle flex items-center gap-2 text-xs font-semibold text-primary">
                        <span className="w-2 h-2 rounded-full bg-primary-container"></span>
                        <span>USD Currency ($)</span>
                    </div>
                </div>

                {/* Bar Chart Container */}
                <div className="h-64 flex items-end justify-between gap-2 sm:gap-3 border-b border-border-subtle pb-4 pt-6 px-2">
                    {monthlyIncome.map((item, idx) => (
                        <div
                            key={idx}
                            className="flex-1 flex flex-col justify-end items-center group relative h-full cursor-pointer"
                        >
                            {/* Hover Tooltip */}
                            <div className="absolute -top-7 opacity-0 group-hover:opacity-100 transition-opacity bg-charcoal text-white text-[10px] px-2 py-0.5 rounded font-mono pointer-events-none whitespace-nowrap z-10">
                                {item.amount}
                            </div>

                            {/* Bar */}
                            <div
                                className="w-full max-w-[36px] bg-primary-container/70 group-hover:bg-primary rounded-t transition-all duration-300"
                                style={{ height: item.height }}
                            />

                            {/* Month Label */}
                            <span className="font-label-md text-[10px] text-outline group-hover:text-primary group-hover:font-bold mt-2">
                                {item.month}
                            </span>
                        </div>
                    ))}
                </div>
            </div>

            {/* Lower Grid: Team Management & Activity Timeline */}
            <div className="stagger-comm grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Team Management */}
                <div className="bg-surface-container-lowest rounded-xl border border-border-subtle p-6 card-shadow flex flex-col justify-between">
                    <div>
                        <div className="flex justify-between items-center mb-4 pb-2 border-b border-border-subtle">
                            <h3 className="font-headline-md text-base font-bold text-on-surface">
                                Local Leadership & Sector Stewards
                            </h3>
                            <button
                                onClick={() => alert('View all community coordinators')}
                                className="text-primary font-label-md text-xs font-bold hover:underline flex items-center"
                            >
                                View All <span className="material-symbols-outlined text-[14px] ml-0.5">arrow_forward</span>
                            </button>
                        </div>

                        <div className="space-y-3">
                            {team.map((member, i) => (
                                <div
                                    key={i}
                                    className="flex items-center justify-between p-3 border border-border-subtle rounded-lg hover:border-primary transition-colors cursor-pointer group bg-surface-bright"
                                >
                                    <div className="flex items-center gap-3">
                                        <img
                                            src={member.avatar}
                                            alt={member.name}
                                            className="w-10 h-10 rounded-full object-cover border border-border-subtle"
                                        />
                                        <div>
                                            <div className="font-label-md text-xs font-bold text-on-surface group-hover:text-primary">
                                                {member.name}
                                            </div>
                                            <div className="font-body-sm text-[11px] text-outline">
                                                {member.role}
                                            </div>
                                        </div>
                                    </div>
                                    <span className="px-2.5 py-1 bg-surface-container text-on-surface font-label-md text-[10px] font-bold rounded-full">
                                        {member.members}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Timeline */}
                <div className="bg-surface-container-lowest rounded-xl border border-border-subtle p-6 card-shadow flex flex-col justify-between">
                    <div>
                        <div className="flex justify-between items-center mb-4 pb-2 border-b border-border-subtle">
                            <h3 className="font-headline-md text-base font-bold text-on-surface">
                                Recent Community Field Activities
                            </h3>
                            <span className="text-xs text-outline font-semibold">Q3/Q4 2026</span>
                        </div>

                        <div className="relative border-l-2 border-border-subtle ml-3 pl-5 space-y-5 pt-1 text-xs">
                            {timeline.map((event, i) => (
                                <div key={i} className="relative">
                                    <div
                                        className={`absolute -left-[27px] top-0.5 w-3.5 h-3.5 rounded-full border-2 border-surface-container-lowest ${
                                            event.active ? 'bg-primary-container ring-2 ring-primary/20' : 'bg-surface-container-high'
                                        }`}
                                    />
                                    <span className="font-label-md text-[10px] text-outline font-mono block">
                                        {event.date}
                                    </span>
                                    <h4 className="font-bold text-on-surface text-xs mt-0.5">{event.title}</h4>
                                    <p className="font-body-sm text-[11px] text-on-surface-variant mt-0.5">
                                        {event.desc}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
