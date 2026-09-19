/**
 * The public landing page: what re:green does, how the pipeline works, who it
 * is for, and the honest limits of the numbers it shows.
 *
 * It renders outside {@see AppShell} — a visitor has no session yet — and it is
 * the only page that talks to an unauthenticated endpoint, `public/summary`.
 * Those figures come from seeded demo lands, so the page states that instead of
 * dressing the numbers up as measured field data.
 */
import React, { useEffect, useRef } from 'react';
import { gsap, ScrollTrigger, useGSAP } from '@/gsap';
import { overview } from '@/react/lib/api';
import { formatHectares, formatNumber } from '@/react/lib/format';
import { Link } from '@/react/lib/router';
import { useResource } from '@/react/lib/useResource';

const SECTIONS = [
    { id: 'masalah', label: 'Masalah' },
    { id: 'cara-kerja', label: 'Cara kerja' },
    { id: 'untuk-siapa', label: 'Untuk siapa' },
    { id: 'karbon', label: 'Karbon' },
];

const PROBLEMS = [
    {
        icon: 'local_fire_department',
        title: 'Lahan terdegradasi',
        body: 'Kebakaran meninggalkan tanah terbuka, bekas bakaran, dan vegetasi yang hilang. Tanpa penanganan, lapisan atas tanah terkikis dan lahan makin sulit pulih.',
    },
    {
        icon: 'psychology_alt',
        title: 'Rekomendasi tidak objektif',
        body: 'Pilihan tanaman sering mengikuti kebiasaan, bukan kondisi nyata lahan. Jenis tanah dan curah hujan setempat jarang dijadikan dasar keputusan.',
    },
    {
        icon: 'timeline',
        title: 'Progres sulit dipantau',
        body: 'Tanpa catatan berkala, sulit membuktikan lahan benar-benar membaik. Foto lama tersebar dan tidak bisa dibandingkan antar periode.',
    },
    {
        icon: 'description',
        title: 'Laporan ESG butuh bukti',
        body: 'Program CSR/ESG dan anggaran pemda menuntut bukti terverifikasi per lahan, bukan laporan naratif tanpa data pendukung.',
    },
];

const STEPS = [
    {
        icon: 'add_a_photo',
        title: 'Unggah foto lahan',
        body: 'Ambil foto dari ponsel atau drone, tentukan tanggal pemotretan, lalu kirim. Tidak perlu alat khusus.',
    },
    {
        icon: 'center_focus_strong',
        title: 'Deteksi keparahan dengan YOLOv8',
        body: 'Model YOLOv8 mendeteksi keparahan kebakaran dan sisa vegetasi pada foto, lalu memetakan sebarannya sebagai grid tutupan lahan.',
    },
    {
        icon: 'rule',
        title: 'Cocokkan dengan kondisi lahan',
        body: 'Mesin berbasis aturan mencocokkan hasil deteksi dengan jenis tanah dan curah hujan, lalu menyusun peringkat tanaman yang paling sesuai.',
    },
    {
        icon: 'monitoring',
        title: 'Pantau dan buktikan progres',
        body: 'Analisis berkala menunjukkan arah perubahan kondisi lahan. Data yang sama menjadi bahan laporan dan estimasi karbon.',
    },
];

const AUDIENCES = [
    {
        icon: 'agriculture',
        title: 'Petani & pengelola lahan',
        body: 'Ketahui kondisi lahan dan tanaman yang paling cocok, tanpa harus menjadi ahli. Riwayat foto tersimpan rapi dan bisa dibuka kapan saja.',
        points: ['Kelola banyak lahan dalam satu akun', 'Rekomendasi tanaman yang bisa dijelaskan', 'Riwayat analisis per periode'],
    },
    {
        icon: 'account_balance',
        title: 'Pemerintah daerah & NGO',
        body: 'Pantau program restorasi lintas lahan, lihat wilayah dan lahan paling berisiko, lalu ekspor laporan untuk rapat dan pendanaan.',
        points: ['Peta sebaran lahan terpantau', 'Rekap per wilayah dan pemilik', 'Laporan siap ekspor'],
    },
    {
        icon: 'corporate_fare',
        title: 'Korporasi (CSR/ESG)',
        body: 'Kumpulkan bukti progres per lahan untuk laporan keberlanjutan, dengan angka yang bisa ditelusuri kembali ke foto dan analisisnya.',
        points: ['Bukti sebelum dan sesudah', 'Status kelayakan karbon per lahan', 'Portofolio lintas mitra'],
    },
];

const CARBON_CHECKS = [
    'Luas lahan memenuhi ambang minimum',
    'Analisis terbaru masih berlaku (≤ 12 bulan)',
    'Tutupan vegetasi di atas ambang minimum',
    'Ada minimal dua periode monitoring',
    'Koordinat lahan tercatat',
];

const DEMO_NOTE =
    'Sebagian angka di halaman ini berasal dari data demo (data_status = demo) yang dipakai saat pengembangan. Setelah foto lapangan diunggah dan dianalisis, status lahan berubah menjadi measured.';

function SectionHeading({ eyebrow, title, description }) {
    return (
        <div className="max-w-2xl" data-reveal>
            <p className="font-label-md text-label-md uppercase tracking-wider text-primary">{eyebrow}</p>
            <h2 className="mt-2 font-headline-lg text-headline-lg-mobile text-on-surface md:text-headline-lg">{title}</h2>
            {description && <p className="mt-3 font-body-md text-body-md text-on-surface-variant">{description}</p>}
        </div>
    );
}

function Stat({ label, value, note }) {
    return (
        <div className="rounded-xl border border-on-primary/15 bg-on-primary/5 px-4 py-3">
            <dt className="font-label-md text-[11px] uppercase tracking-wider text-on-primary/60">{label}</dt>
            <dd className="mt-1 font-headline-md text-headline-md font-bold text-primary-fixed">{value}</dd>
            {note && <dd className="font-body-sm text-[11px] text-on-primary/50">{note}</dd>}
        </div>
    );
}

export default function LandingPage() {
    const rootRef = useRef(null);
    const { data: summary, loading, error } = useResource((options) => overview.publicSummary(options), []);

    useGSAP(
        () => {
            const root = rootRef.current;
            if (!root) {
                return;
            }

            const matchMedia = gsap.matchMedia();
            matchMedia.add('(prefers-reduced-motion: no-preference)', () => {
                gsap.from(root.querySelectorAll('[data-hero]'), {
                    opacity: 0,
                    y: 28,
                    duration: 0.7,
                    ease: 'power3.out',
                    stagger: 0.12,
                });

                root.querySelectorAll('[data-reveal]').forEach((element) => {
                    gsap.from(element, {
                        opacity: 0,
                        y: 26,
                        duration: 0.6,
                        ease: 'power2.out',
                        scrollTrigger: { trigger: element, start: 'top 88%', once: true },
                    });
                });
            });
        },
        { scope: rootRef },
    );

    // The statistics panel changes height once the summary arrives, which moves
    // every trigger below it; recompute the positions instead of drifting.
    useEffect(() => {
        ScrollTrigger.refresh();
    }, [summary, loading]);

    const stats = [
        { label: 'Lahan terpantau', value: formatNumber(summary?.lands_monitored) },
        { label: 'Hektar terpantau', value: formatHectares(summary?.hectares) },
        { label: 'Periode monitoring', value: formatNumber(summary?.monitoring_periods) },
        { label: 'Wilayah', value: formatNumber(summary?.regions) },
    ];

    return (
        <div ref={rootRef} className="min-h-screen bg-background text-on-surface antialiased">
            <header className="sticky top-0 z-[500] border-b border-border-subtle bg-background/85 backdrop-blur">
                <div className="mx-auto flex w-full max-w-max-width items-center justify-between gap-4 px-4 py-3 md:px-margin-desktop">
                    <Link to="/" className="flex items-center gap-2">
                        <span className="material-symbols-outlined text-[26px] text-primary" aria-hidden="true">
                            forest
                        </span>
                        <span className="font-headline-sm text-headline-sm font-bold text-primary">re:green</span>
                    </Link>

                    <nav className="hidden items-center gap-6 lg:flex" aria-label="Bagian halaman">
                        {SECTIONS.map((section) => (
                            <a
                                key={section.id}
                                href={`#${section.id}`}
                                className="font-body-sm text-body-sm text-on-surface-variant transition-colors hover:text-primary"
                            >
                                {section.label}
                            </a>
                        ))}
                    </nav>

                    <div className="flex items-center gap-2">
                        <Link
                            to="/login"
                            className="rounded-lg px-3 py-2 font-body-sm text-body-sm font-semibold text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                        >
                            Masuk
                        </Link>
                        <Link
                            to="/register"
                            className="rounded-lg bg-primary px-4 py-2 font-body-sm text-body-sm font-semibold text-on-primary transition-opacity hover:opacity-90"
                        >
                            Daftar
                        </Link>
                    </div>
                </div>
            </header>

            {/* Hero */}
            <section className="relative overflow-hidden bg-primary text-on-primary">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -right-32 -top-40 h-[460px] w-[460px] rounded-full"
                    style={{ background: 'radial-gradient(circle, rgba(197,234,223,0.20), transparent 70%)' }}
                />
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -bottom-44 -left-24 h-[380px] w-[380px] rounded-full"
                    style={{ background: 'radial-gradient(circle, rgba(202,236,188,0.14), transparent 70%)' }}
                />

                <div className="relative mx-auto grid w-full max-w-max-width gap-10 px-4 py-16 md:px-margin-desktop md:py-24 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                    <div>
                        <span
                            data-hero
                            className="inline-flex items-center gap-2 rounded-full border border-on-primary/20 bg-on-primary/10 px-3 py-1 font-label-md text-[11px] uppercase tracking-wider text-primary-fixed"
                        >
                            <span className="material-symbols-outlined text-[15px]" aria-hidden="true">
                                local_fire_department
                            </span>
                            Restorasi lahan pasca kebakaran
                        </span>

                        <h1
                            data-hero
                            className="mt-5 font-headline-md text-headline-lg-mobile text-on-primary md:font-headline-xl md:text-headline-xl"
                        >
                            <span className="text-primary-fixed">re:green</span> — Platform Restorasi Lahan Pasca Kebakaran
                        </h1>

                        <p data-hero className="mt-5 max-w-xl font-body-lg text-body-lg text-on-primary/80">
                            Unggah foto lahan, biarkan model mendeteksi keparahan kebakaran dan sisa vegetasi, lalu terima rekomendasi tanaman yang
                            cocok dengan jenis tanah dan curah hujan setempat. Setiap analisis tersimpan sebagai bukti progres.
                        </p>

                        <div data-hero className="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link
                                to="/register"
                                className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-fixed px-6 py-3 font-body-md text-body-md font-semibold text-on-primary-fixed transition-opacity hover:opacity-90"
                            >
                                Daftar
                                <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                                    arrow_forward
                                </span>
                            </Link>
                            <Link
                                to="/login"
                                className="inline-flex items-center justify-center gap-2 rounded-xl border border-on-primary/30 px-6 py-3 font-body-md text-body-md font-semibold text-on-primary transition-colors hover:bg-on-primary/10"
                            >
                                Masuk
                            </Link>
                        </div>

                        <p data-hero className="mt-5 font-body-sm text-xs text-on-primary/60">
                            Foto dari ponsel atau drone. Hasil analisis langsung tersimpan di akun Anda.
                        </p>
                    </div>

                    <div data-hero className="rounded-2xl border border-on-primary/15 bg-on-primary/5 p-5 backdrop-blur md:p-6">
                        <div className="flex items-center justify-between gap-3">
                            <p className="font-label-md text-[11px] uppercase tracking-wider text-primary-fixed">Statistik platform</p>
                            <span className="material-symbols-outlined text-[18px] text-on-primary/50" aria-hidden="true">
                                insights
                            </span>
                        </div>

                        <dl className="mt-4 grid grid-cols-2 gap-3">
                            {stats.map((stat) => (
                                <Stat key={stat.label} label={stat.label} value={loading ? '…' : stat.value} />
                            ))}
                        </dl>

                        {error ? (
                            <p className="mt-4 flex items-start gap-2 font-body-sm text-xs text-primary-fixed">
                                <span className="material-symbols-outlined text-[15px]" aria-hidden="true">
                                    cloud_off
                                </span>
                                Statistik belum bisa dimuat. Muat ulang halaman untuk mencoba lagi.
                            </p>
                        ) : (
                            <p className="mt-4 flex items-start gap-2 font-body-sm text-xs text-on-primary/60">
                                <span className="material-symbols-outlined text-[15px]" aria-hidden="true">
                                    info
                                </span>
                                Data awal berstatus demo sampai foto lapangan diunggah dan dianalisis.
                            </p>
                        )}
                    </div>
                </div>
            </section>

            {/* Problem */}
            <section id="masalah" className="mx-auto w-full max-w-max-width px-4 py-16 md:px-margin-desktop md:py-20">
                <SectionHeading
                    eyebrow="Masalah"
                    title="Pemulihan lahan berjalan lambat karena datanya tidak rapi"
                    description="Empat hal yang paling sering menghambat restorasi lahan bekas kebakaran — dan yang ingin dibereskan re:green."
                />

                <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {PROBLEMS.map((problem) => (
                        <article
                            key={problem.title}
                            data-reveal
                            className="card-shadow rounded-xl border border-border-subtle bg-surface-container-lowest p-5"
                        >
                            <span className="material-symbols-outlined text-[26px] text-primary" aria-hidden="true">
                                {problem.icon}
                            </span>
                            <h3 className="mt-3 font-headline-sm text-headline-sm text-on-surface">{problem.title}</h3>
                            <p className="mt-2 font-body-sm text-body-sm text-on-surface-variant">{problem.body}</p>
                        </article>
                    ))}
                </div>
            </section>

            {/* How it works */}
            <section id="cara-kerja" className="border-y border-border-subtle bg-surface-container-low">
                <div className="mx-auto w-full max-w-max-width px-4 py-16 md:px-margin-desktop md:py-20">
                    <SectionHeading
                        eyebrow="Cara kerja"
                        title="Empat langkah dari foto lapangan menjadi bukti"
                        description="Alurnya sengaja pendek: satu foto masuk, satu rekomendasi keluar, dan hasilnya tersimpan sebagai riwayat."
                    />

                    <ol className="mt-10 grid gap-4 lg:grid-cols-4">
                        {STEPS.map((step, index) => (
                            <li
                                key={step.title}
                                data-reveal
                                className="relative rounded-xl border border-border-subtle bg-surface-container-lowest p-5"
                            >
                                <div className="flex items-center gap-3">
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary font-headline-sm text-headline-sm font-bold text-on-primary">
                                        {index + 1}
                                    </span>
                                    <span className="material-symbols-outlined text-[22px] text-secondary" aria-hidden="true">
                                        {step.icon}
                                    </span>
                                </div>
                                <h3 className="mt-4 font-headline-sm text-headline-sm text-on-surface">{step.title}</h3>
                                <p className="mt-2 font-body-sm text-body-sm text-on-surface-variant">{step.body}</p>
                            </li>
                        ))}
                    </ol>
                </div>
            </section>

            {/* Audience */}
            <section id="untuk-siapa" className="mx-auto w-full max-w-max-width px-4 py-16 md:px-margin-desktop md:py-20">
                <SectionHeading
                    eyebrow="Untuk siapa"
                    title="Satu alur data, tiga kebutuhan berbeda"
                    description="Peran akun menentukan apa yang terlihat: lahan sendiri, seluruh lahan terpantau, atau portofolio lintas mitra."
                />

                <div className="mt-10 grid gap-4 lg:grid-cols-3">
                    {AUDIENCES.map((audience) => (
                        <article
                            key={audience.title}
                            data-reveal
                            className="card-shadow flex flex-col rounded-xl border border-border-subtle bg-surface-container-lowest p-6"
                        >
                            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-secondary-container text-on-secondary-container">
                                <span className="material-symbols-outlined text-[24px]" aria-hidden="true">
                                    {audience.icon}
                                </span>
                            </span>
                            <h3 className="mt-4 font-headline-sm text-headline-sm text-on-surface">{audience.title}</h3>
                            <p className="mt-2 font-body-sm text-body-sm text-on-surface-variant">{audience.body}</p>
                            <ul className="mt-4 space-y-2 border-t border-border-subtle pt-4">
                                {audience.points.map((point) => (
                                    <li key={point} className="flex items-start gap-2 font-body-sm text-body-sm text-on-surface">
                                        <span className="material-symbols-outlined text-[17px] text-success" aria-hidden="true">
                                            check_circle
                                        </span>
                                        {point}
                                    </li>
                                ))}
                            </ul>
                        </article>
                    ))}
                </div>
            </section>

            {/* Carbon */}
            <section id="karbon" className="border-t border-border-subtle bg-surface-container-low">
                <div className="mx-auto grid w-full max-w-max-width gap-10 px-4 py-16 md:px-margin-desktop md:py-20 lg:grid-cols-[1fr_1fr] lg:items-start">
                    <div>
                        <SectionHeading
                            eyebrow="Karbon"
                            title="Dari bukti lapangan menjadi peluang pendanaan"
                            description="Tutupan vegetasi yang terukur adalah bahan baku estimasi serapan karbon. re:green menyiapkan angkanya, mitra yang memutuskan kelayakannya."
                        />

                        <div className="mt-8 space-y-4" data-reveal>
                            <div className="flex gap-3">
                                <span className="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">
                                    calculate
                                </span>
                                <div>
                                    <h3 className="font-headline-sm text-headline-sm text-on-surface">Estimasi serapan</h3>
                                    <p className="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                                        Serapan tahunan dinyatakan dalam ton CO₂e per tahun, dihitung dari luas lahan, tutupan vegetasi pada analisis
                                        terbaru, dan faktor pertumbuhan biomassa IPCC. Angkanya diberi label estimasi.
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-3">
                                <span className="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">
                                    fact_check
                                </span>
                                <div>
                                    <h3 className="font-headline-sm text-headline-sm text-on-surface">Status kelayakan</h3>
                                    <p className="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                                        Platform memeriksa syarat kelayakan secara otomatis dan menampilkan mana yang belum terpenuhi, sehingga
                                        perbaikannya bisa direncanakan.
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-3">
                                <span className="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">
                                    handshake
                                </span>
                                <div>
                                    <h3 className="font-headline-sm text-headline-sm text-on-surface">Diteruskan ke mitra sertifikasi</h3>
                                    <p className="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                                        Pengajuan karbon dari suatu lahan dikirim ke mitra sertifikasi, bersama riwayat analisis yang menjadi buktinya.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div data-reveal className="card-shadow rounded-2xl border border-border-subtle bg-surface-container-lowest p-6">
                        <p className="font-label-md text-[11px] uppercase tracking-wider text-outline">Pemeriksaan kelayakan</p>
                        <ul className="mt-4 space-y-3">
                            {CARBON_CHECKS.map((check) => (
                                <li key={check} className="flex items-start gap-2 font-body-sm text-body-sm text-on-surface">
                                    <span className="material-symbols-outlined text-[18px] text-outline" aria-hidden="true">
                                        check_box
                                    </span>
                                    {check}
                                </li>
                            ))}
                        </ul>

                        <div className="mt-6 flex gap-3 rounded-xl border border-border-subtle bg-surface-container-low p-4">
                            <span className="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">
                                info
                            </span>
                            <p className="font-body-sm text-body-sm text-on-surface-variant">
                                Angka karbon di platform ini adalah estimasi, bukan sertifikasi. Penerbitan kredit karbon dilakukan mitra sertifikasi
                                independen; re:green menyiapkan dan merapikan buktinya.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {/* Closing CTA */}
            <section className="mx-auto w-full max-w-max-width px-4 py-16 md:px-margin-desktop md:py-20">
                <div
                    data-reveal
                    className="flex flex-col items-start gap-6 rounded-2xl bg-primary px-6 py-10 text-on-primary md:flex-row md:items-center md:justify-between md:px-10"
                >
                    <div className="max-w-xl">
                        <h2 className="font-headline-lg text-headline-lg-mobile text-on-primary md:text-headline-lg">
                            Mulai dari satu foto lahan
                        </h2>
                        <p className="mt-3 font-body-md text-body-md text-on-primary/80">
                            Buat akun, unggah foto lahan pertama, dan lihat kondisi lahannya terbaca dalam hitungan menit.
                        </p>
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Link
                            to="/register"
                            className="inline-flex items-center justify-center rounded-xl bg-primary-fixed px-6 py-3 font-body-md text-body-md font-semibold text-on-primary-fixed transition-opacity hover:opacity-90"
                        >
                            Daftar
                        </Link>
                        <Link
                            to="/login"
                            className="inline-flex items-center justify-center rounded-xl border border-on-primary/30 px-6 py-3 font-body-md text-body-md font-semibold text-on-primary transition-colors hover:bg-on-primary/10"
                        >
                            Masuk
                        </Link>
                    </div>
                </div>
            </section>

            {/* Footer */}
            <footer className="border-t border-border-subtle bg-surface-container-lowest">
                <div className="mx-auto grid w-full max-w-max-width gap-8 px-4 py-12 md:px-margin-desktop lg:grid-cols-[1.4fr_1fr_1fr]">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="material-symbols-outlined text-[24px] text-primary" aria-hidden="true">
                                forest
                            </span>
                            <span className="font-headline-sm text-headline-sm font-bold text-primary">re:green</span>
                        </div>
                        <p className="mt-3 max-w-md font-body-sm text-body-sm text-on-surface-variant">{DEMO_NOTE}</p>
                    </div>

                    <div>
                        <p className="font-label-md text-label-md uppercase tracking-wider text-outline">Jelajahi</p>
                        <ul className="mt-3 space-y-2">
                            {SECTIONS.map((section) => (
                                <li key={section.id}>
                                    <a
                                        href={`#${section.id}`}
                                        className="font-body-sm text-body-sm text-on-surface-variant transition-colors hover:text-primary"
                                    >
                                        {section.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div>
                        <p className="font-label-md text-label-md uppercase tracking-wider text-outline">Akun</p>
                        <ul className="mt-3 space-y-2">
                            <li>
                                <Link to="/login" className="font-body-sm text-body-sm text-on-surface-variant transition-colors hover:text-primary">
                                    Masuk
                                </Link>
                            </li>
                            <li>
                                <Link to="/register" className="font-body-sm text-body-sm text-on-surface-variant transition-colors hover:text-primary">
                                    Daftar
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <div className="border-t border-border-subtle">
                    <p className="mx-auto w-full max-w-max-width px-4 py-4 font-body-sm text-xs text-outline md:px-margin-desktop">
                        © 2026 re:green. Prototipe platform restorasi lahan.
                    </p>
                </div>
            </footer>
        </div>
    );
}
