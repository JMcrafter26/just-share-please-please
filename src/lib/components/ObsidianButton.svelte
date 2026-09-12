<script lang="ts">
	let { href, compact = false } = $props<{ href: string; compact?: boolean }>();
</script>

<a
	href={href}
	class="obsidian-top-button"
	class:obsidian-compact={compact}
	title="Import this note into Obsidian"
	aria-label="Import this note into Obsidian"
>
	<svg
		width="20"
		height="20"
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 640 640"
		fill="currentColor"
		aria-hidden="true"
	>
		<path
			d="M270.3 384.3C306.4 376.1 340.6 373.3 371.6 385C403.4 396.9 433.3 424.6 458.3 481C450.2 498.7 445.9 517.9 443.6 534.8C440.5 557.7 418.2 575.5 395.9 569.4C364.2 560.7 327.6 547.1 294.6 544.5C290.2 544.2 244 540.7 244 540.7C242.3 540.6 240.6 540.3 238.9 539.9C270.4 475.7 277.1 424.8 270.2 384.5zM183 283.5C205.3 298.3 232.5 321.3 247.1 357.1C263.2 396.5 264.9 452.8 225.5 533.2C224.5 532.4 223.4 531.5 222.5 530.5L135.4 440.8C125.9 431 123.3 416.4 128.9 403.9C129.5 402.6 173.5 305.6 183 283.5zM447.3 192.2C452.2 198.4 454.7 206.2 454.7 214.1C454.8 234.9 456.5 277.7 468 305.3C479.2 332.1 499.8 361.1 510.5 375.3C514.6 380.8 515.3 388.2 511.8 394C504.2 406.8 489.2 431.5 468 463.2C467.7 463.6 467.4 464.1 467.2 464.5C442 412.3 411.3 383.8 376.8 370.9C367.2 367.3 357.3 364.9 347.4 363.6C329.6 317.8 324.2 283.9 325.4 257.2C326.6 229.8 334.6 209.4 343.6 190.9C352.5 172.8 363.4 155 368.5 135.2C371.7 122.7 372.7 109.4 370.2 94.3L447.3 192.3zM312.2 78.1C322.5 68.9 336.8 67.3 348.5 72.6C358.1 97.2 358.2 115.4 354.1 131.4C349.5 149.2 340 164.4 330.2 184.2C320.6 203.7 311.8 226.2 310.5 256.5C309.3 284.4 314.6 318.4 330.9 362.3C309.9 361.7 288.4 364.9 267.2 369.7C265.5 363.3 263.4 357.3 261.1 351.5C243.8 309.3 211.2 283.6 187 268.2C190.1 249.5 195.2 212.8 198.6 190.7C199.8 183.1 203.5 176.1 209.3 170.9L312.2 78.1z"
		/>
	</svg>
	<span class="obsidian-label">Import into Obsidian</span>
</a>

<style>
	.obsidian-top-button {
		position: fixed;
		top: 16px;
		right: 20px;
		z-index: 90;
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 8px 14px;
		font-size: 0.88rem;
		font-weight: 500;
		color: #ffffff !important;
		background: #7c3aed;
		border-radius: 20px;
		text-decoration: none !important;
		transition:
			transform 0.2s ease,
			box-shadow 0.2s ease,
			padding 0.25s cubic-bezier(0.4, 0, 0.2, 1),
			gap 0.25s ease,
			width 0.25s cubic-bezier(0.4, 0, 0.2, 1),
			background 0.2s ease;
		overflow: hidden;
		white-space: nowrap;
		will-change: padding, gap, width;
		box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12);
	}

	.obsidian-top-button:hover {
		transform: translateY(-1px);
	}

	.obsidian-top-button .obsidian-label {
		transition:
			opacity 0.2s ease,
			max-width 0.28s cubic-bezier(0.4, 0, 0.2, 1),
			transform 0.25s ease,
			margin 0.25s ease;
		max-width: 200px;
		opacity: 1;
		overflow: hidden;
		transform: translateX(0);
	}

	.obsidian-top-button.obsidian-compact {
		justify-content: center;
		gap: 0;
		padding: 10px;
		border-radius: 9999px;
		box-shadow: 0 2px 12px rgba(0, 0, 0, 0.18);
	}

	.obsidian-top-button.obsidian-compact .obsidian-label {
		opacity: 0;
		max-width: 0;
		transform: translateX(8px);
		pointer-events: none;
	}

	.obsidian-top-button.obsidian-compact:hover,
	.obsidian-top-button.obsidian-compact:focus-visible {
		justify-content: center;
		border-radius: 20px;
	}

	.obsidian-top-button.obsidian-compact:hover .obsidian-label,
	.obsidian-top-button.obsidian-compact:focus-visible .obsidian-label {
		opacity: 1;
		max-width: 200px;
		transform: translateX(0);
		pointer-events: auto;
	}

	@media (prefers-reduced-motion: reduce) {
		.obsidian-top-button,
		.obsidian-top-button .obsidian-label {
			transition: none;
		}
	}

	@media (max-width: 600px) {
		.obsidian-top-button {
			position: absolute;
			top: 12px;
			right: 12px;
			padding: 6px 10px;
			font-size: 0.8rem;
		}

		.obsidian-top-button.obsidian-compact {
			position: fixed;
			top: 12px;
			right: 12px;
			padding: 6px;
			font-size: 0.8rem;
		}

		.obsidian-top-button.obsidian-compact:hover,
		.obsidian-top-button.obsidian-compact:focus-visible {
			padding: 6px 10px;
		}
	}
</style>
