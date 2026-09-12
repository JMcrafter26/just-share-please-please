<script lang="ts">
	import { enhanceCodeBlocks } from '$lib/utils/codeBlocks';

	let { bodyHtml = '', bindEl = undefined as unknown as (el: HTMLElement | null) => void } = $props<{
		bodyHtml: string;
		bindEl?: (el: HTMLElement | null) => void;
	}>();

	let mainEl: HTMLElement | null = $state(null);

	$effect(() => {
		if (bindEl) bindEl(mainEl);
	});

	$effect(() => {
		void bodyHtml;
		if (typeof document !== 'undefined' && mainEl) {
			queueMicrotask(() => enhanceCodeBlocks(mainEl));
		}
	});
</script>

<div id="main" bind:this={mainEl}>{@html bodyHtml}</div>

<style>
	/* Code block copy button – needs :global because pre is injected via {@html} */
	:global(pre.code-block) {
		position: relative;
	}

	:global(.code-copy-btn) {
		position: absolute;
		top: 8px;
		right: 8px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 30px;
		height: 30px;
		padding: 0;
		border: 1px solid #d0d7de;
		background: #ffffff;
		color: #24292f;
		border-radius: 6px;
		cursor: pointer;
		opacity: 0;
		transition:
			opacity 0.15s ease,
			background 0.15s ease,
			border-color 0.15s ease,
			color 0.15s ease;
		z-index: 1;
	}

	:global(pre.code-block:hover .code-copy-btn),
	:global(.code-copy-btn:focus-visible) {
		opacity: 1;
	}

	:global(.code-copy-btn:hover) {
		background: #f3f4f6;
		border-color: #d0d7de;
	}

	:global(.code-copy-btn:active) {
		background: #ebecf0;
	}

	:global(.code-copy-btn.copied) {
		color: #1a7f37;
		border-color: #1a7f37;
	}

	:global(.code-copy-btn svg) {
		pointer-events: none;
		display: block;
	}

	@media (max-width: 600px) {
		:global(.code-copy-btn) {
			opacity: 1;
		}
	}

	@media (prefers-color-scheme: dark) {
		:global(.code-copy-btn) {
			background: #2a2a2a;
			border-color: #3a3a3a;
			color: #dadada;
		}

		:global(.code-copy-btn:hover) {
			background: #333333;
			border-color: #444444;
		}

		:global(.code-copy-btn:active) {
			background: #3a3a3a;
		}

		:global(.code-copy-btn.copied) {
			color: #3fb950;
			border-color: #3fb950;
		}
	}

	@media print {
		:global(.code-copy-btn) {
			display: none;
		}
	}
</style>
