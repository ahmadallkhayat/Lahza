/**
 * The site settings form.
 *
 * Only the repeatable list needs state — everything else on that page is an
 * ordinary input that posts itself. Kept out of an x-data="..." attribute for
 * the same reason as the rest: a literal double quote inside one closes it
 * early and spills the script onto the page as text, and this particular
 * component would be holding studio-typed prose, which is exactly where a
 * quote turns up.
 */
export default function settingsForm(config) {
    return {
        offers: config.offers.length ? config.offers : [{ title: '', body: '' }],

        add() {
            this.offers.push({ title: '', body: '' });
        },

        remove(index) {
            this.offers.splice(index, 1);

            // Never leave the studio with no way back to a first row: an
            // empty list renders no inputs and no Add button to click.
            if (this.offers.length === 0) this.add();
        },

        move(index, by) {
            const to = index + by;
            if (to < 0 || to >= this.offers.length) return;

            const [row] = this.offers.splice(index, 1);
            this.offers.splice(to, 0, row);
        },
    };
}
