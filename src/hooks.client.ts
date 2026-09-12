import { PUBLIC_PLAUSIBLE_DOMAIN, PUBLIC_PLAUSIBLE_ENABLE, PUBLIC_PLAUSIBLE_ENDPOINT } from '$env/static/public';
import { init } from '@plausible-analytics/tracker';

if (PUBLIC_PLAUSIBLE_ENABLE === 'true' && PUBLIC_PLAUSIBLE_DOMAIN) {
	init({
		domain: PUBLIC_PLAUSIBLE_DOMAIN,
		...(PUBLIC_PLAUSIBLE_ENDPOINT ? { endpoint: PUBLIC_PLAUSIBLE_ENDPOINT } : {}),
		captureOnLocalhost: false,
		outboundLinks: true
	});
}
