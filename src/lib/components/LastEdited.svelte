<script lang="ts">
	import { formatLastEdited } from '$lib/utils/formatDate';

	let { timestamp }: { timestamp: number | null } = $props();

	let label = $derived(formatLastEdited(timestamp));
</script>

{#if timestamp}
	<div class="last-edited" title={new Date(timestamp * 1000).toLocaleString()}>
		Last edited: {label}
	</div>
{/if}

<style>
	.last-edited {
		text-align: center;
		margin: 32px 0 8px;
		font-size: 0.82em;
		color: rgba(34, 34, 34, 0.5);
		font-style: italic;
	}

	@media (prefers-color-scheme: dark) {
		.last-edited {
			color: rgba(255, 255, 255, 0.5);
		}
	}
</style>
