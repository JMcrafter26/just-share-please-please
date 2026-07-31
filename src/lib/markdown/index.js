import MarkdownIt from 'markdown-it';
import markdownItAnchor from 'markdown-it-anchor';
import markdownItFootnote from 'markdown-it-footnote';
import markdownItCheckbox from 'markdown-it-checkbox';
import texmath from 'markdown-it-texmath';
import katex from 'katex';
import 'katex/contrib/mhchem'; // chemistry notation (\ce{...}), same as the old mhchem.min.js CDN script
import hljs from 'highlight.js/lib/core';
import plaintext from 'highlight.js/lib/languages/plaintext';
import latex from 'highlight.js/lib/languages/latex';
import javascript from 'highlight.js/lib/languages/javascript';
import typescript from 'highlight.js/lib/languages/typescript';
import python from 'highlight.js/lib/languages/python';
import bash from 'highlight.js/lib/languages/bash';
import json from 'highlight.js/lib/languages/json';
import css from 'highlight.js/lib/languages/css';
import xml from 'highlight.js/lib/languages/xml';
import markdown from 'highlight.js/lib/languages/markdown';
import yaml from 'highlight.js/lib/languages/yaml';
import sql from 'highlight.js/lib/languages/sql';
import java from 'highlight.js/lib/languages/java';
import cpp from 'highlight.js/lib/languages/cpp';
import csharp from 'highlight.js/lib/languages/csharp';
import go from 'highlight.js/lib/languages/go';
import rust from 'highlight.js/lib/languages/rust';
import php from 'highlight.js/lib/languages/php';

// Register a reasonably broad but not exhaustive set of languages so the
// bundle stays small. Anything unregistered falls back to plaintext, same
// as the original `hljs.getLanguage(l) ? l : "plaintext"` check.
hljs.registerLanguage('plaintext', plaintext);
hljs.registerLanguage('latex', latex);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('typescript', typescript);
hljs.registerLanguage('python', python);
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('json', json);
hljs.registerLanguage('css', css);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('html', xml);
hljs.registerLanguage('markdown', markdown);
hljs.registerLanguage('yaml', yaml);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('java', java);
hljs.registerLanguage('cpp', cpp);
hljs.registerLanguage('csharp', csharp);
hljs.registerLanguage('go', go);
hljs.registerLanguage('rust', rust);
hljs.registerLanguage('php', php);

/**
 * Slugify the same way the original app did: lowercase, NFKD-normalize,
 * strip anything that isn't a-z0-9_-, collapse whitespace to hyphens.
 * @param {string} s
 */
function slugify(s) {
	return encodeURIComponent(
		String(s)
			.trim()
			.toLowerCase()
			.normalize('NFKD')
			.replace(/\s+/g, '-')
			.replace(/[^a-z0-9_-]/g, '')
	);
}

/**
 * Builds a markdown-it instance whose heading anchors and footnote ids are
 * prefixed with the *current* note id, exactly like the jQuery version did.
 * `getId` is called lazily on every render so the same instance can be
 * reused across note navigations (SPA-style hash routing).
 * @param {() => string | undefined} getId
 */
export function createMarkdownRenderer(getId) {
	const md = new MarkdownIt({
		html: true,
		linkify: true,
		langPrefix: 'hljs language-',
		highlight: (code, lang) => {
			const language = hljs.getLanguage(lang) ? lang : 'plaintext';
			return hljs.highlight(code, { language }).value;
		}
	});

	md.use(texmath, {
		engine: katex,
		delimiters: ['dollars', 'beg_end']
	});

	md.use(markdownItAnchor, {
		permalink: markdownItAnchor.permalink.linkInsideHeader({
			placement: 'after',
			ariaHidden: true
		}),
		slugify: (s) => `${getId() ?? ''}-${slugify(s)}`
	});

	md.use(markdownItFootnote);
	md.use(markdownItCheckbox);

	const rulesToReplace = [
		['footnote_ref', /href="#(fn\d+)"/, () => `href="#${getId() ?? ''}-$1"`],
		['footnote_open', /id="(fn\d+)"/, () => `id="${getId() ?? ''}-$1"`],
		['footnote_ref', /id="(fnref\d+)"/, () => `id="${getId() ?? ''}-$1"`],
		['footnote_anchor', /href="#(fnref\d+)"/, () => `href="#${getId() ?? ''}-$1"`]
	];
	for (const [ruleName, pattern, replacement] of rulesToReplace) {
		const prevRule = md.renderer.rules[ruleName];
		md.renderer.rules[ruleName] = (tokens, idx, options, env, self) =>
			prevRule(tokens, idx, options, env, self).replace(pattern, replacement());
	}

	return md;
}
