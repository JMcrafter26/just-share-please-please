<script lang="ts">
	let {
		currentId = undefined as string | undefined,
		downloadHref = '',
		obsidianHref = '',
		pageTitle = '',
		onReport = () => {},
		onTos = () => {},
		onPrivacy = () => {}
	} = $props<{
		currentId?: string;
		downloadHref: string;
		obsidianHref: string;
		pageTitle: string;
		onReport?: () => void;
		onTos?: () => void;
		onPrivacy?: () => void;
	}>();
</script>

<div id="footer">
	<a href={downloadHref} download="{pageTitle}.md">Download Markdown</a> -
	<a href={obsidianHref}>Import into Obsidian</a>
	{#if currentId}
		- <button type="button" class="link-button" onclick={onReport}>Report</button>
	{/if}
	<br />
	Created using <a
		href="https://github.com/JMcrafter26/just-share-please-please"
		target="_blank"
		rel="noopener noreferrer">Just Share Please</a
	>
	for
	<a href="https://obsidian.md" target="_blank" rel="noopener noreferrer">Obsidian</a>
	- <button type="button" class="link-button" onclick={onTos}>Terms of Service</button>
	- <button type="button" class="link-button" onclick={onPrivacy}>Privacy Policy</button>
</div>

<style>
	#footer {
		position: absolute;
		left: 0;
		right: 0;
		bottom: 10px;
		height: 50px;
		margin-top: 40px;
		text-align: center;
		margin-bottom: 10px;
	}

	.link-button {
		background: none;
		border: none;
		padding: 0;
		margin: 0;
		font: inherit;
		color: #9275f0;
		text-decoration-line: underline;
		text-decoration-thickness: auto;
		cursor: pointer;
	}

	.link-button:hover {
		color: #a48cf2;
	}

	@media print {
		#footer {
			position: relative;
		}
	}
</style>
