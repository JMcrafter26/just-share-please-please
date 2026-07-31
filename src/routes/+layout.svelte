<script>
	import '@fontsource/inter';
	import '@fontsource/jetbrains-mono';
	import 'katex/dist/katex.min.css';
	import 'highlight.js/styles/github.css';
	import '$lib/styles/hljs-dark.css';
	import '$lib/styles/app.css';

	let { children } = $props();
</script>

{@render children()}
