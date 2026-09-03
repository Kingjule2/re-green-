import React, { useRef, useEffect } from 'react';
import { gsap } from '@/gsap';

const TREND_TONES = {
    positive: 'text-success',
    negative: 'text-critical',
    neutral: 'text-on-surface-variant',
};

export default function KpiCard({
    title,
    value,
    unit = '',
    trend = null,
    isPositive = true,
    trendTone = null,
    icon = 'landscape',
    trendIcon = 'trending_up',
    className = '',
    delay = 0,
}) {
    const tone = trendTone ?? (isPositive ? 'positive' : 'negative');
    const numberRef = useRef(null);
    const cardRef = useRef(null);

    useEffect(() => {
        // Numeric counter animation if value is numeric. Thousands separators are
        // stripped first, otherwise parseFloat('2,450') yields 2.
        const numericVal = parseFloat(String(value).replace(/,/g, ''));
        if (!isNaN(numericVal) && numberRef.current) {
            const isFloat = String(value).includes('.');
            const obj = { val: 0 };

            gsap.to(obj, {
                val: numericVal,
                duration: 1.2,
                delay: delay,
                ease: 'power2.out',
                onUpdate: () => {
                    if (numberRef.current) {
                        numberRef.current.textContent = isFloat
                            ? obj.val.toFixed(1)
                            : Math.round(obj.val).toLocaleString();
                    }
                },
            });
        }
    }, [value, delay]);

    return (
        <div
            ref={cardRef}
            className={`bg-surface-container-lowest border border-border-subtle rounded-xl p-6 card-shadow flex flex-col justify-between transition-all duration-200 hover:shadow-md hover:border-primary/30 ${className}`}
        >
            <div className="flex justify-between items-start mb-4">
                <h3 className="font-label-md text-label-md text-on-surface-variant font-medium">
                    {title}
                </h3>
                <span className="material-symbols-outlined text-outline text-[22px]" aria-hidden="true">
                    {icon}
                </span>
            </div>

            <div className="flex items-baseline gap-2 mb-2">
                <span ref={numberRef} className="font-stats-lg text-stats-lg text-primary dark:text-primary-fixed">
                    {value}
                </span>
                {unit && (
                    <span className="font-body-md text-body-md text-on-surface-variant font-medium">
                        {unit}
                    </span>
                )}
            </div>

            {trend && (
                <div
                    className={`flex items-center gap-1 font-label-md text-label-md ${
                        TREND_TONES[tone] ?? TREND_TONES.positive
                    }`}
                >
                    <span className="material-symbols-outlined text-[16px]" aria-hidden="true">
                        {trendIcon}
                    </span>
                    <span>{trend}</span>
                </div>
            )}
        </div>
    );
}
