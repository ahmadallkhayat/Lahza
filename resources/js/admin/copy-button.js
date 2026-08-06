/**
 * Copy-to-clipboard button.
 *
 * Registered here rather than written inline in x-data, so the component is
 * not one stray double quote away from spilling itself onto the page.
 *
 * The icon swaps to a tick briefly: without feedback there is no way to tell
 * a successful copy from a dead button. navigator.clipboard needs a secure
 * context — production is HTTPS and localhost counts — so the fallback is
 * only reached over plain http on a remote host.
 */
export default function copyButton(value, label = 'Copy link') {
    return {
        value,
        label,
        copied: false,

        async copy() {
            try {
                await navigator.clipboard.writeText(this.value);
                this.copied = true;
                setTimeout(() => (this.copied = false), 1500);
            } catch (e) {
                window.prompt('Copy this link', this.value);
            }
        },

        get title() {
            return this.copied ? 'Copied' : this.label;
        },
    };
}
