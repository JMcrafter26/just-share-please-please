<script>
	import { onMount } from 'svelte';
	import DOMPurify from 'dompurify';
	import { createMarkdownRenderer } from '$lib/markdown';
	import "cap-widget";
	
	/** Base URL for the self-hosted Cap endpoints (same origin, routed via .htaccess) */
	const CAP_ENDPOINT = './cap/';

	const REPORT_REASONS = [
		{ value: 'spam', label: 'Spam' },
		{ value: 'broken', label: 'Broken or empty' },
		{ value: 'inappropriate', label: 'Inappropriate content' },
		{ value: 'copyright', label: 'Copyright violation' },
		{ value: 'illegal', label: 'Illegal content or abuse' },
		{ value: 'other', label: 'Other' }
	];

	/** @type {string | undefined} */
	let currentId = $state(undefined);
	/** current raw markdown source, kept so the download/Obsidian links can use it */
	let rawContent = $state('');
	let bodyHtml = $state('');
	let pageLoading = $state(true);
	let pageTitle = $state('Just Share Please');
	let mainEl;

	// ---- Cap PoW state ----
	/** @type {'idle' | 'solving' | 'done' | 'error'} */
	let capStatus = $state('idle');
	/** 0–100 progress percentage driven by cap widget events */
	let capProgress = $state(0);
	/** Whether to show the reassurance label (only after >2 s hang) */
	let capShowLabel = $state(false);
	let _capLabelTimer = null;

	let reportOpen = $state(false);
	let reportReason = $state(REPORT_REASONS[0].value);
	let reportMessage = $state('');
	/** @type {'idle' | 'solving' | 'sending' | 'done' | 'error'} */
	let reportStatus = $state('idle');
	let reportError = $state('');
	/** Cap token obtained after solving PoW for the report form */
	let reportCapToken = $state('');
	let reportCapProgress = $state(0);

	let tosOpen = $state(false);
	let privacyOpen = $state(false);

	const md = createMarkdownRenderer(() => currentId);

	/**
	 * Parses the note id out of a location hash, same rule as before:
	 * "#<id>-<permalink-slug>". No id (bare "#" or empty hash) means
	 * "show the default index.md".
	 * @param {string} [hash]
	 */
	function getId(hash) {
		hash ??= window.location.hash;
		if (!hash) return undefined;
		const dash = hash.indexOf('-');
		return hash.substring(1, dash > 0 ? dash : hash.length);
	}

	/**
	 * Solves the Cap PoW challenge and establishes the session on the PHP backend.
	 * cap.solve() internally calls /cap/challenge + /cap/redeem for us and
	 * returns a one-time verification token.
	 */
	async function solveCap() {
		capStatus = 'solving';
		capProgress = 0;
		capShowLabel = false;
		clearTimeout(_capLabelTimer);
		// Show reassurance label only if solving takes >2 s
		_capLabelTimer = setTimeout(() => { capShowLabel = true; }, 2000);

	
	// simulate fake progress for now, since we don't have a real Cap PoW implementation yet
		// await new Promise((resolve) => {
		// 	const interval = setInterval(() => {
		// 		capProgress += 5;
		// 		if (capProgress >= 100) {
		// 			clearInterval(interval);
		// 			resolve(undefined);
		// 		}
		// 	}, 100);
		// });
	

		try {
			const cap = new Cap({ apiEndpoint: CAP_ENDPOINT });

			// Listen to solve progress from the Web Worker
			const onProgress = (e) => {
				capProgress = Math.round((e.detail?.progress ?? 0) * 100);
			};
			cap.addEventListener?.('progress', onProgress);

			// cap.solve() does challenge + redeem internally via /cap/challenge and /cap/redeem
			const { token } = await cap.solve();

			cap.removeEventListener?.('progress', onProgress);
			clearTimeout(_capLabelTimer);
			capProgress = 100;
			capStatus = 'done';
			// Fade bar out shortly after
			setTimeout(() => { if (capStatus === 'done') capStatus = 'idle'; }, 500);
			return token;
		} catch (err) {
			clearTimeout(_capLabelTimer);
			capStatus = 'error';
			bodyHtml = `<div class="center-message"><p>Verification failed. Please <a href="javascript:location.reload()">reload</a> and try again.</p></div>`;
			return undefined;
		}
	}

	async function display() {
		const id = getId();
		currentId = id;
		pageLoading = true;

		try {
			// Only shared notes (id != null) are protected by Cap PoW.
			// index.md is public.
			let capToken = '';
			if (id) {
				capToken = await solveCap() ?? '';
				if (!capToken) return; // solveCap already set error html
			}

			// share.php lives at the webroot, right next to this page - see
			// ../backend in the project for its source.
			const url = id
				? `./share?id=${encodeURIComponent(id)}&cap-token=${encodeURIComponent(capToken)}`
				: './index.md';
			let text;
			try {
				const res = await fetch(url, { credentials: 'omit' });
				if (res.status === 403) {
					// Session may have expired — reset and retry with fresh PoW
					const json = await res.json().catch(() => ({}));
					if (json.error === 'cap_required') {
						capToken = await solveCap() ?? '';
						if (!capToken) return;
						// Retry fetch after fresh verification
						const retryUrl = `./share?id=${encodeURIComponent(id ?? '')}&cap-token=${encodeURIComponent(capToken)}`;
						const retry = await fetch(retryUrl, { credentials: 'omit' });
						if (!retry.ok) throw new Error(`${retry.status} ${retry.statusText}`);
						text = await retry.text();
					} else {
						throw new Error(`403 Forbidden`);
					}
				} else if (!res.ok) {
					throw new Error(`${res.status} ${res.statusText}`);
				} else {
					text = await res.text();
				}
			} catch (err) {
				bodyHtml = `<div class="center-message"><p>Error loading shared note with id <code>${escapeHtml(
					id ?? ''
				)}</code>: <code>${escapeHtml(String(err.message ?? err))}</code></p><p><a href="./">Home</a></p></div>`;
				return;
			}

			rawContent = text;
			bodyHtml = DOMPurify.sanitize(md.render(text));

			await tick();

			const firstHeading = mainEl?.querySelector('h1, h2, h3, h4, h5, h6');
			if (firstHeading) {
				let heading = firstHeading.textContent?.trim() ?? '';
				if (heading.endsWith('#')) heading = heading.slice(0, -1).trimEnd();
				if (heading) pageTitle = heading;
			}

			if (window.location.hash) {
				const target = document.getElementById(decodeURIComponent(window.location.hash.slice(1)));
				target?.scrollIntoView();
			}
		} finally {
			pageLoading = false;
		}
	}

	function escapeHtml(s) {
		return s.replace(
			/[&<>"']/g,
			(c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
		);
	}

	// tiny local tick helper so we don't need to pull in extra svelte internals
	function tick() {
		return new Promise((resolve) => requestAnimationFrame(resolve));
	}

	function openReport() {
		reportReason = REPORT_REASONS[0].value;
		reportMessage = '';
		reportStatus = 'idle';
		reportError = '';
		reportOpen = true;
	}

	function closeReport() {
		reportOpen = false;
	}

	async function submitReport() {
		if (!currentId) return;
		reportError = '';

		// Solve PoW fresh for each report submission (token is single-use on the server)
		reportStatus = 'solving';
		reportCapProgress = 0;
		try {
			const reportCap = new Cap({ apiEndpoint: CAP_ENDPOINT });
			const onProg = (e) => { reportCapProgress = Math.round((e.detail?.progress ?? 0) * 100); };
			reportCap.addEventListener?.('progress', onProg);
			const { token } = await reportCap.solve();
			reportCap.removeEventListener?.('progress', onProg);
			reportCapProgress = 100;
			reportCapToken = token;
		} catch {
			reportError = 'Verification failed. Please try again.';
			reportStatus = 'error';
			return;
		}

		reportStatus = 'sending';
		try {
			const res = await fetch('./report', {
				method: 'POST',
				headers: { 'content-type': 'application/json' },
				body: JSON.stringify({
					id: currentId,
					reason: reportReason,
					message: reportMessage,
					'cap-token': reportCapToken,
				})
			});
			if (res.status === 429) {
				reportError = "You've already reported this note recently - thanks, it's in the queue.";
				reportStatus = 'error';
				return;
			}
			if (!res.ok) {
				reportError = 'Something went wrong submitting the report. Please try again later.';
				reportStatus = 'error';
				return;
			}
			reportStatus = 'done';
		} catch {
			reportError = 'Could not reach the server. Please try again later.';
			reportStatus = 'error';
		}
	}

	onMount(() => {
		const onHashChange = (e) => {
			const oldId = e.oldURL ? getId(new URL(e.oldURL).hash) : undefined;
			if (getId() !== oldId) display();
		};
		window.addEventListener('hashchange', onHashChange);
		display();
		return () => window.removeEventListener('hashchange', onHashChange);
	});

	$effect(() => {
		if (typeof document !== 'undefined') document.title = pageTitle;
	});

	const downloadHref = $derived(`data:text/plain;charset=utf-8,${encodeURIComponent(rawContent)}`);
	const obsidianHref = $derived(
		`obsidian://new?name=${encodeURIComponent(pageTitle)}&content=${encodeURIComponent(rawContent)}`
	);
</script>

<svelte:head>
	<title>Just Share Please</title>
</svelte:head>

<a href={obsidianHref} class="obsidian-top-button" title="Import this note into Obsidian">
	<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true">
		<!--!Font Awesome Free v7.3.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.-->
		<path d="M270.3 384.3C306.4 376.1 340.6 373.3 371.6 385C403.4 396.9 433.3 424.6 458.3 481C450.2 498.7 445.9 517.9 443.6 534.8C440.5 557.7 418.2 575.5 395.9 569.4C364.2 560.7 327.6 547.1 294.6 544.5C290.2 544.2 244 540.7 244 540.7C242.3 540.6 240.6 540.3 238.9 539.9C270.4 475.7 277.1 424.8 270.2 384.5zM183 283.5C205.3 298.3 232.5 321.3 247.1 357.1C263.2 396.5 264.9 452.8 225.5 533.2C224.5 532.4 223.4 531.5 222.5 530.5L135.4 440.8C125.9 431 123.3 416.4 128.9 403.9C129.5 402.6 173.5 305.6 183 283.5zM447.3 192.2C452.2 198.4 454.7 206.2 454.7 214.1C454.8 234.9 456.5 277.7 468 305.3C479.2 332.1 499.8 361.1 510.5 375.3C514.6 380.8 515.3 388.2 511.8 394C504.2 406.8 489.2 431.5 468 463.2C467.7 463.6 467.4 464.1 467.2 464.5C442 412.3 411.3 383.8 376.8 370.9C367.2 367.3 357.3 364.9 347.4 363.6C329.6 317.8 324.2 283.9 325.4 257.2C326.6 229.8 334.6 209.4 343.6 190.9C352.5 172.8 363.4 155 368.5 135.2C371.7 122.7 372.7 109.4 370.2 94.3L447.3 192.3zM312.2 78.1C322.5 68.9 336.8 67.3 348.5 72.6C358.1 97.2 358.2 115.4 354.1 131.4C349.5 149.2 340 164.4 330.2 184.2C320.6 203.7 311.8 226.2 310.5 256.5C309.3 284.4 314.6 318.4 330.9 362.3C309.9 361.7 288.4 364.9 267.2 369.7C265.5 363.3 263.4 357.3 261.1 351.5C243.8 309.3 211.2 283.6 187 268.2C190.1 249.5 195.2 212.8 198.6 190.7C199.8 183.1 203.5 176.1 209.3 170.9L312.2 78.1z"/>
	</svg>
	<span>Import into Obsidian</span>
</a>

<!-- Cap PoW progress bar — centered, 50px wide, completely rounded -->
{#if pageLoading || capStatus === 'solving' || capStatus === 'done'}
	<div class="cap-bar-wrap" class:cap-bar-done={capStatus === 'done'}>
		{#if pageLoading && capStatus === 'idle'}
			<progress class="cap-bar-progress" max="100"></progress>
		{:else}
			<progress class="cap-bar-progress" value={capProgress} max="100"></progress>
		{/if}
		{#if capShowLabel}
			<span class="cap-bar-label">Loading…</span>
		{/if}
	</div>
{/if}

<div class="content">
	<div id="main" bind:this={mainEl}>{@html bodyHtml}</div>
	<div id="footer">
		<a href={downloadHref} download="{pageTitle}.md">Download Markdown</a> -
		<a href={obsidianHref}>Import into Obsidian</a>
		{#if currentId}
			- <button type="button" class="link-button" onclick={openReport}>Report</button>
		{/if}
		<br />
				Created using <a href="./">Just Share Please</a> for
		<a href="https://obsidian.md">Obsidian</a>
		- <button type="button" class="link-button" onclick={() => (tosOpen = true)}>Terms of Service</button>
		- <button type="button" class="link-button" onclick={() => (privacyOpen = true)}>Privacy Policy</button>
	</div>
</div>

{#if reportOpen}
	<div class="modal-overlay" onclick={closeReport} role="presentation">
		<div
			class="modal"
			onclick={(e) => e.stopPropagation()}
			role="dialog"
			aria-modal="true"
			aria-label="Report this note"
		>
			{#if reportStatus === 'done'}
				<p>Thanks - this note has been flagged for review.</p>
				<div class="modal-actions">
					<button type="button" onclick={closeReport}>Close</button>
				</div>
			{:else}
				<h3>Report this note</h3>
				<label>
					Reason
					<select bind:value={reportReason}>
						{#each REPORT_REASONS as r (r.value)}
							<option value={r.value}>{r.label}</option>
						{/each}
					</select>
				</label>
				<label>
					Additional details (optional)
					<textarea bind:value={reportMessage} maxlength="1000" rows="3"></textarea>
				</label>
				{#if reportStatus === 'solving'}
					<div class="report-cap-wrap">
						<div class="report-cap-track">
							<div class="report-cap-bar" style="width: {reportCapProgress}%"></div>
						</div>
					</div>
				{/if}
				{#if reportError}<p class="modal-error">{reportError}</p>{/if}
				<div class="modal-actions">
					<button type="button" onclick={closeReport} disabled={reportStatus === 'solving' || reportStatus === 'sending'}
						>Cancel</button
					>
					<button type="button" onclick={submitReport} disabled={reportStatus === 'solving' || reportStatus === 'sending'}>
						{reportStatus === 'solving' ? 'Verifying…' : reportStatus === 'sending' ? 'Sending…' : 'Submit report'}
					</button>
				</div>
			{/if}
		</div>
	</div>
{/if}

{#if tosOpen}
	<div class="modal-overlay" onclick={() => (tosOpen = false)} role="presentation">
		<div
			class="modal modal-legal"
			onclick={(e) => e.stopPropagation()}
			role="dialog"
			aria-modal="true"
			aria-label="Terms of Service"
		>
			<h3>Terms of Service</h3>
			<h4>1. Service Provision & Disclaimer</h4>
			<p>
				Just Share Please is provided on an "AS IS" and "AS AVAILABLE" basis without warranties of any kind, express or implied.
				The service operator makes no guarantees regarding availability, reliability, security, or data retention.
			</p>
			<h4>2. Limitation of Liability</h4>
			<p>
				To the maximum extent permitted by applicable law, the owner and operator of this service shall NOT be held liable for any direct, indirect, incidental, special, consequential, or exemplary damages, including but not limited to loss of data, loss of profits, service interruption, server downtime, or any issues arising from the use of or inability to use this service.
			</p>
			<h4>3. User Responsibility & Content Restrictions</h4>
			<p>
				You are solely responsible for all content you share using this service. You agree not to upload or share any content that is illegal, abusive, defamatory, copyright-infringing, contains malware, or violates the privacy of others.
			</p>
			<h4>4. Content Removal</h4>
			<p>
				We reserve the right to remove any shared note or disable access to content at any time, for any reason, without prior notice.
			</p>
			<div class="modal-actions">
				<button type="button" onclick={() => (tosOpen = false)}>Close</button>
			</div>
		</div>
	</div>
{/if}

{#if privacyOpen}
	<div class="modal-overlay" onclick={() => (privacyOpen = false)} role="presentation">
		<div
			class="modal modal-legal"
			onclick={(e) => e.stopPropagation()}
			role="dialog"
			aria-modal="true"
			aria-label="Privacy Policy"
		>
			<h3>Privacy Policy</h3>
			<h4>1. Minimal Data Collection</h4>
			<p>
				We value your privacy. We do not collect personal identifying information. No user accounts, registration, email addresses, or cookies are required to share or view notes.
			</p>
			<h4>2. Shared Note Data</h4>
			<p>
				When you share a note, the raw text content is stored securely on the server to render it publicly for anyone with the shared link. Anyone with the unique link can view the note.
			</p>
			<h4>3. Privacy-Friendly Analytics</h4>
			<p>
				This website may collect basic, privacy-friendly usage metrics using tools like Plausible Analytics. Plausible Analytics is GDPR-compliant, cookie-less, and does not collect or track any personal identifying data.
			</p>
			<h4>4. IP Processing</h4>
			<p>
				IP addresses are processed temporarily in memory strictly for security and abuse mitigation (e.g. rate limiting note report submissions) and are never sold or shared with third parties.
			</p>
			<div class="modal-actions">
				<button type="button" onclick={() => (privacyOpen = false)}>Close</button>
			</div>
		</div>
	</div>
{/if}
