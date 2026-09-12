export function parseLastEditedHeader(res: Response): number | null {
	const raw = res.headers.get('X-Last-Edited') ?? res.headers.get('x-last-edited');
	if (raw) {
		const n = Number(raw);
		if (!Number.isNaN(n) && n > 0) return n;
	}
	const lm = res.headers.get('Last-Modified');
	if (lm) {
		const t = Date.parse(lm);
		if (!Number.isNaN(t)) return Math.floor(t / 1000);
	}
	return null;
}

export function formatLastEdited(ts: number | null): string {
	if (ts == null) return '';
	const nowSec = Date.now() / 1000;
	const diff = nowSec - ts;
	const absTs = ts * 1000;
	// Relative for < 30 days, absolute otherwise
	const MONTH_SEC = 30 * 24 * 60 * 60;
	if (diff >= 0 && diff < MONTH_SEC) {
		if (diff < 60) return 'just now';
		if (diff < 3600) {
			const m = Math.floor(diff / 60);
			return `${m} minute${m === 1 ? '' : 's'} ago`;
		}
		if (diff < 86400) {
			const h = Math.floor(diff / 3600);
			return `${h} hour${h === 1 ? '' : 's'} ago`;
		}
		if (diff < 604800) {
			const d = Math.floor(diff / 86400);
			return `${d} day${d === 1 ? '' : 's'} ago`;
		}
		const w = Math.floor(diff / 604800);
		// For 1-4 weeks show weeks
		if (w < 5) return `${w} week${w === 1 ? '' : 's'} ago`;
		const d2 = Math.floor(diff / 86400);
		return `${d2} days ago`;
	}
	// Absolute: browser locale, e.g. Sep 12, 2026, 3:42 PM
	try {
		return new Date(absTs).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
	} catch {
		return new Date(absTs).toLocaleString();
	}
}
