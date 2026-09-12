/**
 * Parses the note id out of a location hash, same rule as original:
 * "#<id>-<permalink-slug>". No id (bare "#" or empty hash) means
 * "show the default index.md".
 * @param {string} [hash] - defaults to window.location.hash when available
 */
export function getId(hash?: string): string | undefined {
	if (hash === undefined) {
		if (typeof window === 'undefined') return undefined;
		hash = window.location.hash;
	}
	if (!hash) return undefined;
	const dash = hash.indexOf('-');
	return hash.substring(1, dash > 0 ? dash : hash.length);
}
