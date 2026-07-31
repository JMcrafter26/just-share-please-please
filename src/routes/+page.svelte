<script>
	import { onMount } from 'svelte';
	import DOMPurify from 'dompurify';
	import { createMarkdownRenderer } from '$lib/markdown';

	const REPORT_REASONS = [
		{ value: 'spam', label: 'Spam' },
		{ value: 'broken', label: 'Broken or empty' },
		{ value: 'inappropriate', label: 'Inappropriate content' },
		{ value: 'copyright', label: 'Copyright violation' },
		{ value: 'illegal', label: 'Illegal content or abuse' },
		{ value: 'other', label: 'Other' }
	];

	/** @type {string | undefined} */
	let currentId;
	/** current raw markdown source, kept so the download/Obsidian links can use it */
	let rawContent = $state('');
	let bodyHtml = $state('<div class="center-message"><p>Loading...</p></div>');
	let pageTitle = $state('Just Share Please');
	let mainEl;

	let reportOpen = $state(false);
	let reportReason = $state(REPORT_REASONS[0].value);
	let reportMessage = $state('');
	/** @type {'idle' | 'sending' | 'done' | 'error'} */
	let reportStatus = $state('idle');
	let reportError = $state('');

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

	async function display() {
		bodyHtml = '<div class="center-message"><p>Loading...</p></div>';
		const id = getId();
		currentId = id;

		// share.php lives at the webroot, right next to this page - see
		// ../backend in the project for its source.
		const url = id ? `./share?id=${encodeURIComponent(id)}` : './index.md';
		let text;
		try {
			const res = await fetch(url);
			if (!res.ok) throw new Error(`${res.status} ${res.statusText}`);
			text = await res.text();
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
		reportStatus = 'sending';
		reportError = '';
		try {
			const res = await fetch('./report', {
				method: 'POST',
				headers: { 'content-type': 'application/json' },
				body: JSON.stringify({ id: currentId, reason: reportReason, message: reportMessage })
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

<div class="content">
	<div id="main" bind:this={mainEl}>{@html bodyHtml}</div>
	<div id="footer">
		Created using <a href="./">Just Share Please</a> for
		<a href="https://obsidian.md">Obsidian</a> -
		<a href={downloadHref} download="{pageTitle}.md">Download Markdown</a> -
		<a href={obsidianHref}>Open in Obsidian</a>
		{#if currentId}
			- <button type="button" class="link-button" onclick={openReport}>Report</button>
		{/if}
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
				{#if reportError}<p class="modal-error">{reportError}</p>{/if}
				<div class="modal-actions">
					<button type="button" onclick={closeReport} disabled={reportStatus === 'sending'}
						>Cancel</button
					>
					<button type="button" onclick={submitReport} disabled={reportStatus === 'sending'}>
						{reportStatus === 'sending' ? 'Sending…' : 'Submit report'}
					</button>
				</div>
			{/if}
		</div>
	</div>
{/if}
