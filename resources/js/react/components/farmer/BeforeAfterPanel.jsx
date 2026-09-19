/**
 * Before / after: the first monitoring period next to the latest one, with the
 * change between them.
 *
 * The two sides come from `progress.comparison`, which carries the image URL of
 * each period. When a period has no stored image the panel asks the analysis
 * endpoint for that period and draws the model's segmentation grid instead, so
 * the farmer still sees where the vegetation and the burn scar were.
 */
import React, { useMemo } from 'react';
import { analyses } from '@/react/lib/api';
import { useResource } from '@/react/lib/useResource';
import { formatDate, formatNumber } from '@/react/lib/format';
import { tDirection } from '@/react/lib/i18n';
import SegmentationGrid from '@/react/components/farmer/SegmentationGrid';
import { DeltaPill, MetricTile, Panel } from '@/react/components/farmer/primitives';

function PeriodSide({ caption, side, detail, loading }) {
    const segmentation = detail?.land_intelligence?.segmentation ?? null;
    const landCover = detail?.land_intelligence?.land_cover ?? [];

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">{caption}</h3>
                <span className="font-body-sm text-body-sm font-semibold text-on-surface">{formatDate(side.captured_at)}</span>
            </div>

            <SegmentationGrid
                imageUrl={side.image_url}
                segmentation={segmentation}
                landCover={landCover}
                alt={`Foto ${caption.toLowerCase()} ${formatDate(side.captured_at)}`}
            />

            {!side.image_url && loading && (
                <p className="font-body-sm text-xs text-on-surface-variant">Memuat detail analisis periode ini…</p>
            )}

            <div className="grid grid-cols-2 gap-3">
                <MetricTile label="Skor kesehatan" value={formatNumber(side.land_health_score)} />
                <MetricTile label="Vegetasi" value={formatNumber(side.vegetation_percentage, 1)} unit="%" />
            </div>
        </div>
    );
}

export default function BeforeAfterPanel({ comparison, className = '' }) {
    const missingIds = useMemo(() => {
        if (!comparison) {
            return [];
        }

        return [comparison.from, comparison.to].filter((side) => side && !side.image_url).map((side) => side.id);
    }, [comparison]);

    const { data: details, loading } = useResource(
        async (options) => {
            const pairs = await Promise.all(missingIds.map(async (id) => [id, await analyses.get(id, options)]));

            return Object.fromEntries(pairs);
        },
        [missingIds.join(',')],
        { enabled: missingIds.length > 0 },
    );

    if (!comparison) {
        return (
            <Panel title="Sebelum & sesudah" icon="compare" className={className}>
                <p className="font-body-sm text-body-sm text-on-surface-variant">
                    Perbandingan sebelum–sesudah muncul setelah lahan punya minimal dua periode analisis yang selesai.
                </p>
            </Panel>
        );
    }

    const { from, to } = comparison;

    return (
        <Panel
            title="Sebelum & sesudah"
            subtitle="Periode pertama dibanding periode terakhir"
            icon="compare"
            className={className}
            action={<DeltaPill delta={comparison.vegetation_delta} direction={comparison.direction} label="vegetasi" />}
        >
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <PeriodSide caption="Sebelum" side={from} detail={details?.[from.id] ?? null} loading={loading} />
                <PeriodSide caption="Sesudah" side={to} detail={details?.[to.id] ?? null} loading={loading} />
            </div>

            <div className="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-border-subtle pt-4">
                <span className="inline-flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
                    Arah perubahan
                    <span className={`font-semibold ${comparison.direction === 'declining' ? 'text-critical' : comparison.direction === 'improving' ? 'text-success' : 'text-on-surface'}`}>
                        {tDirection(comparison.direction)}
                    </span>
                </span>
                <DeltaPill delta={comparison.vegetation_delta} direction={comparison.direction} label="vegetasi" />
                <DeltaPill delta={comparison.health_delta} unit=" poin" label="skor kesehatan" />
                <DeltaPill delta={comparison.bare_soil_delta} unit=" pp" label="tanah terbuka" />
                <DeltaPill delta={comparison.charred_delta} unit=" pp" label="tanah terbakar" />
            </div>
        </Panel>
    );
}
