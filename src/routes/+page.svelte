<script>
	import { onMount } from 'svelte';
	import DOMPurify from 'dompurify';
	import { createMarkdownRenderer } from '$lib/markdown';
	import 'cap-widget';

	import CapProgressBar from '$lib/components/CapProgressBar.svelte';
	import ObsidianButton from '$lib/components/ObsidianButton.svelte';
	import MarkdownContent from '$lib/components/MarkdownContent.svelte';
	import LastEdited from '$lib/components/LastEdited.svelte';
	import NoteFooter from '$lib/components/NoteFooter.svelte';
	import ReportModal from '$lib/components/ReportModal.svelte';
	import TosModal from '$lib/components/TosModal.svelte';
	import PrivacyModal from '$lib/components/PrivacyModal.svelte';

	import { getId } from '$lib/utils/hash';
	import { parseLastEditedHeader } from '$lib/utils/formatDate';
	import { CAP_ENDPOINT, isCapRateLimitError, capRateLimitMessage } from '$lib/utils/cap';
	import { tick } from '$lib/utils/codeBlocks';
	import { escapeHtml } from '$lib/utils/html';

	/** @type {string | undefined} */
	let currentId = $state(undefined);
	let rawContent = $state('');
	let bodyHtml = $state('');
	let pageLoading = $state(true);
	let pageTitle = $state('Just Share Please');
	let mainEl = $state(null);
	let isObsidianCompact = $state(false);

	/** @type {'idle' | 'solving' | 'done' | 'error'} */
	let capStatus = $state('idle');
	let capProgress = $state(0);
	let capShowLabel = $state(false);
	let _capLabelTimer = null;

	let reportOpen = $state(false);
	let tosOpen = $state(false);
	let privacyOpen = $state(false);

	/** Unix timestamp (seconds) of last edit, null when not available or for index.md */
	let lastEditedTs = $state(null);

	const md = createMarkdownRenderer(() => currentId);

	async function solveCap() {
		capStatus = 'solving';
		capProgress = 0;
		capShowLabel = false;
		clearTimeout(_capLabelTimer);
		_capLabelTimer = setTimeout(() => {
			capShowLabel = true;
		}, 2000);

		try {
			const cap = new Cap({ apiEndpoint: CAP_ENDPOINT });
			const onProgress = (e) => {
				capProgress = Math.round((e.detail?.progress ?? 0) * 100);
			};
			cap.addEventListener?.('progress', onProgress);
			const { token } = await cap.solve();
			cap.removeEventListener?.('progress', onProgress);
			clearTimeout(_capLabelTimer);
			capProgress = 100;
			capStatus = 'done';
			setTimeout(() => {
				if (capStatus === 'done') capStatus = 'idle';
			}, 500);
			return token;
		} catch (err) {
			clearTimeout(_capLabelTimer);
			capStatus = 'error';
			bodyHtml = isCapRateLimitError(err)
				? capRateLimitMessage()
				: `<div class="center-message"><p>Verification failed. Please <a href="javascript:location.reload()">reload</a> and try again.</p></div>`;
			return undefined;
		}
	}

	async function display() {
		const id = getId();
		currentId = id;
		lastEditedTs = null;
		pageLoading = true;

		try {
			let capToken = '';
			if (id) {
				capToken = (await solveCap()) ?? '';
				if (!capToken) return;
			}

			const url = id
				? `./share?id=${encodeURIComponent(id)}&cap-token=${encodeURIComponent(capToken)}`
				: './index.md';
			let text;
			try {
				const res = await fetch(url, { credentials: 'omit' });
				if (res.status === 403) {
					const json = await res.json().catch(() => ({}));
					if (json.error === 'cap_required') {
						capToken = (await solveCap()) ?? '';
						if (!capToken) return;
						const retryUrl = `./share?id=${encodeURIComponent(id ?? '')}&cap-token=${encodeURIComponent(capToken)}`;
						const retry = await fetch(retryUrl, { credentials: 'omit' });
						if (!retry.ok) throw new Error(`${retry.status} ${retry.statusText}`);
						if (id) lastEditedTs = parseLastEditedHeader(retry);
						text = await retry.text();
					} else {
						throw new Error(`403 Forbidden`);
					}
				} else if (!res.ok) {
					throw new Error(`${res.status} ${res.statusText}`);
				} else {
					if (id) lastEditedTs = parseLastEditedHeader(res);
					text = await res.text();
				}
			} catch (err) {
				bodyHtml = `<div class="center-message"><p>Error loading shared note with id <code>${escapeHtml(
					id ?? ''
				)}</code>: <code>${escapeHtml(String(err.message ?? err))}</code></p><p><a href="./">Home</a></p></div>`;
				return;
			}

			rawContent = text;
			bodyHtml = DOMPurify.sanitize(md.render(text), { ADD_ATTR: ['target', 'rel'] });

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

	function handleMainEl(el) {
		mainEl = el;
	}

	onMount(() => {
		const onHashChange = (e) => {
			const oldId = e.oldURL ? getId(new URL(e.oldURL).hash) : undefined;
			if (getId() !== oldId) display();
		};
		window.addEventListener('hashchange', onHashChange);

		let ticking = false;
		const onScroll = () => {
			if (ticking) return;
			ticking = true;
			requestAnimationFrame(() => {
				const next = window.scrollY > 36;
				if (next !== isObsidianCompact) isObsidianCompact = next;
				ticking = false;
			});
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
		display();
		return () => {
			window.removeEventListener('hashchange', onHashChange);
			window.removeEventListener('scroll', onScroll);
		};
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

<ObsidianButton href={obsidianHref} compact={isObsidianCompact} />

<CapProgressBar {pageLoading} {capStatus} {capProgress} {capShowLabel} />

<div class="content">
	<MarkdownContent {bodyHtml} bindEl={handleMainEl} />
	{#if currentId && lastEditedTs}
		<LastEdited timestamp={lastEditedTs} />
	{/if}
	<NoteFooter
		{currentId}
		{downloadHref}
		{obsidianHref}
		{pageTitle}
		onReport={() => (reportOpen = true)}
		onTos={() => (tosOpen = true)}
		onPrivacy={() => (privacyOpen = true)}
	/>
</div>

<ReportModal open={reportOpen} {currentId} onclose={() => (reportOpen = false)} />

<TosModal open={tosOpen} onclose={() => (tosOpen = false)} />

<PrivacyModal open={privacyOpen} onclose={() => (privacyOpen = false)} />
