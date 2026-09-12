<script lang="ts">
	import type { Snippet } from 'svelte';

	let {
		open = false,
		title,
		onclose = () => {},
		children
	}: {
		open: boolean;
		title: string;
		onclose?: () => void;
		children: Snippet;
	} = $props();
</script>

{#if open}
	<div class="modal-overlay" onclick={onclose} role="presentation">
		<div
			class="modal modal-legal"
			onclick={(e) => e.stopPropagation()}
			role="dialog"
			aria-modal="true"
			aria-label={title}
		>
			<h3>{title}</h3>
			{@render children()}
			<div class="modal-actions">
				<button type="button" onclick={onclose}>Close</button>
			</div>
		</div>
	</div>
{/if}

<style>
	.modal-overlay {
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, 0.4);
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 16px;
		z-index: 100;
	}

	.modal {
		background: #ffffff;
		color: #222222;
		border-radius: 8px;
		padding: 20px 24px;
		max-width: 420px;
		width: 100%;
		box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
	}

	.modal h3 {
		margin-top: 0;
	}

	.modal-actions {
		display: flex;
		justify-content: flex-end;
		gap: 8px;
		margin-top: 16px;
	}

	.modal-actions button {
		font-family: inherit;
		font-size: 0.95em;
		padding: 6px 14px;
		border-radius: 5px;
		border: 1px solid #8b8b8b;
		background: transparent;
		color: inherit;
		cursor: pointer;
	}

	.modal-actions button:last-child {
		background-color: #9275f0;
		border-color: #9275f0;
		color: #ffffff;
	}

	.modal-actions button:last-child:hover {
		background-color: #a48cf2;
		border-color: #a48cf2;
	}

	@media (prefers-color-scheme: dark) {
		.modal {
			background: #2a2a2a;
			color: #dadada;
		}
	}

	.modal-legal {
		max-width: 580px;
		max-height: 80vh;
		overflow-y: auto;
		line-height: 1.6;
	}

	.modal-legal :global(h4) {
		margin-top: 16px;
		margin-bottom: 4px;
		color: #7c3aed;
	}
</style>
