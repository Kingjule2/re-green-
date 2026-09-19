# re:green

Platform restorasi lahan pasca kebakaran berbasis machine learning.

Petani memotret lahannya, model YOLOv8 membaca kondisi lahan (tingkat keparahan
kebakaran dan sisa vegetasi), lalu mesin rule-based mencocokkan hasil deteksi itu
dengan data lokasi (jenis tanah, curah hujan) untuk menghasilkan rekomendasi
tanaman restorasi beserta panduan tanamnya. Foto susulan tiap periode membandingkan
kondisi lahan antar waktu, sehingga progres restorasi terbukti dengan data — bukan
klaim. Data yang sama menjadi dashboard agregat, laporan PDF, dan basis pengajuan
carbon credit lewat mitra sertifikasi.

## Arsitektur

```
Petani / Pemda / Korporasi
        │  (React SPA, satu Blade view)
        ▼
Laravel 13 ── HTTP ──► ML service (FastAPI)  ──► YOLOv8 / fallback heuristik
   │                        └── DEMNAS, ISRIC SoilGrids, NASA POWER (konteks lokasi)
   ├── PostgreSQL: lands, land_analyses, carbon_assessments, report_snapshots (+ alur survei lama)
   ├── Queue job AnalyzeLandPhoto: ML → LandIntelligenceEngine → RestorationRecommendationEngine → karbon
   └── dompdf: laporan PDF (snapshot dibekukan, tidak pernah dihitung ulang)
```

- **`app/Services/Ml/LandAnalysisClient`** — batas Computer Vision yang bisa ditukar: seluruh
  aplikasi bergantung pada *bentuk* hasil, bukan pada modelnya.
- **`app/Services/Land/LandIntelligenceEngine`** — skor kesehatan lahan, keparahan kebakaran,
  kelas tutupan lahan, dan deteksi masalah.
- **`app/Services/Agriculture/*`** — katalog 10 komoditas + skoring kesesuaian berbobot
  (rule-based, terpisah dari YOLO).
- **`app/Services/Land/MonitoringProgress`** — deret waktu antar periode dan perbandingan sebelum-sesudah.
- **`app/Services/Carbon/CarbonCreditAssessor`** — estimasi serapan + checklist kelayakan
  (penyaringan awal, bukan sertifikasi).

## Menjalankan

```bash
composer install
cp .env.example .env          # lalu php artisan key:generate
php artisan migrate --seed    # data demo: 4 akun, 6 lahan, 10 periode monitoring
npm install && npm run dev    # atau: npm run build
php artisan serve
```

Layanan ML (diperlukan untuk menganalisis foto baru):

```powershell
cd ml
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements-dev.txt
uvicorn app.main:app --reload --port 8001
```

Job analisis berjalan di queue, jadi worker harus hidup:

```bash
php artisan queue:work
```

`.env` yang relevan: `ML_SERVICE_URL` (default `http://localhost:8001`) dan
`ML_SERVICE_KEY` (samakan dengan `ML_API_KEY` milik service; kosong = tanpa kunci).

### Akun demo (dari `php artisan migrate --seed`)

| Peran | Email | Kata sandi |
| --- | --- | --- |
| Petani / pengelola lahan | `petani@regreen.id` | `password` |
| Petani kedua | `petani2@regreen.id` | `password` |
| Pemerintah daerah / NGO | `bappeda@regreen.id` | `password` |
| Korporasi (CSR/ESG) | `csr@regreen.id` | `password` |

## Peran dan hak akses

| Peran | Melihat | Boleh mengubah |
| --- | --- | --- |
| `farmer` | lahan miliknya sendiri | lahannya sendiri: daftar lahan, unggah foto, pilih tanaman, ajukan karbon |
| `institution` (pemda/NGO) | seluruh lahan terpantau | hanya lahan yang ia daftarkan sendiri |
| `corporate` (CSR/ESG) | seluruh lahan terpantau | hanya lahan yang ia daftarkan sendiri |

Semua endpoint selain `auth/register`, `auth/login`, dan `public/summary` butuh sesi.
API berjalan di middleware group `web` (cookie sesi + token CSRF dari meta tag di layout).

## Model ML: apa yang nyata dan apa yang belum

- Jalur **YOLOv8** ada dan lengkap (`ml/app/yolo.py`, `ml/train_yolo.py`, `ml/evaluate_yolo.py`),
  tapi **berkas bobot tidak ikut di repositori**. Selama `ml/models/regreen-burn-yolov8n.pt`
  belum ada (atau `ultralytics`/`torch` belum terpasang), service otomatis memakai classifier
  warna `ColorHistogramModel` dan **mengatakannya** pada hasil: `model.name = "color-histogram"`,
  `fire_severity.method = "colour-heuristic"`, plus `fire_severity.reason` yang menjelaskan
  kenapa. UI menampilkan catatan itu apa adanya.
- Melatih model: lihat `ml/train_yolo.py` (skema dataset dan panduan pelabelan ada di docstring-nya),
  evaluasi akurasi/mAP dengan `ml/evaluate_yolo.py`.
- Konteks lokasi (elevasi, tanah, iklim) diambil dari dataset publik: DEMNAS (BIG),
  ISRIC SoilGrids, NASA POWER. Bila salah satu tidak bisa dihubungi, blok itu dilaporkan
  `available: false` beserta alasannya dan tidak menular ke hasil lain.

## Data demo vs data terukur

Setiap lahan punya `data_status`: `demo` untuk baris hasil seeder, `measured` begitu ada
foto yang benar-benar dianalisis. Dashboard, laporan, dan halaman lahan menyebutkan status
ini, sehingga angka demo tidak pernah tampil seolah hasil lapangan.

Estimasi karbon juga diberi label serupa: `method.notice` menyatakan bahwa angkanya adalah
estimasi penyaringan awal dengan laju serapan per kelas vegetasi, bukan hasil sertifikasi.

## Pengujian

```bash
php artisan test            # PHPUnit 12 (sqlite :memory:)
cd ml && .venv/Scripts/python.exe -m pytest -q
vendor/bin/pint --dirty     # gaya kode PHP
```

Catatan penting: `phpunit.xml` memakai `force="true"` pada setiap `<env>`. Tanpa itu, variabel
lingkungan yang sudah ada di shell (`APP_ENV=local`, `DB_CONNECTION=pgsql`) menang dan
`php artisan test` akan menjalankan `migrate:fresh` pada database **dev**. Jangan dihapus.

## Struktur data

| Tabel | Isi |
| --- | --- |
| `lands` | satu lahan: pemilik, lokasi, luas, tanggal kebakaran, tekstur tanah & curah hujan yang diisi petani, tanaman yang dipilih |
| `land_analyses` | satu foto + hasil deteksi: keparahan, tutupan lahan, skor kesehatan, rekomendasi, asal-usul model |
| `carbon_assessments` | estimasi serapan + checklist kelayakan + pengajuan ke mitra |
| `report_snapshots` | laporan yang dibekukan per versi (PDF/CSV) |
| `parcels`, `field_observations`, `assets`, `analysis_runs`, `analysis_reviews` | alur survei lanjutan yang dipertahankan dari platform sebelumnya: sub-petak, observasi lapangan manual, aset bukti, analisis dNBR satelit, dan review manusia |
