/**
 * The ranked crops for one analysed photo.
 *
 * The `agriculture` block is the only place the crop engine's result lives, so
 * everything on screen comes straight from it: the score out of 100, the
 * classification band, the parameters that pulled the score down, and the
 * parameters the engine never measured (which it refuses to invent).
 *
 * When the block says the ranking is unavailable, the reason is rebuilt here as
 * facts — which parameters are missing — instead of the engine's English
 * sentence.
 */
import React from 'react';
import { formatNumber } from '@/react/lib/format';
import { tParameter } from '@/react/lib/i18n';
import { Button, Chip, Panel, ShareBar } from '@/react/components/farmer/primitives';
import { tClassification, tMarketValue } from '@/react/components/farmer/labels';

function ScoreBar({ score, color }) {
    return (
        <div className="flex items-center gap-3">
            <ShareBar percentage={score} color={color} className="flex-1" />
            <span className="w-16 text-right font-body-sm text-body-sm font-semibold text-on-surface">{formatNumber(score)}/100</span>
        </div>
    );
}

function CropCard({ crop, isSelected, isBusy, onSelect }) {
    const measured = Object.entries(crop.parameter_scores ?? {});

    return (
        <li className={`rounded-xl border p-4 ${isSelected ? 'border-primary bg-surface-container-low' : 'border-border-subtle bg-surface-container-lowest'}`}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                    <span className="text-[24px]" aria-hidden="true">
                        {crop.icon}
                    </span>
                    <div>
                        <p className="font-body-md text-body-md font-semibold text-on-surface">{crop.name}</p>
                        <p className="font-body-sm text-xs text-on-surface-variant">{crop.category}</p>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Chip label={tClassification(crop.classification_key, crop.classification)} color={crop.classification_color} />
                    {isSelected && <Chip label="Tanaman pilihan Anda" color="#01261f" />}
                </div>
            </div>

            <div className="mt-3 space-y-2">
                <ScoreBar score={crop.score} color={crop.color} />
                <div className="flex flex-wrap gap-x-5 gap-y-1 font-body-sm text-xs text-on-surface-variant">
                    <span>
                        Nilai pasar <span className="font-semibold text-on-surface">{tMarketValue(crop.market_value)}</span>
                    </span>
                    <span>
                        Potensi agroforestri{' '}
                        <span className="font-semibold text-on-surface">{formatNumber(crop.agroforestry_potential * 100)}%</span>
                    </span>
                </div>
            </div>

            {crop.limiting_parameters?.length > 0 && (
                <p className="mt-3 font-body-sm text-xs text-on-surface-variant">
                    Penghambat skor: {crop.limiting_parameters.map((parameter) => tParameter(parameter)).join(', ')}.
                </p>
            )}

            {crop.not_measured?.length > 0 && (
                <p className="mt-1 font-body-sm text-xs text-on-surface-variant">
                    Belum diukur untuk lahan ini: {crop.not_measured.map((parameter) => tParameter(parameter)).join(', ')}.
                </p>
            )}

            {measured.length > 0 && (
                <details className="mt-3">
                    <summary className="cursor-pointer font-body-sm text-xs font-semibold text-primary">
                        Rincian nilai per parameter
                    </summary>
                    <ul className="mt-2 space-y-1">
                        {measured.map(([parameter, value]) => (
                            <li key={parameter} className="flex items-center justify-between gap-3 font-body-sm text-xs text-on-surface-variant">
                                <span>{tParameter(parameter)}</span>
                                <span className="font-semibold text-on-surface">{formatNumber(value)}</span>
                            </li>
                        ))}
                    </ul>
                </details>
            )}

            {onSelect && (
                <div className="mt-4 flex items-center gap-2">
                    <Button
                        variant={isSelected ? 'secondary' : 'primary'}
                        icon={isSelected ? 'check' : 'eco'}
                        disabled={isBusy || isSelected}
                        onClick={() => onSelect(crop)}
                    >
                        {isSelected ? 'Sudah dipilih' : isBusy ? 'Menyimpan…' : 'Pilih tanaman ini'}
                    </Button>
                </div>
            )}
        </li>
    );
}

function RankingUnavailable({ agriculture }) {
    const missing = agriculture?.missing_inputs ?? [];
    const known = Object.entries(agriculture?.inputs ?? {})
        .filter(([, value]) => value !== null && value !== undefined && value !== '')
        .map(([parameter]) => tParameter(parameter));

    return (
        <div className="rounded-xl border border-dashed border-border-subtle bg-surface-container-low p-4">
            <p className="font-body-sm text-body-sm font-semibold text-on-surface">Perankingan tanaman belum bisa dijalankan</p>
            <p className="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                Mesin rekomendasi butuh minimal tiga parameter lokasi, salah satunya curah hujan atau suhu. Parameter yang sudah diketahui:{' '}
                {known.length > 0 ? known.join(', ') : 'belum ada'}.
                {missing.length > 0 && <> Parameter yang masih kosong: {missing.map((parameter) => tParameter(parameter)).join(', ')}.</>}
            </p>
            <p className="mt-2 font-body-sm text-xs text-on-surface-variant">
                Lengkapi tekstur tanah dan curah hujan pada data lahan, atau unggah foto dengan koordinat, supaya rekomendasi tanaman bisa dihitung.
            </p>
        </div>
    );
}

export default function CropRanking({
    agriculture,
    selectedCropId = null,
    onSelect = null,
    busyCropId = null,
    title = 'Rekomendasi tanaman',
    className = '',
}) {
    if (!agriculture) {
        return null;
    }

    const crops = agriculture.crops ?? [];
    const available = agriculture.ranking_available === true && crops.length > 0;

    return (
        <Panel
            title={title}
            subtitle={
                available
                    ? `${crops.length} tanaman dengan skor tertinggi dari ${formatNumber(agriculture.crop_count)} tanaman yang dinilai`
                    : null
            }
            icon="agriculture"
            className={className}
        >
            {!available ? (
                <RankingUnavailable agriculture={agriculture} />
            ) : (
                <ul className="space-y-3">
                    {crops.map((crop) => (
                        <CropCard
                            key={crop.id}
                            crop={crop}
                            isSelected={selectedCropId === crop.id}
                            isBusy={busyCropId === crop.id}
                            onSelect={onSelect}
                        />
                    ))}
                </ul>
            )}
        </Panel>
    );
}
