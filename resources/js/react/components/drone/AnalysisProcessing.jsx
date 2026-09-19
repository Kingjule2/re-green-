import React, { useEffect, useRef, useState } from 'react';
import { gsap } from '@/gsap';

const PIPELINE_STEPS = [
    { label: 'Uploading Image', icon: 'cloud_upload' },
    { label: 'Image Preprocessing', icon: 'image' },
    { label: 'Vegetation Detection', icon: 'forest' },
    { label: 'Land Classification', icon: 'category' },
    { label: 'Degradation Detection', icon: 'warning' },
    { label: 'Spatial Analysis', icon: 'map' },
    { label: 'Restoration Recommendation', icon: 'psychiatry' },
    { label: 'Generating Report', icon: 'description' },
];

/**
 * Animated processing screen shown while the backend pipeline runs. Progress is
 * indicative (the real signal is the polled status), so it eases toward ~92%
 * and holds until the parent transitions away on completion.
 */
export default function AnalysisProcessing({ statusMessage }) {
    const [progress, setProgress] = useState(6);
    const [stepIndex, setStepIndex] = useState(0);
    const barRef = useRef(null);

    useEffect(() => {
        const timer = setInterval(() => {
            setProgress((current) => {
                if (current >= 92) {
                    return current;
                }
                // Ease-out: slow down as we approach the cap.
                const next = current + Math.max(1, (92 - current) * 0.08);
                return Math.min(92, next);
            });
        }, 400);
        return () => clearInterval(timer);
    }, []);

    useEffect(() => {
        setStepIndex(Math.min(PIPELINE_STEPS.length - 1, Math.floor((progress / 100) * PIPELINE_STEPS.length)));
    }, [progress]);

    useEffect(() => {
        if (barRef.current) {
            gsap.to(barRef.current, { width: `${progress}%`, duration: 0.4, ease: 'power1.out' });
        }
    }, [progress]);

    return (
        <div className="mx-auto max-w-2xl rounded-2xl border border-border-subtle bg-surface-container-lowest p-8 md:p-10 card-shadow">
            <div className="text-center">
                <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/5 text-primary dark:text-primary-fixed">
                    <span className="material-symbols-outlined animate-pulse text-[34px]" aria-hidden="true">
                        neurology
                    </span>
                </span>
                <p className="mt-4 font-label-md text-[11px] uppercase tracking-[0.2em] text-outline">AI Land Analysis</p>
                <h3 className="mt-1 font-headline-md text-headline-md font-bold text-on-surface">
                    {PIPELINE_STEPS[stepIndex].label}…
                </h3>
                <p className="mt-1 font-body-sm text-sm text-on-surface-variant">
                    {statusMessage || 'Analyzing vegetation patterns and land condition…'}
                </p>
            </div>

            {/* Progress bar */}
            <div className="mt-6" role="progressbar" aria-valuenow={Math.round(progress)} aria-valuemin={0} aria-valuemax={100}>
                <div className="h-2.5 w-full overflow-hidden rounded-full bg-surface-container-highest">
                    <div ref={barRef} className="h-full rounded-full bg-primary-container" style={{ width: `${progress}%` }} />
                </div>
                <div className="mt-2 flex items-center justify-between font-label-md text-[11px] text-outline">
                    <span>{Math.round(progress)}%</span>
                    <span>Estimated time ~30 seconds</span>
                </div>
            </div>

            {/* Pipeline steps */}
            <ol className="mt-8 grid grid-cols-1 gap-2 sm:grid-cols-2">
                {PIPELINE_STEPS.map((step, index) => {
                    const done = index < stepIndex;
                    const active = index === stepIndex;
                    return (
                        <li
                            key={step.label}
                            className={`flex items-center gap-3 rounded-lg border px-3 py-2 transition-colors ${
                                active
                                    ? 'border-primary/30 bg-primary/5'
                                    : done
                                      ? 'border-transparent bg-surface-container-low'
                                      : 'border-transparent'
                            }`}
                        >
                            <span
                                className={`material-symbols-outlined text-[18px] ${
                                    done ? 'text-success' : active ? 'text-primary dark:text-primary-fixed' : 'text-outline'
                                } ${active ? 'animate-pulse' : ''}`}
                                aria-hidden="true"
                            >
                                {done ? 'check_circle' : step.icon}
                            </span>
                            <span
                                className={`font-body-sm text-xs ${
                                    active ? 'font-semibold text-on-surface' : 'text-on-surface-variant'
                                }`}
                            >
                                {step.label}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
