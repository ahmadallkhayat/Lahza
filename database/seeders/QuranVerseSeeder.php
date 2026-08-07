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
 * rather than typed, so no diacritic rests on anyone\''s transcription. Have
 * someone who knows the text read it before publishing regardless: no
 * automated source removes the need for that, and a misplaced mark on a
 * wedding invitation is not a small thing.
 *
 * The English is Marmaduke Pickthall's 1930 rendering, which is out of
 * copyright. Swap it for another if you prefer — the field is free text and
 * nothing here depends on which translation it holds.
 *
 * Kurdish carries none: the verse is read in Arabic on a Kurdish invitation,
 * which is what lang/ku already assumes. Fill it in if you would rather it
 * printed one; an empty translation just falls back to the design's wording.
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
                    'translation' => $verse['translation'],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'And of His signs is this: He created for you helpmeets from yourselves that ye might find rest in them, and He ordained between you love and mercy. Lo! herein indeed are portents for folk who reflect.',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'And who say: Our Lord! Vouchsafe us comfort of our wives and of our offspring, and make us patterns for (all) those who ward off (evil).',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'And marry such of you as are solitary and the pious of your slaves and maid-servants. If they be poor, Allah will enrich them of His bounty. Allah is of ample means, Aware.',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'He it is Who did create you from a single soul, and therefrom did make his mate that he might take rest in her. And when he covered her she bore a light burden, and she passed (unnoticed) with it, but when it became heavy they cried unto Allah, their Lord, saying: If thou givest unto us aright we shall be of the thankful.',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'O mankind! Be careful of your duty to your Lord Who created you from a single soul and from it created its mate and from them twain hath spread abroad a multitude of men and women. Be careful of your duty toward Allah in Whom ye claim (your rights) of one another, and toward the wombs (that bare you). Lo! Allah hath been a watcher over you.',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'And Allah hath given you wives of your own kind, and hath given you, from your wives, sons and grandsons, and hath made provision of good things for you. Is it then in vanity that they believe and in the grace of Allah that they disbelieve?',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'And all things We have created by pairs, that haply ye may reflect.',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'How can ye take it (back) after one of you hath gone in unto the other, and they have taken a strong pledge from you?',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'Enter the Garden, ye and your wives, to be made glad.',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'Gardens of Eden which they enter, along with all who do right of their fathers and their helpmeets and their seed. The angels enter unto them from every gate,',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'Our Lord! And make them enter the Gardens of Eden which thou hast promised them, with such of their fathers and their wives and their descendants as do right. Lo! Thou, only Thou, art the Mighty, the Wise.',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'Glory be to Him Who created all the sexual pairs, of that which the earth groweth, and of themselves, and of that which they know not!',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'And that He createth the two spouses, the male and the female,',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'And the believers, men and women, are protecting friends one of another; they enjoin the right and forbid the wrong, and they establish worship and they pay the poor-due, and they obey Allah and His messenger. As for these, Allah will have mercy on them. Lo! Allah is Mighty, Wise.',
                ],
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
                // Pickthall, 1930 — out of copyright. Kurdish carries none:
                // the verse is read in Arabic on a Kurdish invitation.
                'translation' => [
                    'en' => 'And He it is Who hath created man from water, and hath appointed for him kindred by blood and kindred by marriage; for thy Lord is ever Powerful.',
                ],
                'reference' => [
                    'ar' => 'سورة الفرقان — ٥٤',
                    'ku' => 'سورەتی الفرقان — ٥٤',
                    'en' => 'QURAN 25:54',
                ],
            ],
        ];
    }
}
