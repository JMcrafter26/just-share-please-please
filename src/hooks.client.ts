import { env } from '$env/dynamic/public';
import { init } from '@plausible-analytics/tracker';

if (env.PUBLIC_PLAUSIBLE_ENABLE === 'true' && env.PUBLIC_PLAUSIBLE_DOMAIN) {
	init({
		domain: env.PUBLIC_PLAUSIBLE_DOMAIN,
		...(env.PUBLIC_PLAUSIBLE_ENDPOINT ? { endpoint: env.PUBLIC_PLAUSIBLE_ENDPOINT } : {}),
		captureOnLocalhost: false,
		outboundLinks: true
	});
}
