/**
 * The planting guide for the crop the farmer picked.
 *
 * The guide text comes from the crop catalog and is already Indonesian; the
 * card only lays it out — what to do first, how far apart, how much water, and
 * what has to be maintained afterwards. Until a crop is selected the card says
 * so and points at the ranked list instead of showing an empty shell.
 */
import React from 'react';
import { formatDate } from '@/react/lib/format';
import { Panel } from '@/react/components/farmer/primitives';
import { tMarketValue } from '@/react/components/farmer/labels';

function GuideRow({ label, value }) {
    if (!value) {
        return null;
    }

    return (
        <div className="rounded-lg border border-border-subtle bg-surface-container-low px-3 py-2">
            <p className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">{label}</p>
            <p className="font-body-sm text-body-sm text-on-surface">{value}</p>
        </div>
    );
}

export default function PlantingGuideCard({ plantingPlan, className = '' }) {
    if (!plantingPlan?.planting_guide) {
        return (
            <Panel title="Panduan tanam" icon="menu_book" className={className}>
                <p className="font-body-sm text-body-sm text-on-surface-variant">
                    Belum ada tanaman yang dipilih untuk lahan ini. Pilih satu tanaman dari daftar rekomendasi, lalu panduan tanamnya akan muncul di sini.
                </p>
            </Panel>
        );
    }

    const { crop, planting_guide: guide, selected_at: selectedAt } = plantingPlan;

    return (
        <Panel
            title="Panduan tanam"
            subtitle={selectedAt ? `Dipilih ${formatDate(selectedAt)}` : null}
            icon="menu_book"
            className={className}
        >
            <div className="flex flex-wrap items-center gap-3 rounded-xl border border-border-subtle bg-surface-container-low px-4 py-3">
                <span className="text-[26px]" aria-hidden="true">
                    {crop.icon}
                </span>
                <div>
                    <p className="font-body-md text-body-md font-semibold text-on-surface">{crop.name}</p>
                    <p className="font-body-sm text-xs text-on-surface-variant">
                        {crop.category}
                        {crop.market_value ? ` · nilai pasar ${tMarketValue(crop.market_value)}` : ''}
                    </p>
                </div>
            </div>

            {guide.summary && <p className="mt-4 font-body-sm text-body-sm text-on-surface">{guide.summary}</p>}

            {guide.steps?.length > 0 && (
                <div className="mt-4">
                    <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Langkah kerja</h3>
                    <ol className="mt-2 space-y-2">
                        {guide.steps.map((step, index) => (
                            <li key={index} className="flex gap-3 font-body-sm text-body-sm text-on-surface">
                                <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary font-label-md text-[11px] text-on-primary">
                                    {index + 1}
                                </span>
                                <span>{step}</span>
                            </li>
                        ))}
                    </ol>
                </div>
            )}

            <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <GuideRow label="Jarak tanam" value={guide.spacing} />
                <GuideRow label="Kebutuhan bibit" value={guide.seedlings_per_ha} />
                <GuideRow label="Waktu sampai tajuk menutup" value={guide.time_to_canopy} />
                <GuideRow label="Kebutuhan air" value={guide.water_need} />
            </div>

            {guide.maintenance?.length > 0 && (
                <div className="mt-4">
                    <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Perawatan rutin</h3>
                    <ul className="mt-2 space-y-1.5">
                        {guide.maintenance.map((item, index) => (
                            <li key={index} className="flex gap-2 font-body-sm text-body-sm text-on-surface-variant">
                                <span className="material-symbols-outlined text-[16px] text-success" aria-hidden="true">
                                    check_circle
                                </span>
                                <span>{item}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {guide.notes && (
                <p className="mt-4 rounded-lg border border-border-subtle bg-surface-container-low px-3 py-2 font-body-sm text-body-sm text-on-surface-variant">
                    {guide.notes}
                </p>
            )}
        </Panel>
    );
}
