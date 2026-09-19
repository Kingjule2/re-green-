/**
 * The restoration recommendations of one analysis.
 *
 * The engine names each action with a machine key and describes the work in
 * English steps; both are rendered through `lib/i18n.js`, so the farmer reads
 * "Reboisasi" and "Tanam pohon asli" while the stored payload keeps the stable
 * keys a partner or an auditor reads.
 */
import React from 'react';
import { tAction, tPriority, tStep } from '@/react/lib/i18n';
import { Chip, Panel } from '@/react/components/farmer/primitives';

const PRIORITY_COLORS = { high: '#D7263D', medium: '#F3722C', low: '#52B788' };

function RecommendationCard({ item }) {
    return (
        <li className="rounded-xl border border-border-subtle bg-surface-container-low p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="font-body-md text-body-md font-semibold text-on-surface">{tAction(item.action, item.title)}</h3>
                <Chip
                    label={`Prioritas ${tPriority(item.priority)}`}
                    color={PRIORITY_COLORS[item.priority] ?? null}
                />
            </div>

            {item.recommended_actions?.length > 0 && (
                <ul className="mt-3 space-y-1.5">
                    {item.recommended_actions.map((step, index) => (
                        <li key={index} className="flex gap-2 font-body-sm text-body-sm text-on-surface-variant">
                            <span className="material-symbols-outlined text-[16px] text-primary" aria-hidden="true">
                                arrow_right
                            </span>
                            <span>{tStep(step)}</span>
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}

export default function RecommendationList({ recommendation, className = '' }) {
    const items = recommendation?.recommendations ?? [];

    if (items.length === 0) {
        return (
            <Panel title="Rekomendasi pemulihan" icon="task_alt" className={className}>
                <p className="font-body-sm text-body-sm text-on-surface-variant">
                    Belum ada rekomendasi untuk lahan ini. Rekomendasi disusun dari hasil analisis foto.
                </p>
            </Panel>
        );
    }

    return (
        <Panel
            title="Rekomendasi pemulihan"
            subtitle={`${items.length} tindakan, disusun dari kondisi lahan yang terdeteksi`}
            icon="task_alt"
            className={className}
            action={<Chip label={`Prioritas keseluruhan: ${tPriority(recommendation.priority)}`} color={PRIORITY_COLORS[recommendation.priority] ?? null} />}
        >
            <ul className="space-y-3">
                {items.map((item, index) => (
                    <RecommendationCard key={`${item.action}-${index}`} item={item} />
                ))}
            </ul>
        </Panel>
    );
}
