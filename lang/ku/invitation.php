<?php

/*
| Sorani Kurdish (ckb) — Arabic script, right to left.
|
| VERIFICATION STATUS
|
| Checked against native-written sources rather than a machine translation.
| Where a string is marked unverified below, it is understandable Kurdish but
| has not been confirmed by a speaker or a dictionary — treat those as the
| remaining risk if an invitation in this language goes to a customer.
|
| Verified — time units and meridiem
|   چرکە / خولەک / کاتژمێر / ڕۆژ and بەیانی / ئێوارە all match Moment.js's
|   `ku` locale (contributed by a native speaker) and ckb.wikipedia.org, whose
|   article on چرکە opens "چرکە یان سانییە ... یەکەی بنچینەی پێوانی کاتە"
|   (a second is the base unit of time measurement) and gives 60 چرکە to a
|   خولەک, 3600 to a کاتژمێر.
|
|   Note our orthography is the modern standard where Moment's is dated: ە
|   (U+06D5) not ه‌+ZWNJ, and ک (U+06A9) not Arabic ك.
|
| Verified — ڕۆژژمێر for calendar
|   The Kurdish calendar app on Google Play is titled "ڕۆژژمێری كوردی".
|
| Verified — بەخێربێن for welcome
|   Standard greeting, in common use across Kurdish media.
|
| CORRECTED — date was wrong
|   This file previously used بەروار for "date". Kurdish language sources call
|   that a mistake: بەروار is a place, mountain and valley name near Amed, not
|   a calendar date. The correct word is ڕێکەوت, per Abdulrahman Zebihî's
|   قامووسی زبانی کوردی. بەروار is nonetheless common in Iraqi Kurdish usage,
|   so this is a contested point — but ڕێکەوت is the form nobody will call
|   wrong, which is what a formal invitation wants.
|   https://rezmanikurde.blogspot.com/2018/12/blog-post.html
|
| UNVERIFIED — still wants a speaker
|   - mute / unmute. کوژاندن (to extinguish, used for switching devices off)
|     and لێدان (to play, as in playing music) are both real usage, but no
|     dictionary confirms the pairing for an audio toggle. Glosbe has no
|     English→Sorani entry for either "mute" or "silent". بێدەنگکردن
|     (literally "making without sound") may read better; left alone rather
|     than swapped on a guess.
|   - tap, programme heading, the "celebration has begun" line, and بۆ زانیاری
|     for enquiries. All grammatical, none confirmed idiomatic.
|
| The Quranic verse stays in Arabic, as it does on Kurdish invitations.
*/

return [
    'loading' => 'تکایە چاوەڕێ بکە',
    'tap' => 'دەست لێبدە بۆ کردنەوە',

    'verse' => 'وَمِنْ آيَاتِهِ أَنْ خَلَقَ لَكُم مِّنْ أَنفُسِكُمْ أَزْوَاجًا لِّتَسْكُنُوا إِلَيْهَا وَجَعَلَ بَيْنَكُم مَّوَدَّةً وَرَحْمَةً',
    'verse_ref' => 'سورەتی الروم — ٢١',
    'verse_translation' => '',

    'date' => 'ڕێکەوت',
    'venue' => 'شوێن',
    'programme' => 'بەرنامەی ئاهەنگ',

    'am' => 'بەیانی',
    'pm' => 'ئێوارە',

    'days' => 'ڕۆژ',
    'hours' => 'کاتژمێر',
    'minutes' => 'خولەک',
    'seconds' => 'چرکە',
    'begun' => 'ئاهەنگ دەستی پێکرد — بەخێربێن',

    'map' => 'شوێن لەسەر نەخشە',
    'calendar' => 'زیادکردن بۆ ڕۆژژمێر',
    'call' => 'بۆ زانیاری',

    'language' => 'زمان',
    'mute' => 'کوژاندنی مۆسیقا',
    'unmute' => 'لێدانی مۆسیقا',

    'brand' => 'لەحزە',
];
