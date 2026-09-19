<?php

namespace App\Services\Agriculture;

/**
 * Crop database with suitability requirements.
 *
 * Ported from the ReGreen prototype's `data/crops.js` so the numbers a farmer
 * sees in this app match the ones the design was built around. Each crop
 * declares the environmental envelope it grows in — an absolute range plus the
 * optimal core of that range — and the weights the scoring engine applies to
 * each parameter.
 *
 * Requirements are the published agronomic envelopes for Indonesian growing
 * conditions: elevation in metres, temperature in degrees Celsius, rainfall in
 * mm per year, pH in pH units, slope in degrees, soil moisture as a percentage
 * of the plant-available range, and texture as USDA class names.
 */
final class CropCatalog
{
    /**
     * Weight keys are shared with {@see CropSuitabilityEngine}'s input keys, and
     * every crop's weights sum to 1.0.
     *
     * @var list<array<string, mixed>>
     */
    private const CROPS = [
        [
            'id' => 'coffee-arabica',
            'name' => 'Coffee Arabica',
            'icon' => '☕',
            'category' => 'Perkebunan',
            'color' => '#6F4E37',
            'description' => 'Premium highland coffee, requires cool temperatures and well-drained acidic soils.',
            'requirements' => [
                'elevation' => ['min' => 900, 'max' => 1600, 'optimal' => ['min' => 1000, 'max' => 1400]],
                'temperature' => ['min' => 15, 'max' => 24, 'optimal' => ['min' => 18, 'max' => 22]],
                'rainfall' => ['min' => 1500, 'max' => 2500, 'optimal' => ['min' => 1800, 'max' => 2200]],
                'soil_ph' => ['min' => 4.5, 'max' => 6.5, 'optimal' => ['min' => 5.0, 'max' => 6.0]],
                'slope' => ['min' => 0, 'max' => 25, 'optimal' => ['min' => 5, 'max' => 15]],
                'soil_moisture' => ['min' => 30, 'max' => 70, 'optimal' => ['min' => 40, 'max' => 60]],
                'soil_texture' => ['Loam', 'Clay Loam', 'Silt Loam', 'Sandy Loam'],
            ],
            'weights' => ['elevation' => 0.20, 'temperature' => 0.22, 'rainfall' => 0.15, 'soil_ph' => 0.15, 'soil_texture' => 0.08, 'slope' => 0.10, 'soil_moisture' => 0.05, 'land_health' => 0.05],
            'agroforestry_potential' => 0.9,
            'market_value' => 'High',
            'growing_period' => '3-4 years to first harvest',
            'planting_guide' => [
                'summary' => 'Arabika cocok untuk lahan pasca kebakaran pada elevasi 900-1.600 m dengan curah hujan 1.500-2.500 mm/tahun; tajuknya menutup tanah bertahap sehingga menekan erosi lereng setelah terbakar.',
                'steps' => [
                    'Bersihkan tunggul dan batang mati sisa kebakaran, sisakan kayu sebagai guludan kontur atau mulsa; jangan buang lapisan tanah atas.',
                    'Pasang teras atau rorak mengikuti kontur pada lahan berlereng (sampai 25°) untuk menahan aliran permukaan.',
                    'Buat lubang tanam 40 x 40 x 40 cm, campur tanah galian dengan kompos atau pupuk kandang 2-5 kg per lubang, diamkan 2-4 minggu.',
                    'Tanam bibit arabika berumur 6-8 bulan di awal musim hujan, padatkan tanah dan beri naungan sementara.',
                    'Tanam pohon naungan seperti dadap atau lamtoro satu-dua baris sebelum atau bersamaan dengan kopi.',
                    'Tutup permukaan tanah dengan mulsa organik atau tanaman penutup tanah di antara barisan.',
                ],
                'spacing' => '2,5 m x 2,5 m (±1.600 tanaman/ha)',
                'seedlings_per_ha' => '±1.600 bibit/ha',
                'time_to_canopy' => '±3 tahun sampai tajuk saling menutup; panen pertama 3-4 tahun',
                'water_need' => '1.500-2.500 mm/tahun; siram saat kemarau panjang pada 2 tahun pertama',
                'maintenance' => [
                    'Penyiangan gulma 3-4 kali per tahun pada 2 tahun pertama; pertahankan mulsa dan penutup tanah.',
                    'Pemupukan berimbang (N-P-K) 2 kali per tahun dengan dosis naik bertahap sesuai umur tanaman.',
                    'Pengendalian penggerek buah dan karat daun lewat sanitasi serta pangkas.',
                    'Pangkas bentuk dan atur naungan agar cahaya masuk 30-50 %.',
                    'Perbaiki teras dan saluran drainase sebelum musim hujan, periksa erosi alur.',
                ],
                'notes' => 'Tanah bekas kebakaran mudah tererosi dan pH dapat turun; uji pH lebih dahulu, kapur bila di bawah 4,5, dan jaga naungan. Tanah tipis di lereng perlu mulsa tambahan dan hindari olah tanah berat.',
            ],
        ],
        [
            'id' => 'coffee-robusta',
            'name' => 'Coffee Robusta',
            'icon' => '☕',
            'category' => 'Perkebunan',
            'color' => '#8B6914',
            'description' => 'Hardy lowland coffee variety, tolerates higher temperatures and varied soils.',
            'requirements' => [
                'elevation' => ['min' => 200, 'max' => 900, 'optimal' => ['min' => 400, 'max' => 700]],
                'temperature' => ['min' => 20, 'max' => 30, 'optimal' => ['min' => 22, 'max' => 28]],
                'rainfall' => ['min' => 1500, 'max' => 3000, 'optimal' => ['min' => 2000, 'max' => 2500]],
                'soil_ph' => ['min' => 4.5, 'max' => 6.5, 'optimal' => ['min' => 5.0, 'max' => 6.0]],
                'slope' => ['min' => 0, 'max' => 30, 'optimal' => ['min' => 3, 'max' => 15]],
                'soil_moisture' => ['min' => 35, 'max' => 75, 'optimal' => ['min' => 45, 'max' => 65]],
                'soil_texture' => ['Loam', 'Clay Loam', 'Silt Loam', 'Sandy Clay Loam'],
            ],
            'weights' => ['elevation' => 0.18, 'temperature' => 0.22, 'rainfall' => 0.15, 'soil_ph' => 0.13, 'soil_texture' => 0.10, 'slope' => 0.10, 'soil_moisture' => 0.07, 'land_health' => 0.05],
            'agroforestry_potential' => 0.85,
            'market_value' => 'Medium-High',
            'growing_period' => '2-3 years to first harvest',
            'planting_guide' => [
                'summary' => 'Robusta lebih toleran panas dan tanah bervariasi di dataran rendah 200-900 m, dan tajuknya menutup lahan cukup cepat sehingga cocok merehabilitasi bekas kebakaran di bawah 900 m.',
                'steps' => [
                    'Tandai dan potong kayu terbakar, lalu susun sebagai guludan kontur atau mulsa.',
                    'Olah tanah minimum dan buat lubang tanam 40 x 40 x 40 cm dengan kompos 2-5 kg per lubang.',
                    'Tanam bibit robusta berumur 6-8 bulan pada awal musim hujan; perlebar jarak barisan bila ingin tumpangsari.',
                    'Sediakan naungan sementara seperti dadap untuk menekan suhu dan menjaga kelembapan tanah.',
                    'Tanam penutup tanah legum di antara barisan untuk menahan erosi permukaan.',
                ],
                'spacing' => '3 m x 2 m (±1.667 tanaman/ha)',
                'seedlings_per_ha' => '±1.667 bibit/ha',
                'time_to_canopy' => '±2 tahun sampai tajuk saling menutup; panen pertama 2-3 tahun',
                'water_need' => '1.500-3.000 mm/tahun; siram saat kemarau panjang pada 2 tahun pertama',
                'maintenance' => [
                    'Penyiangan dan pembumbunan 3-4 kali per tahun pada 2 tahun pertama.',
                    'Pemupukan N-P-K 2 kali per tahun; tambahkan bahan organik setiap tahun.',
                    'Pengendalian penggerek batang dan karat daun melalui pangkas sanitasi.',
                    'Pangkas pemeliharaan agar cabang produktif terbuka dan tajuk tidak terlalu rapat.',
                ],
                'notes' => 'Robusta toleran pH 4,5-6,5 tetapi tetap peka pada tanah terbakar yang memadat; hindari erosi permukaan dan jaga drainase. Jangan tanam pada lereng di atas 30° tanpa teras.',
            ],
        ],
        [
            'id' => 'cocoa',
            'name' => 'Cocoa',
            'icon' => '🍫',
            'category' => 'Perkebunan',
            'color' => '#5C3317',
            'description' => 'Tropical tree crop requiring warm, humid conditions with shade requirements.',
            'requirements' => [
                'elevation' => ['min' => 0, 'max' => 800, 'optimal' => ['min' => 100, 'max' => 600]],
                'temperature' => ['min' => 21, 'max' => 32, 'optimal' => ['min' => 24, 'max' => 28]],
                'rainfall' => ['min' => 1500, 'max' => 2500, 'optimal' => ['min' => 1800, 'max' => 2200]],
                'soil_ph' => ['min' => 5.0, 'max' => 7.5, 'optimal' => ['min' => 6.0, 'max' => 7.0]],
                'slope' => ['min' => 0, 'max' => 20, 'optimal' => ['min' => 2, 'max' => 10]],
                'soil_moisture' => ['min' => 40, 'max' => 80, 'optimal' => ['min' => 50, 'max' => 70]],
                'soil_texture' => ['Clay Loam', 'Loam', 'Silty Clay Loam', 'Silt Loam'],
            ],
            'weights' => ['elevation' => 0.15, 'temperature' => 0.25, 'rainfall' => 0.18, 'soil_ph' => 0.12, 'soil_texture' => 0.10, 'slope' => 0.08, 'soil_moisture' => 0.07, 'land_health' => 0.05],
            'agroforestry_potential' => 0.95,
            'market_value' => 'High',
            'growing_period' => '3-5 years to first harvest',
            'planting_guide' => [
                'summary' => 'Kakao tumbuh baik di dataran rendah 0-800 m yang hangat dan lembap serta membutuhkan naungan, sehingga tajuknya membantu menutup kembali lahan bekas kebakaran.',
                'steps' => [
                    'Bersihkan sisa kayu terbakar dan buat guludan atau mulsa; hindari pembakaran ulang.',
                    'Siapkan lubang tanam 40 x 40 x 40 cm, isi kompos atau pupuk kandang 3-5 kg, diamkan 2 minggu.',
                    'Tanam pohon naungan tetap 6-12 bulan lebih dahulu dengan jarak lebih lebar.',
                    'Tanam bibit kakao sambung berumur 5-6 bulan saat awal musim hujan, posisinya di bawah naungan.',
                    'Tanam pisang sebagai naungan sementara pada tahun pertama dan kedua.',
                    'Tutup tanah dengan mulsa atau penutup tanah legum di antara barisan untuk menahan erosi.',
                ],
                'spacing' => '3 m x 3 m (±1.111 tanaman/ha)',
                'seedlings_per_ha' => '±1.111 bibit/ha',
                'time_to_canopy' => '±3 tahun sampai tajuk menutup bersama naungan tetap; panen pertama 3-5 tahun',
                'water_need' => '1.500-2.500 mm/tahun; kakao peka kekeringan, beri mulsa dan siram saat kemarau panjang',
                'maintenance' => [
                    'Penyiangan terbatas dengan menyisakan penutup tanah; bersihkan gulma berkayu.',
                    'Pemupukan 2 kali per tahun (N-P-K dan bahan organik) atau sesuai hasil uji tanah.',
                    'Pengendalian penggerek buah kakao lewat sanitasi buah dan pangkas.',
                    'Atur naungan dan pangkas bentuk agar kelembapan tajuk tidak berlebihan.',
                    'Jaga drainase dan periksa erosi alur setelah hujan besar.',
                ],
                'notes' => 'Kakao membutuhkan naungan pada masa muda; lahan terbuka bekas kebakaran membuat bibit stres dan daun terbakar. Tanah bekas kebakaran sering miskin bahan organik, jadi tambahkan kompos dan mulsa secara berkala.',
            ],
        ],
        [
            'id' => 'tea',
            'name' => 'Tea',
            'icon' => '🍵',
            'category' => 'Perkebunan',
            'color' => '#567D46',
            'description' => 'Highland crop requiring cool, humid conditions with acidic soils and adequate rainfall.',
            'requirements' => [
                'elevation' => ['min' => 600, 'max' => 2000, 'optimal' => ['min' => 900, 'max' => 1500]],
                'temperature' => ['min' => 13, 'max' => 25, 'optimal' => ['min' => 16, 'max' => 22]],
                'rainfall' => ['min' => 1500, 'max' => 3000, 'optimal' => ['min' => 2000, 'max' => 2500]],
                'soil_ph' => ['min' => 4.0, 'max' => 6.0, 'optimal' => ['min' => 4.5, 'max' => 5.5]],
                'slope' => ['min' => 0, 'max' => 30, 'optimal' => ['min' => 5, 'max' => 20]],
                'soil_moisture' => ['min' => 40, 'max' => 80, 'optimal' => ['min' => 50, 'max' => 70]],
                'soil_texture' => ['Loam', 'Silt Loam', 'Clay Loam', 'Silty Clay Loam'],
            ],
            'weights' => ['elevation' => 0.22, 'temperature' => 0.20, 'rainfall' => 0.15, 'soil_ph' => 0.15, 'soil_texture' => 0.08, 'slope' => 0.10, 'soil_moisture' => 0.05, 'land_health' => 0.05],
            'agroforestry_potential' => 0.5,
            'market_value' => 'Medium-High',
            'growing_period' => '3 years to first harvest',
            'planting_guide' => [
                'summary' => 'Teh cocok pada lahan miring pasca kebakaran di dataran tinggi 600-2.000 m karena perakarannya dalam dan penanaman rapat cepat menutup permukaan tanah.',
                'steps' => [
                    'Bersihkan sisa tumbuhan terbakar; pada lereng di atas 8° buat teras kontur atau rorak.',
                    'Buat lubang atau lurikan tanam sedalam 30-40 cm, campur kompos dan dolomit bila pH di bawah 4,5.',
                    'Tanam bibit teh berumur 9-12 bulan atau stek berakar pada awal musim hujan.',
                    'Pangkas bibit setinggi 20-30 cm setelah tanam untuk merangsang percabangan.',
                    'Tanam penutup tanah legum di gawangan untuk menekan erosi pada lereng.',
                ],
                'spacing' => '1,2 m x 0,75 m (±11.111 tanaman/ha)',
                'seedlings_per_ha' => '±11.111 bibit/ha',
                'time_to_canopy' => '±2-3 tahun sampai barisan tajuk menutup; panen pertama 3 tahun',
                'water_need' => '1.500-3.000 mm/tahun; hindari tanah kering lebih dari 2 minggu, beri mulsa di musim kemarau',
                'maintenance' => [
                    'Penyiangan gawangan dan pemangkasan gulma 3-4 kali per tahun.',
                    'Pemupukan nitrogen dengan penutup tanah legum atau pupuk kandang.',
                    'Pangkas pemeliharaan tiap 3-4 tahun untuk menjaga bidang petik.',
                    'Pengendalian penggerek dan penyakit cacar daun melalui sanitasi dan pangkas.',
                ],
                'notes' => 'Pada lereng bekas kebakaran erosi permukaan adalah risiko utama, sehingga teras dan penutup tanah wajib. Teh toleran pH 4,0-6,0 tetapi akar muda peka pada tanah yang mengeras setelah hujan.',
            ],
        ],
        [
            'id' => 'avocado',
            'name' => 'Avocado',
            'icon' => '🥑',
            'category' => 'Hortikultura',
            'color' => '#2E8B57',
            'description' => 'Subtropical fruit tree adaptable to highland conditions, well-drained soils preferred.',
            'requirements' => [
                'elevation' => ['min' => 200, 'max' => 1500, 'optimal' => ['min' => 500, 'max' => 1200]],
                'temperature' => ['min' => 16, 'max' => 28, 'optimal' => ['min' => 20, 'max' => 25]],
                'rainfall' => ['min' => 1000, 'max' => 2000, 'optimal' => ['min' => 1200, 'max' => 1800]],
                'soil_ph' => ['min' => 5.0, 'max' => 7.0, 'optimal' => ['min' => 5.5, 'max' => 6.5]],
                'slope' => ['min' => 0, 'max' => 25, 'optimal' => ['min' => 3, 'max' => 15]],
                'soil_moisture' => ['min' => 25, 'max' => 65, 'optimal' => ['min' => 35, 'max' => 55]],
                'soil_texture' => ['Sandy Loam', 'Loam', 'Silt Loam', 'Sandy Clay Loam'],
            ],
            'weights' => ['elevation' => 0.18, 'temperature' => 0.20, 'rainfall' => 0.15, 'soil_ph' => 0.12, 'soil_texture' => 0.12, 'slope' => 0.10, 'soil_moisture' => 0.08, 'land_health' => 0.05],
            'agroforestry_potential' => 0.75,
            'market_value' => 'High',
            'growing_period' => '3-5 years to first harvest',
            'planting_guide' => [
                'summary' => 'Alpukat cocok pada lahan pasca kebakaran di ketinggian 200-1.500 m karena akarnya dalam dan tajuknya lebat membantu menstabilkan tanah lereng.',
                'steps' => [
                    'Bersihkan kayu terbakar dan sisakan mulsa; pada lereng buat teras individu atau lubang yang menahan air hujan.',
                    'Buat lubang tanam 60 x 60 x 60 cm karena akar tunggang alpukat dalam.',
                    'Isi lubang dengan kompos atau pupuk kandang, lalu diamkan 2-4 minggu.',
                    'Tanam bibit sambung berumur 6-12 bulan pada awal musim hujan tanpa menimbun leher akar.',
                    'Beri mulsa selebar 1 m dan naungan sementara bila lahan sangat terbuka.',
                ],
                'spacing' => '6 m x 6 m (±278 tanaman/ha)',
                'seedlings_per_ha' => '±278 bibit/ha',
                'time_to_canopy' => '±3 tahun sampai tajuk saling menyentuh; panen pertama 3-5 tahun',
                'water_need' => '1.000-2.000 mm/tahun; siram pada 2 tahun pertama saat kemarau dan pastikan drainase lancar',
                'maintenance' => [
                    'Penyiangan melingkar di sekitar batang dan pembaruan mulsa setiap tahun.',
                    'Pemupukan N-P-K bertahap 2-3 kali per tahun seiring pertumbuhan tajuk.',
                    'Pengendalian penyakit akar dengan drainase baik dan sanitasi.',
                    'Pengendalian penggerek batang dan kutu daun serta kebersihan pangkasan.',
                ],
                'notes' => 'Alpukat sangat peka genangan dan pH rendah; pada tanah bekas kebakaran pastikan drainase dan pecahkan lapisan padat. Hindari tumpangsari tanaman pangan yang mengganggu perakaran muda.',
            ],
        ],
        [
            'id' => 'banana',
            'name' => 'Banana',
            'icon' => '🍌',
            'category' => 'Hortikultura',
            'color' => '#FFD700',
            'description' => 'Fast-growing tropical fruit, versatile altitude range, high water needs.',
            'requirements' => [
                'elevation' => ['min' => 0, 'max' => 1200, 'optimal' => ['min' => 100, 'max' => 800]],
                'temperature' => ['min' => 20, 'max' => 35, 'optimal' => ['min' => 24, 'max' => 30]],
                'rainfall' => ['min' => 1500, 'max' => 3000, 'optimal' => ['min' => 2000, 'max' => 2500]],
                'soil_ph' => ['min' => 5.5, 'max' => 7.5, 'optimal' => ['min' => 6.0, 'max' => 7.0]],
                'slope' => ['min' => 0, 'max' => 15, 'optimal' => ['min' => 0, 'max' => 8]],
                'soil_moisture' => ['min' => 40, 'max' => 85, 'optimal' => ['min' => 55, 'max' => 75]],
                'soil_texture' => ['Loam', 'Clay Loam', 'Silt Loam', 'Silty Clay Loam'],
            ],
            'weights' => ['elevation' => 0.12, 'temperature' => 0.22, 'rainfall' => 0.18, 'soil_ph' => 0.10, 'soil_texture' => 0.10, 'slope' => 0.12, 'soil_moisture' => 0.10, 'land_health' => 0.06],
            'agroforestry_potential' => 0.8,
            'market_value' => 'Medium',
            'growing_period' => '9-12 months to first harvest',
            'planting_guide' => [
                'summary' => 'Pisang tumbuh cepat dan menutup tanah dalam waktu kurang dari setahun, sehingga berguna menahan erosi dan memberi naungan sementara pada lahan pasca kebakaran.',
                'steps' => [
                    'Bersihkan sisa batang terbakar dan ratakan bekas lubang; buat guludan bila drainase buruk.',
                    'Buat lubang tanam 40 x 40 x 40 cm dan isi kompos atau pupuk kandang 3-5 kg.',
                    'Pilih bibit anakan muda yang sehat atau bibit kultur jaringan.',
                    'Tanam pada awal musim hujan dengan posisi anakan 10-15 cm di atas permukaan tanah.',
                    'Mulsa tebal dari sisa organik untuk menjaga kelembapan dan menekan gulma.',
                ],
                'spacing' => '3 m x 3 m (±1.111 tanaman/ha)',
                'seedlings_per_ha' => '±1.111 bibit/ha',
                'time_to_canopy' => '±6-9 bulan sampai tajuk menutup; panen pertama 9-12 bulan',
                'water_need' => '1.500-3.000 mm/tahun; kebutuhan air tinggi, beri mulsa dan siram saat kemarau panjang',
                'maintenance' => [
                    'Penyiangan dan pembersihan anakan, sisakan hanya 1-2 anakan per rumpun.',
                    'Pemberian pupuk kandang tiap 3-6 bulan disertai N-P-K bertahap.',
                    'Pengendalian layu dan bercak daun melalui sanitasi serta drainase.',
                    'Dongkel dan mulsa sisa batang setelah panen untuk menjaga bahan organik.',
                ],
                'notes' => 'Batang muda pisang mudah rebah pada lahan terbuka bekas kebakaran, jadi beri naungan atau tanaman pelindung angin. Jangan tanam pada lereng di atas 15° tanpa teras karena perakaran pisang dangkal.',
            ],
        ],
        [
            'id' => 'rice',
            'name' => 'Rice (Padi)',
            'icon' => '🌾',
            'category' => 'Pangan',
            'color' => '#C4B454',
            'description' => 'Staple crop requiring flat terrain, standing water capability, and warm temperatures.',
            'requirements' => [
                'elevation' => ['min' => 0, 'max' => 750, 'optimal' => ['min' => 0, 'max' => 400]],
                'temperature' => ['min' => 22, 'max' => 35, 'optimal' => ['min' => 25, 'max' => 32]],
                'rainfall' => ['min' => 1200, 'max' => 3000, 'optimal' => ['min' => 1500, 'max' => 2500]],
                'soil_ph' => ['min' => 5.0, 'max' => 7.5, 'optimal' => ['min' => 5.5, 'max' => 7.0]],
                'slope' => ['min' => 0, 'max' => 8, 'optimal' => ['min' => 0, 'max' => 3]],
                'soil_moisture' => ['min' => 60, 'max' => 95, 'optimal' => ['min' => 70, 'max' => 90]],
                'soil_texture' => ['Clay', 'Clay Loam', 'Silty Clay', 'Silty Clay Loam'],
            ],
            'weights' => ['elevation' => 0.10, 'temperature' => 0.18, 'rainfall' => 0.15, 'soil_ph' => 0.10, 'soil_texture' => 0.12, 'slope' => 0.20, 'soil_moisture' => 0.10, 'land_health' => 0.05],
            'agroforestry_potential' => 0.1,
            'market_value' => 'Medium',
            'growing_period' => '3-4 months per season',
            'planting_guide' => [
                'summary' => 'Padi cocok pada lahan datar bekas kebakaran dengan tanah berat yang dapat digenangi; sawah beririgasi menekan erosi dan mempercepat pemulihan kesuburan tanah.',
                'steps' => [
                    'Bersihkan sisa terbakar dan jerami lama, lalu perbaiki pematang serta saluran air.',
                    'Olah tanah secara minimal, ratakan permukaan agar pengairan merata.',
                    'Siapkan persemaian, atau gunakan tanam benih langsung bila pengairan terbatas.',
                    'Tanam bibit berumur 15-21 hari sebanyak 2-3 bibit per rumpun pada awal musim hujan.',
                    'Airi berselang selama fase anakan, lalu genangi pada fase berbunga.',
                ],
                'spacing' => '0,25 m x 0,25 m (±160.000 rumpun/ha)',
                'seedlings_per_ha' => '±160.000 rumpun/ha (2-3 bibit per rumpun)',
                'time_to_canopy' => '±1,5-2 bulan kanopi menutup rapat; panen 3-4 bulan per musim',
                'water_need' => '1.200-3.000 mm/tahun; kebutuhan air tinggi saat penggenangan sehingga cocok pada sawah beririgasi',
                'maintenance' => [
                    'Penyiangan gulma 2-3 kali pada fase anakan; penggenangan juga menekan gulma.',
                    'Pemupukan N-P-K bertahap 2-3 kali disertai penambahan bahan organik atau jerami kompos.',
                    'Pengendalian tikus, wereng, dan penggerek batang dengan menjaga pematang dan pengairan.',
                    'Pengaturan air: kurangi air menjelang panen agar pemasakan merata.',
                ],
                'notes' => 'Padi membutuhkan tanah datar (lereng ≤8°) dan tidak cocok pada lereng bekas kebakaran yang rawan erosi. Pastikan pengairan cukup; pH tanah bekas kebakaran dapat menurun sehingga pengapuran mungkin diperlukan.',
            ],
        ],
        [
            'id' => 'maize',
            'name' => 'Maize (Jagung)',
            'icon' => '🌽',
            'category' => 'Pangan',
            'color' => '#DAA520',
            'description' => 'Versatile cereal crop adaptable to various elevations, moderate water needs.',
            'requirements' => [
                'elevation' => ['min' => 0, 'max' => 1500, 'optimal' => ['min' => 200, 'max' => 1000]],
                'temperature' => ['min' => 18, 'max' => 32, 'optimal' => ['min' => 22, 'max' => 28]],
                'rainfall' => ['min' => 800, 'max' => 2000, 'optimal' => ['min' => 1000, 'max' => 1500]],
                'soil_ph' => ['min' => 5.5, 'max' => 7.5, 'optimal' => ['min' => 6.0, 'max' => 7.0]],
                'slope' => ['min' => 0, 'max' => 15, 'optimal' => ['min' => 0, 'max' => 8]],
                'soil_moisture' => ['min' => 30, 'max' => 65, 'optimal' => ['min' => 40, 'max' => 55]],
                'soil_texture' => ['Loam', 'Sandy Loam', 'Silt Loam', 'Clay Loam'],
            ],
            'weights' => ['elevation' => 0.12, 'temperature' => 0.20, 'rainfall' => 0.18, 'soil_ph' => 0.12, 'soil_texture' => 0.10, 'slope' => 0.12, 'soil_moisture' => 0.10, 'land_health' => 0.06],
            'agroforestry_potential' => 0.3,
            'market_value' => 'Medium',
            'growing_period' => '3-4 months',
            'planting_guide' => [
                'summary' => 'Jagung cepat menghasilkan biomassa penutup tanah dan toleran pada rentang elevasi luas, sehingga cocok sebagai tanaman tahap awal pemulihan lahan pasca kebakaran.',
                'steps' => [
                    'Bersihkan sisa terbakar lalu buat lubang tanam dengan tugal atau olah tanah minimum.',
                    'Pada lereng, tanam mengikuti kontur dengan guludan untuk menahan erosi.',
                    'Tanam 2 butir benih per lubang, lalu sisakan 1 tanaman terbaik setelah 2 minggu.',
                    'Beri kompos atau pupuk organik di lubang tanam sebelum benih ditutup.',
                    'Sisipkan tanaman penutup tanah legum di antara barisan bila jarak memungkinkan.',
                ],
                'spacing' => '0,75 m x 0,20 m (±66.667 tanaman/ha)',
                'seedlings_per_ha' => '±66.667 tanaman/ha (dari 2 butir per lubang, disisakan 1)',
                'time_to_canopy' => '±1,5 bulan kanopi barisan menutup; panen 3-4 bulan',
                'water_need' => '800-2.000 mm/tahun; peka kekeringan saat berbunga, siram bila kemarau panjang',
                'maintenance' => [
                    'Penyiangan 1-2 kali pada 3-5 minggu setelah tanam.',
                    'Pemupukan N-P-K saat tanam dan saat tanaman setinggi lutut.',
                    'Pengendalian penggerek batang dan ulat tongkol dengan pemantauan rutin.',
                    'Pembumbunan tanah untuk memperkuat batang dan menutup gulma.',
                ],
                'notes' => 'Tanpa mulsa atau penutup tanah, barisan jagung yang bersih mempercepat erosi pada lereng bekas kebakaran. Sisakan sisa jerami sebagai mulsa dan jangan dibakar.',
            ],
        ],
        [
            'id' => 'rubber',
            'name' => 'Rubber (Karet)',
            'icon' => '🌳',
            'category' => 'Perkebunan',
            'color' => '#4A6741',
            'description' => 'Industrial tree crop for latex production, requires warm lowland conditions.',
            'requirements' => [
                'elevation' => ['min' => 0, 'max' => 600, 'optimal' => ['min' => 50, 'max' => 400]],
                'temperature' => ['min' => 22, 'max' => 33, 'optimal' => ['min' => 25, 'max' => 30]],
                'rainfall' => ['min' => 1800, 'max' => 3500, 'optimal' => ['min' => 2000, 'max' => 3000]],
                'soil_ph' => ['min' => 4.0, 'max' => 6.5, 'optimal' => ['min' => 4.5, 'max' => 5.5]],
                'slope' => ['min' => 0, 'max' => 20, 'optimal' => ['min' => 2, 'max' => 12]],
                'soil_moisture' => ['min' => 40, 'max' => 80, 'optimal' => ['min' => 50, 'max' => 70]],
                'soil_texture' => ['Loam', 'Clay Loam', 'Sandy Clay Loam', 'Sandy Loam'],
            ],
            'weights' => ['elevation' => 0.15, 'temperature' => 0.22, 'rainfall' => 0.18, 'soil_ph' => 0.12, 'soil_texture' => 0.10, 'slope' => 0.10, 'soil_moisture' => 0.08, 'land_health' => 0.05],
            'agroforestry_potential' => 0.7,
            'market_value' => 'Medium-High',
            'growing_period' => '5-7 years to first tapping',
            'planting_guide' => [
                'summary' => 'Karet cocok untuk lahan bekas kebakaran dataran rendah dengan curah hujan tinggi; tajuknya yang lebar menutup lahan dan mengembalikan bahan organik secara bertahap.',
                'steps' => [
                    'Bersihkan kayu terbakar dan susun sebagai guludan kontur; jangan biarkan tanah terbuka.',
                    'Buat lubang tanam 60 x 60 x 60 cm dengan kompos atau pupuk kandang 3-5 kg.',
                    'Tanam bibit okulasi berumur 1-2 tahun pada awal musim hujan.',
                    'Siapkan guludan dan drainase baik karena karet tidak tahan genangan.',
                    'Tanam penutup tanah legum di antara barisan untuk menekan erosi dan gulma.',
                ],
                'spacing' => '6 m x 3 m (±556 tanaman/ha)',
                'seedlings_per_ha' => '±556 bibit/ha',
                'time_to_canopy' => '±4-5 tahun sampai tajuk saling menutup; sadap pertama 5-7 tahun',
                'water_need' => '1.800-3.500 mm/tahun; kebutuhan air tinggi, beri mulsa pada kemarau panjang',
                'maintenance' => [
                    'Penyiangan barisan tanaman muda 3-4 kali per tahun dan pembersihan penutup tanah secara bergantian.',
                    'Pemupukan N-P-K bertahap sampai masa sadap disertai bahan organik setiap tahun.',
                    'Pengendalian penyakit gugur daun dan akar putih melalui sanitasi.',
                    'Pangkas cabang bawah pada 1-3 tahun pertama agar batang sadap tumbuh lurus.',
                ],
                'notes' => 'Bekas kebakaran sering menyisakan pH rendah dan lapisan padat, jadi uji tanah dan perbaiki drainase sebelum tanam. Hindari genangan karena akar karet mudah membusuk.',
            ],
        ],
        [
            'id' => 'oil-palm',
            'name' => 'Oil Palm (Kelapa Sawit)',
            'icon' => '🌴',
            'category' => 'Perkebunan',
            'color' => '#8B8000',
            'description' => 'Major plantation crop, requires flat lowland terrain with high rainfall.',
            'requirements' => [
                'elevation' => ['min' => 0, 'max' => 500, 'optimal' => ['min' => 0, 'max' => 300]],
                'temperature' => ['min' => 24, 'max' => 35, 'optimal' => ['min' => 26, 'max' => 32]],
                'rainfall' => ['min' => 1800, 'max' => 3500, 'optimal' => ['min' => 2000, 'max' => 3000]],
                'soil_ph' => ['min' => 4.0, 'max' => 6.5, 'optimal' => ['min' => 4.5, 'max' => 5.5]],
                'slope' => ['min' => 0, 'max' => 12, 'optimal' => ['min' => 0, 'max' => 6]],
                'soil_moisture' => ['min' => 40, 'max' => 80, 'optimal' => ['min' => 50, 'max' => 70]],
                'soil_texture' => ['Clay Loam', 'Loam', 'Silty Clay Loam', 'Clay'],
            ],
            'weights' => ['elevation' => 0.15, 'temperature' => 0.20, 'rainfall' => 0.18, 'soil_ph' => 0.10, 'soil_texture' => 0.10, 'slope' => 0.15, 'soil_moisture' => 0.07, 'land_health' => 0.05],
            'agroforestry_potential' => 0.2,
            'market_value' => 'High',
            'growing_period' => '3-4 years to first harvest',
            'planting_guide' => [
                'summary' => 'Kelapa sawit menutup lahan dengan cepat pada dataran rendah basah, tetapi hanya cocok bila topografi datar dan drainase baik sehingga risiko erosi bekas kebakaran dapat dikelola.',
                'steps' => [
                    'Bersihkan sisa terbakar dan tata lahan; pada lereng 6-12° buat teras individu untuk setiap pohon.',
                    'Buat lubang tanam 60 x 60 x 60 cm dan isi kompos atau pupuk kandang.',
                    'Tanam bibit berumur 10-14 bulan pada awal musim hujan.',
                    'Tanam penutup tanah legum di antara barisan untuk menutup permukaan tanah.',
                    'Bentuk lorong panen dan petakan pohon untuk memudahkan pemeliharaan.',
                ],
                'spacing' => '9 m x 9 m (±123 tanaman/ha)',
                'seedlings_per_ha' => '±123 bibit/ha',
                'time_to_canopy' => '±3 tahun sampai tajuk saling menutup; panen pertama 3-4 tahun',
                'water_need' => '1.800-3.500 mm/tahun; hindari defisit air panjang dan jaga muka air tanah',
                'maintenance' => [
                    'Penyiangan dan pembentukan piringan (circle) tiap pohon 2-4 kali per tahun.',
                    'Pemupukan N-P-K-Mg bertahap 2 kali per tahun disertai bahan organik dari pelepah.',
                    'Pengendalian kumbang dan ulat pemakan daun melalui sanitasi.',
                    'Buang tunas liar serta potong pelepah kering dan susun sebagai mulsa piringan.',
                ],
                'notes' => 'Sawit menutup permukaan cepat sehingga drainase harus direncanakan sebelum tanam; pada lahan gambut atau berlereng bekas kebakaran risiko erosi dan penurunan muka tanah tinggi. Jangan tanam pada lereng di atas 12° tanpa teras.',
            ],
        ],
    ];

    /**
     * Every crop in the catalog.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return self::CROPS;
    }

    /**
     * The number of crops the engine scores.
     */
    public static function count(): int
    {
        return count(self::CROPS);
    }

    /**
     * A single crop, or null when the id is unknown.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        foreach (self::CROPS as $crop) {
            if ($crop['id'] === $id) {
                return $crop;
            }
        }

        return null;
    }
}
