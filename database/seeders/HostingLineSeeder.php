<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

/**
 * Ready-written hosting lines for the shared library.
 *
 * The hosting line is the sentence that introduces the couple, and it is the
 * case the placeholders exist for: the same wording every time bar the names,
 * so it is written once and filled in per invitation.
 *
 * Each entry is one library row carrying all three languages. They are
 * variations on who is doing the inviting — both families, one family, the
 * couple themselves — because that is the choice a studio actually makes per
 * invitation, and having the alternatives written down beats retyping the
 * same sentence with two words changed.
 *
 * Shared rather than filed under a design: a hosting line is wording, and no
 * design owns it.
 *
 * Run on its own with:
 *   php artisan db:seed --class=HostingLineSeeder
 */
class HostingLineSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->lines() as $line) {
            Asset::updateOrCreate(
                ['kind' => Asset::KIND_TEXT, 'name' => $line['name']],
                [
                    'base_template' => null,
                    'field' => 'hosts',
                    'body' => json_encode($line['body'], JSON_UNESCAPED_UNICODE),
                    'description' => $line['note'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return array<int, array{name: string, note: string, body: array<string, string>}>
     */
    private function lines(): array
    {
        return [
            /* ── who is inviting ──────────────────────────────────────── */

            [
                'name' => 'Both families — formal',
                'note' => 'The standard opening. Both families named, classical phrasing.',
                'body' => [
                    'ar' => 'تتشرف عائلة {groom} وعائلة {bride} بدعوتكم لحضور حفل زفاف ولديهما',
                    'ku' => 'خێزانی {groom} و خێزانی {bride} بە خۆشحاڵییەوە بانگهێشتتان دەکەن بۆ ئاهەنگی هاوسەرگیری منداڵەکانیان',
                    'en' => 'The families of {groom} and {bride} request the honour of your presence at the wedding of their children',
                ],
            ],

            [
                'name' => 'Both families — warm',
                'note' => 'Softer than the formal version. For a less traditional invitation.',
                'body' => [
                    'ar' => 'بقلوب مفعمة بالفرح، تدعوكم عائلة {groom} وعائلة {bride} لمشاركتهم أجمل لحظات العمر',
                    'ku' => 'بە دڵێکی پڕ لە خۆشییەوە، خێزانی {groom} و خێزانی {bride} بانگهێشتتان دەکەن بۆ بەشداری کردن لە خۆشترین ساتەکانی ژیانیان',
                    'en' => 'With hearts full of joy, the families of {groom} and {bride} invite you to share the happiest day of their lives',
                ],
            ],

            [
                'name' => 'The couple themselves',
                'note' => 'The couple invites, not the families. For an invitation sent by them.',
                'body' => [
                    'ar' => 'يسعدنا نحن {groom} و{bride} أن ندعوكم لمشاركتنا فرحة زفافنا',
                    'ku' => 'ئێمە {groom} و {bride} بە خۆشحاڵییەوە بانگهێشتتان دەکەین بۆ بەشداری کردن لە ئاهەنگی هاوسەرگیریمان',
                    'en' => 'We, {groom} and {bride}, would be delighted to have you with us as we celebrate our wedding',
                ],
            ],

            [
                'name' => 'Couple with their families',
                'note' => 'Both at once — the couple inviting, the families acknowledged.',
                'body' => [
                    'ar' => 'يتشرف {groom} و{bride} مع عائلتيهما بدعوتكم لحضور حفل زفافهما',
                    'ku' => '{groom} و {bride} لەگەڵ خێزانەکانیان بانگهێشتتان دەکەن بۆ ئاهەنگی هاوسەرگیرییان',
                    'en' => 'Together with their families, {groom} and {bride} invite you to celebrate their marriage',
                ],
            ],

            [
                'name' => 'Groom’s family hosts',
                'note' => 'When one family is doing the inviting.',
                'body' => [
                    'ar' => 'تتشرف عائلة {groom} بدعوتكم لحضور حفل زفاف ولدها على الآنسة {bride}',
                    'ku' => 'خێزانی {groom} بانگهێشتتان دەکات بۆ ئاهەنگی هاوسەرگیری کوڕەکەیان لەگەڵ {bride}',
                    'en' => 'The family of {groom} requests the honour of your presence at his marriage to {bride}',
                ],
            ],

            [
                'name' => 'Bride’s family hosts',
                'note' => 'The same, from the other side.',
                'body' => [
                    'ar' => 'تتشرف عائلة {bride} بدعوتكم لحضور حفل زفاف ابنتها على السيد {groom}',
                    'ku' => 'خێزانی {bride} بانگهێشتتان دەکات بۆ ئاهەنگی هاوسەرگیری کچەکەیان لەگەڵ {groom}',
                    'en' => 'The family of {bride} requests the honour of your presence at her marriage to {groom}',
                ],
            ],

            /* ── religious openings ───────────────────────────────────── */

            [
                'name' => 'By God’s grace',
                'note' => 'Religious opening before the names. Common on Iraqi invitations.',
                'body' => [
                    'ar' => 'بفضل الله وتوفيقه، يتم بحمده زفاف {groom} على {bride}، ويسرنا دعوتكم لمشاركتنا هذه المناسبة',
                    'ku' => 'بە فەزڵی خودا، ئاهەنگی هاوسەرگیری {groom} و {bride} بەڕێوە دەچێت، و بە خۆشحاڵییەوە بانگهێشتتان دەکەین',
                    'en' => 'By the grace of God, {groom} and {bride} are to be married, and we would be honoured by your presence',
                ],
            ],

            [
                'name' => 'On a blessed night',
                'note' => 'Warmer religious framing, less formal than “By God’s grace”.',
                'body' => [
                    'ar' => 'في ليلة مباركة، نضع بين أيديكم دعوتنا لحضور زفاف {groom} و{bride}',
                    'ku' => 'لە شەوێکی پیرۆزدا، بانگهێشتنامەکەمان پێشکەشتان دەکەین بۆ ئاهەنگی هاوسەرگیری {groom} و {bride}',
                    'en' => 'On a blessed evening, we invite you to the wedding of {groom} and {bride}',
                ],
            ],

            /* ── short ────────────────────────────────────────────────── */

            [
                'name' => 'Short — share our joy',
                'note' => 'One line. For a design where the hosting line should not dominate.',
                'body' => [
                    'ar' => 'شاركونا فرحتنا',
                    'ku' => 'بەشداری خۆشیمان بکەن',
                    'en' => 'Share our joy',
                ],
            ],

            [
                'name' => 'Short — with our families',
                'note' => 'Short, but says who is inviting.',
                'body' => [
                    'ar' => 'بدعوة من عائلتَي {groom} و{bride}',
                    'ku' => 'بە بانگهێشتی خێزانەکانی {groom} و {bride}',
                    'en' => 'At the invitation of the families of {groom} and {bride}',
                ],
            ],

            /* ── with the details written in ──────────────────────────── */

            [
                'name' => 'With date and venue',
                'note' => 'Carries the date and place in the sentence. Both resolve exactly as the invitation prints them.',
                'body' => [
                    'ar' => 'تتشرف عائلة {groom} وعائلة {bride} بدعوتكم لحضور حفل الزفاف يوم {date} في {venue}',
                    'ku' => 'خێزانی {groom} و خێزانی {bride} بانگهێشتتان دەکەن بۆ ئاهەنگی هاوسەرگیری لە {date} لە {venue}',
                    'en' => 'The families of {groom} and {bride} invite you to their wedding on {date} at {venue}',
                ],
            ],

            [
                'name' => 'With date, time and venue',
                'note' => 'Everything in one sentence, for a design with no separate detail panel.',
                'body' => [
                    'ar' => 'ندعوكم لحضور حفل زفاف {groom} و{bride}، يوم {date} الساعة {time} في {venue} — {city}',
                    'ku' => 'بانگهێشتتان دەکەین بۆ ئاهەنگی هاوسەرگیری {groom} و {bride}، لە {date} کاتژمێر {time} لە {venue} — {city}',
                    'en' => 'You are invited to the wedding of {groom} and {bride} on {date} at {time}, {venue} — {city}',
                ],
            ],

            /* ── classic English-led wording ──────────────────────────── */

            [
                'name' => 'Request the pleasure',
                'note' => 'The classic Western formula, with Arabic and Kurdish equivalents.',
                'body' => [
                    'ar' => 'نلتمس حضوركم الكريم في حفل زفاف {groom} و{bride}',
                    'ku' => 'بە ڕێزەوە داوای ئامادەبوونتان دەکەین لە ئاهەنگی هاوسەرگیری {groom} و {bride}',
                    'en' => 'Request the pleasure of your company at the marriage of {groom} and {bride}',
                ],
            ],

            [
                'name' => 'A new beginning',
                'note' => 'For a couple who want something less formal than the family wording.',
                'body' => [
                    'ar' => 'بداية جديدة تجمعنا، ويسعدنا أن تكونوا شهودًا عليها — {groom} و{bride}',
                    'ku' => 'دەستپێکێکی نوێ کۆمان دەکاتەوە، و خۆشحاڵ دەبین ئێوە شایەتی بن — {groom} و {bride}',
                    'en' => 'A new beginning brings us together, and we would love you to witness it — {groom} and {bride}',
                ],
            ],
        ];
    }
}
