/**
 * A design previewing itself, in a phone, on the landing page.
 *
 * The frame holds the real invitation — the same page a guest receives — so
 * what a visitor watches is the thing being sold rather than a still of its
 * footage. At rest it sits on its sealed opening, which is the invitation's
 * own first screen and needs no explaining. On hover it opens itself and
 * drifts down through the content, then rewinds when the pointer leaves.
 *
 * Nothing here is baked into the base template. The frame is same-origin, so
 * this drives it exactly as a guest would — clicking its opening and scrolling
 * it — which keeps the preview out of the invitation's own code entirely and
 * means a new design gets this for free.
 *
 * The page is only fetched when it is going to be watched: six invitations,
 * each with a video, is a great deal to load for a visitor who scrolls
 * straight past.
 */

/** How long after the pointer leaves before the frame is rewound. */
const REWIND_AFTER = 400;

/** How far down the tour drifts, as a share of the scrollable height. */
const TOUR_DEPTH = 0.75;

export default function designPreview(config) {
    return {
        url: config.url,
        loaded: false,
        ready: false,
        hovering: false,

        init() {
            // Fetch when it comes near the viewport rather than on hover, so
            // that by the time a pointer arrives the frame is already up and
            // the design opens at once instead of after a page load.
            //
            // Falls back to loading immediately where there is no observer,
            // which is a browser old enough that one more request is the
            // least of it.
            if (! ('IntersectionObserver' in window)) {
                this.load();

                return;
            }

            this.observer = new IntersectionObserver(
                (entries) => {
                    if (! entries.some((entry) => entry.isIntersecting)) return;

                    this.load();
                    this.observer.disconnect();
                },
                { rootMargin: '400px' },
            );

            this.observer.observe(this.$root);
        },

        destroy() {
            this.observer?.disconnect();
            clearTimeout(this.rewindTimer);
            cancelAnimationFrame(this.frame);
        },

        load() {
            if (this.loaded) return;
            this.loaded = true;

            this.$refs.frame.src = this.url;
        },

        onLoad() {
            this.ready = true;

            // The pointer can arrive while the page is still loading, and the
            // hover that would have started the tour has already happened.
            if (this.hovering) this.play();
        },

        enter() {
            this.hovering = true;
            clearTimeout(this.rewindTimer);

            this.load();

            if (this.ready) this.play();
        },

        leave() {
            this.hovering = false;
            cancelAnimationFrame(this.frame);

            // A moment's grace: a pointer crossing the card on its way
            // somewhere else should not cost a reload.
            this.rewindTimer = setTimeout(() => this.rewind(), REWIND_AFTER);
        },

        /**
         * Open the invitation and drift down it.
         *
         * The opening is opened by clicking it, because that is what it is
         * waiting for — the design's own script decides everything after,
         * exactly as it would for a guest. The video is muted in the frame,
         * so it plays without a gesture; music does not, which is right for
         * something that starts because a pointer moved.
         */
        play() {
            const win = this.$refs.frame.contentWindow;
            const doc = this.$refs.frame.contentDocument;

            if (! win || ! doc) return;

            doc.getElementById('curtain')?.click();

            // Let the opening have the screen to itself before anything
            // starts moving underneath it.
            this.frame = requestAnimationFrame(() => {
                setTimeout(() => this.tour(win, doc), 2200);
            });
        },

        /**
         * A slow drift down the invitation, driven frame by frame.
         *
         * scroll-behavior: smooth would be one line, but its pace is the
         * browser's and it is built for getting somewhere — this is meant to
         * read as looking rather than as arriving, so it is stepped by hand
         * at a speed that can be set.
         */
        tour(win, doc) {
            if (! this.hovering) return;

            const step = () => {
                if (! this.hovering) return;

                const room = doc.documentElement.scrollHeight - win.innerHeight;

                if (room <= 0) return;

                const target = room * TOUR_DEPTH;
                const next = Math.min(win.scrollY + 0.9, target);

                win.scrollTo(0, next);

                if (next < target) this.frame = requestAnimationFrame(step);
            };

            this.frame = requestAnimationFrame(step);
        },

        /**
         * Back to the sealed opening.
         *
         * A reload rather than a scroll to the top: the invitation has been
         * opened and revealed by now, and scrolling back would leave the card
         * resting on a design that has already been given away. The page is in
         * cache, so the second load is cheap.
         */
        rewind() {
            if (! this.ready || this.hovering) return;

            this.ready = false;
            this.$refs.frame.contentWindow?.location.replace(this.url);
        },
    };
}
