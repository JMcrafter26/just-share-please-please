/** Base URL for the self-hosted Cap endpoints (same origin, routed via .htaccess) */
export const CAP_ENDPOINT = './cap/';

export function isCapRateLimitError(err: unknown): boolean {
	return /rate limit|too many challenge requests/i.test(String((err as Error)?.message ?? err));
}

export function capRateLimitMessage(): string {
	return '<div class="center-message"><p>Hold on, you are being too fast. Slow down, get a cup of coffee and try again later.</p></div>';
}

/**
 * Solves the Cap PoW challenge and returns a one-time verification token.
 * `onProgress` is called with 0–100.
 */
export async function solveCap(
	onProgress?: (pct: number) => void
): Promise<string | undefined> {
	try {
		// Cap is provided globally via `cap-widget` import side-effect.
		// We access it via globalThis to avoid TS missing type.
		const CapCtor = (globalThis as unknown as { Cap: new (opts: { apiEndpoint: string }) => CapInstance }).Cap;
		if (!CapCtor) throw new Error('Cap not loaded');
		const cap = new CapCtor({ apiEndpoint: CAP_ENDPOINT });

		const handler = (e: { detail?: { progress?: number } }) => {
			onProgress?.(Math.round((e.detail?.progress ?? 0) * 100));
		};
		// cap-widget exposes addEventListener/removeEventListener for progress
		(cap as unknown as { addEventListener?: (ev: string, fn: (e: unknown) => void) => void }).addEventListener?.(
			'progress',
			handler as (e: unknown) => void
		);

		const { token } = await cap.solve();

		(cap as unknown as { removeEventListener?: (ev: string, fn: (e: unknown) => void) => void }).removeEventListener?.(
			'progress',
			handler as (e: unknown) => void
		);
		return token as string;
	} catch (err) {
		throw err;
	}
}

type CapInstance = {
	solve: () => Promise<{ token: string }>;
	addEventListener?: (ev: string, fn: (e: unknown) => void) => void;
	removeEventListener?: (ev: string, fn: (e: unknown) => void) => void;
};
