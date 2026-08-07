<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

/**
 * Quranic verses about marriage and spouses, as library assets.
 *
 * Each row carries the Arabic and the surah and ayah as each language writes
 * it, so inserting one on an invitation fills the whole verse block at once.
 *
 * The Arabic is the Tanzil "simple enhanced" text — the same vocalised form the
 * design already shipped — taken from a digital Quran and written out by script
 * rather than typed, so no diacritic rests on anyone's transcription. Have
 * someone who knows the text read it before publishing regardless: no
 * automated source removes the need for that, and a misplaced mark on a
 * wedding invitation is not a small thing.
 *
 * Translations are deliberately left empty. Kurdish prints none by design —
 * the verse is read in Arabic on a Kurdish invitation — and for English the
 * choice of translation is the studio's to make, since the well-known
 * modern ones are separately licensed. An empty translation falls back to the
 * design's own wording, so nothing renders blank in the meantime.
 *
 * Idempotent: matched on kind and name, so running it twice updates rather
 * than duplicates, and re-running restores the canonical text after an edit.
 */
class QuranVerseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->verses() as $verse) {
            Asset::updateOrCreate(
                ['kind' => Asset::KIND_VERSE, 'name' => $verse['name']],
                [
                    // Shared: a verse belongs to no one design.
                    'base_template' => null,
                    'field' => null,
                    'locale' => null,
                    'description' => $verse['description'],
                    'body' => $verse['body'],
                    'reference' => $verse['reference'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function verses(): array
    {
        return [
            [
                'name' => 'Ar-Rum 30:21',
                'description' => 'The classic: mates, tranquillity, affection and mercy.',
                'body' => 'وَمِنْ آيَاتِهِ أَنْ خَلَقَ لَكُم مِّنْ أَنفُسِكُمْ أَزْوَاجًا لِّتَسْكُنُوا إِلَيْهَا وَجَعَلَ بَيْنَكُم مَّوَدَّةً وَرَحْمَةً ۚ إِنَّ فِي ذَٰلِكَ لَآيَاتٍ لِّقَوْمٍ يَتَفَكَّرُونَ',
                'reference' => [
                    'ar' => 'سورة الروم — ٢١',
                    'ku' => 'سورەتی الروم — ٢١',
                    'en' => 'QURAN 30:21',
                ],
            ],
            [
                'name' => 'Al-Furqan 25:74',
                'description' => 'The dua for a spouse and offspring.',
                'body' => 'وَالَّذِينَ يَقُولُونَ رَبَّنَا هَبْ لَنَا مِنْ أَزْوَاجِنَا وَذُرِّيَّاتِنَا قُرَّةَ أَعْيُنٍ وَاجْعَلْنَا لِلْمُتَّقِينَ إِمَامًا',
                'reference' => [
                    'ar' => 'سورة الفرقان — ٧٤',
                    'ku' => 'سورەتی الفرقان — ٧٤',
                    'en' => 'QURAN 25:74',
                ],
            ],
            [
                'name' => 'An-Nur 24:32',
                'description' => 'Marry the unmarried among you.',
                'body' => 'وَأَنكِحُوا الْأَيَامَىٰ مِنكُمْ وَالصَّالِحِينَ مِنْ عِبَادِكُمْ وَإِمَائِكُمْ ۚ إِن يَكُونُوا فُقَرَاءَ يُغْنِهِمُ اللَّهُ مِن فَضْلِهِ ۗ وَاللَّهُ وَاسِعٌ عَلِيمٌ',
                'reference' => [
                    'ar' => 'سورة النور — ٣٢',
                    'ku' => 'سورەتی النور — ٣٢',
                    'en' => 'QURAN 24:32',
                ],
            ],
            [
                'name' => 'Al-A\'raf 7:189',
                'description' => 'Created from one soul its mate, to find rest in her.',
                'body' => '۞ هُوَ الَّذِي خَلَقَكُم مِّن نَّفْسٍ وَاحِدَةٍ وَجَعَلَ مِنْهَا زَوْجَهَا لِيَسْكُنَ إِلَيْهَا ۖ فَلَمَّا تَغَشَّاهَا حَمَلَتْ حَمْلًا خَفِيفًا فَمَرَّتْ بِهِ ۖ فَلَمَّا أَثْقَلَت دَّعَوَا اللَّهَ رَبَّهُمَا لَئِنْ آتَيْتَنَا صَالِحًا لَّنَكُونَنَّ مِنَ الشَّاكِرِينَ',
                'reference' => [
                    'ar' => 'سورة الأعراف — ١٨٩',
                    'ku' => 'سورەتی الأعراف — ١٨٩',
                    'en' => 'QURAN 7:189',
                ],
            ],
            [
                'name' => 'An-Nisa 4:1',
                'description' => 'Created you from one soul and its mate.',
                'body' => 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ يَا أَيُّهَا النَّاسُ اتَّقُوا رَبَّكُمُ الَّذِي خَلَقَكُم مِّن نَّفْسٍ وَاحِدَةٍ وَخَلَقَ مِنْهَا زَوْجَهَا وَبَثَّ مِنْهُمَا رِجَالًا كَثِيرًا وَنِسَاءً ۚ وَاتَّقُوا اللَّهَ الَّذِي تَسَاءَلُونَ بِهِ وَالْأَرْحَامَ ۚ إِنَّ اللَّهَ كَانَ عَلَيْكُمْ رَقِيبًا',
                'reference' => [
                    'ar' => 'سورة النساء — ١',
                    'ku' => 'سورەتی النساء — ١',
                    'en' => 'QURAN 4:1',
                ],
            ],
            [
                'name' => 'An-Nahl 16:72',
                'description' => 'Mates from among yourselves, children and grandchildren.',
                'body' => 'وَاللَّهُ جَعَلَ لَكُم مِّنْ أَنفُسِكُمْ أَزْوَاجًا وَجَعَلَ لَكُم مِّنْ أَزْوَاجِكُم بَنِينَ وَحَفَدَةً وَرَزَقَكُم مِّنَ الطَّيِّبَاتِ ۚ أَفَبِالْبَاطِلِ يُؤْمِنُونَ وَبِنِعْمَتِ اللَّهِ هُمْ يَكْفُرُونَ',
                'reference' => [
                    'ar' => 'سورة النحل — ٧٢',
                    'ku' => 'سورەتی النحل — ٧٢',
                    'en' => 'QURAN 16:72',
                ],
            ],
            [
                'name' => 'Adh-Dhariyat 51:49',
                'description' => 'Of all things We created two mates.',
                'body' => 'وَمِن كُلِّ شَيْءٍ خَلَقْنَا زَوْجَيْنِ لَعَلَّكُمْ تَذَكَّرُونَ',
                'reference' => [
                    'ar' => 'سورة الذاريات — ٤٩',
                    'ku' => 'سورەتی الذاريات — ٤٩',
                    'en' => 'QURAN 51:49',
                ],
            ],
            [
                'name' => 'An-Nisa 4:21',
                'description' => 'A solemn covenant.',
                'body' => 'وَكَيْفَ تَأْخُذُونَهُ وَقَدْ أَفْضَىٰ بَعْضُكُمْ إِلَىٰ بَعْضٍ وَأَخَذْنَ مِنكُم مِّيثَاقًا غَلِيظًا',
                'reference' => [
                    'ar' => 'سورة النساء — ٢١',
                    'ku' => 'سورەتی النساء — ٢١',
                    'en' => 'QURAN 4:21',
                ],
            ],
            [
                'name' => 'Az-Zukhruf 43:70',
                'description' => 'Enter Paradise, you and your spouses.',
                'body' => 'ادْخُلُوا الْجَنَّةَ أَنتُمْ وَأَزْوَاجُكُمْ تُحْبَرُونَ',
                'reference' => [
                    'ar' => 'سورة الزخرف — ٧٠',
                    'ku' => 'سورەتی الزخرف — ٧٠',
                    'en' => 'QURAN 43:70',
                ],
            ],
            [
                'name' => 'Ar-Ra\'d 13:23',
                'description' => 'Gardens they enter with their spouses.',
                'body' => 'جَنَّاتُ عَدْنٍ يَدْخُلُونَهَا وَمَن صَلَحَ مِنْ آبَائِهِمْ وَأَزْوَاجِهِمْ وَذُرِّيَّاتِهِمْ ۖ وَالْمَلَائِكَةُ يَدْخُلُونَ عَلَيْهِم مِّن كُلِّ بَابٍ',
                'reference' => [
                    'ar' => 'سورة الرعد — ٢٣',
                    'ku' => 'سورەتی الرعد — ٢٣',
                    'en' => 'QURAN 13:23',
                ],
            ],
            [
                'name' => 'Ghafir 40:8',
                'description' => 'Admit them, and their spouses, to the gardens.',
                'body' => 'رَبَّنَا وَأَدْخِلْهُمْ جَنَّاتِ عَدْنٍ الَّتِي وَعَدتَّهُمْ وَمَن صَلَحَ مِنْ آبَائِهِمْ وَأَزْوَاجِهِمْ وَذُرِّيَّاتِهِمْ ۚ إِنَّكَ أَنتَ الْعَزِيزُ الْحَكِيمُ',
                'reference' => [
                    'ar' => 'سورة غافر — ٨',
                    'ku' => 'سورەتی غافر — ٨',
                    'en' => 'QURAN 40:8',
                ],
            ],
            [
                'name' => 'Ya-Sin 36:36',
                'description' => 'Exalted is He who created all the pairs.',
                'body' => 'سُبْحَانَ الَّذِي خَلَقَ الْأَزْوَاجَ كُلَّهَا مِمَّا تُنبِتُ الْأَرْضُ وَمِنْ أَنفُسِهِمْ وَمِمَّا لَا يَعْلَمُونَ',
                'reference' => [
                    'ar' => 'سورة يس — ٣٦',
                    'ku' => 'سورەتی يس — ٣٦',
                    'en' => 'QURAN 36:36',
                ],
            ],
            [
                'name' => 'An-Najm 53:45',
                'description' => 'He created the two mates, male and female.',
                'body' => 'وَأَنَّهُ خَلَقَ الزَّوْجَيْنِ الذَّكَرَ وَالْأُنثَىٰ',
                'reference' => [
                    'ar' => 'سورة النجم — ٤٥',
                    'ku' => 'سورەتی النجم — ٤٥',
                    'en' => 'QURAN 53:45',
                ],
            ],
            [
                'name' => 'At-Tawbah 9:71',
                'description' => 'The believing men and women are allies of one another.',
                'body' => 'وَالْمُؤْمِنُونَ وَالْمُؤْمِنَاتُ بَعْضُهُمْ أَوْلِيَاءُ بَعْضٍ ۚ يَأْمُرُونَ بِالْمَعْرُوفِ وَيَنْهَوْنَ عَنِ الْمُنكَرِ وَيُقِيمُونَ الصَّلَاةَ وَيُؤْتُونَ الزَّكَاةَ وَيُطِيعُونَ اللَّهَ وَرَسُولَهُ ۚ أُولَٰئِكَ سَيَرْحَمُهُمُ اللَّهُ ۗ إِنَّ اللَّهَ عَزِيزٌ حَكِيمٌ',
                'reference' => [
                    'ar' => 'سورة التوبة — ٧١',
                    'ku' => 'سورەتی التوبة — ٧١',
                    'en' => 'QURAN 9:71',
                ],
            ],
            [
                'name' => 'Al-Furqan 25:54',
                'description' => 'Made him kin by lineage and by marriage.',
                'body' => 'وَهُوَ الَّذِي خَلَقَ مِنَ الْمَاءِ بَشَرًا فَجَعَلَهُ نَسَبًا وَصِهْرًا ۗ وَكَانَ رَبُّكَ قَدِيرًا',
                'reference' => [
                    'ar' => 'سورة الفرقان — ٥٤',
                    'ku' => 'سورەتی الفرقان — ٥٤',
                    'en' => 'QURAN 25:54',
                ],
            ],
        ];
    }
}
