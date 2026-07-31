import adapter from '@sveltejs/adapter-static';

/** @type {import('@sveltejs/kit').Config} */
const config = {
	kit: {
		// Outputs plain static files (HTML/CSS/JS) - no Node server involved.
		// Deploy the contents of `build/` into your PHP webroot alongside
		// share.php, report.php, includes/ and data/ from ../backend.
		adapter: adapter({
			pages: 'build',
			assets: 'build',
			fallback: 'index.html', // SPA fallback: note ids live in the URL hash, so every path serves the same shell
			strict: true
		})
	}
};

export default config;
