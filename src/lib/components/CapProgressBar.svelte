<script lang="ts">
	let {
		pageLoading = false,
		capStatus = 'idle' as 'idle' | 'solving' | 'done' | 'error',
		capProgress = 0,
		capShowLabel = false
	} = $props();
</script>

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

<style>
	.cap-bar-wrap {
		position: fixed;
		top: 40%;
		left: 50%;
		transform: translate(-50%, -50%);
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 12px;
		z-index: 9999;
		pointer-events: none;
		transition: opacity 0.3s ease;
	}

	.cap-bar-wrap.cap-bar-done {
		opacity: 0;
	}

	.cap-bar-progress {
		width: 50px;
		height: 8px;
		-webkit-appearance: none;
		appearance: none;
		border: none;
		background-color: #f2f2f2;
		border-radius: 9999px;
		overflow: hidden;
	}

	.cap-bar-progress::-webkit-progress-bar {
		background-color: #f2f2f2;
		border-radius: 9999px;
	}

	.cap-bar-progress::-webkit-progress-value {
		background-color: #101010;
		border-radius: 9999px;
		transition: width 0.2s ease-out;
	}

	.cap-bar-progress::-moz-progress-bar {
		background-color: #101010;
		border-radius: 9999px;
	}

	.cap-bar-label {
		font-size: 0.85rem;
		color: rgba(34, 34, 34, 0.5);
		font-family: 'Inter', sans-serif;
		animation: cap-label-fade-in 0.3s ease;
	}

	@keyframes cap-label-fade-in {
		from {
			opacity: 0;
			transform: translateY(-3px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	@media (prefers-color-scheme: dark) {
		.cap-bar-progress {
			background-color: #242424;
		}

		.cap-bar-progress::-webkit-progress-bar {
			background-color: #101010;
		}

		.cap-bar-progress::-webkit-progress-value {
			background-color: #f2f2f2;
		}

		.cap-bar-progress::-moz-progress-bar {
			background-color: #f2f2f2;
		}

		.cap-bar-label {
			color: rgba(255, 255, 255, 0.5);
		}
	}
</style>
