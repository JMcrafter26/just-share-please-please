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
 * Wikilink plugin – handles Obsidian-style `[[target]]` syntax.
 *
 * Supported forms (single-note share context):
 *  - `[[#Header]]` → same-doc anchor link `href="#${id}-${slug}"` (live)
 *  - `[[#Header|Alias]]` → same but with alias text
 *  - `[[Page]]` / `[[Page|Alias]]` / `[[Page#Header]]` etc. → unresolved span
 *  - `![[Page]]` → embed placeholder span (same unresolved styling)
 *
 * Only heading-target without page (`#Header`) can resolve to an anchor.
 * Everything with a page part is rendered as `<span class="internal-link is-unresolved">`
 * since only the current note is shared.
 *
 * @param {import('markdown-it')} md
 * @param {{ slugify: (s: string) => string, getId: () => string|undefined }} opts
 */
function wikilinkPlugin(md, opts) {
	const slugifyFn = opts.slugify;
	const getId = opts.getId;

	/** Find first unescaped `|` in string, or -1 */
	function findUnescapedPipe(str) {
		for (let i = 0; i < str.length; i++) {
			if (str[i] === '|' && (i === 0 || str[i - 1] !== '\\')) return i;
		}
		return -1;
	}

	function wikilinkRule(state, silent) {
		const src = state.src;
		const pos = state.pos;
		const max = state.posMax;

		let isEmbed = false;
		let openerLen = 0;

		if (
			pos + 2 < max &&
			src.charCodeAt(pos) === 0x21 && // !
			src.charCodeAt(pos + 1) === 0x5b && // [
			src.charCodeAt(pos + 2) === 0x5b // [
		) {
			isEmbed = true;
			openerLen = 3;
		} else if (
			pos + 1 < max &&
			src.charCodeAt(pos) === 0x5b &&
			src.charCodeAt(pos + 1) === 0x5b
		) {
			isEmbed = false;
			openerLen = 2;
		} else {
			return false;
		}

		const closeIdx = src.indexOf(']]', pos + openerLen);
		if (closeIdx === -1 || closeIdx >= max) return false;

		const innerRaw = src.slice(pos + openerLen, closeIdx);
		if (innerRaw.indexOf('\n') !== -1) return false;
		if (innerRaw.trim() === '') return false;

		if (silent) {
			return true;
		}

		// Split alias on first unescaped |
		// Also handle table-escaped `\|` as alias delimiter when no plain `|` is present
		// (Obsidian requires `\|` inside tables so the markdown table parser doesn't split cells).
		let alias = null;
		let target = innerRaw;
		let pipeIdx = findUnescapedPipe(innerRaw);
		if (pipeIdx !== -1) {
			target = innerRaw.slice(0, pipeIdx);
			alias = innerRaw.slice(pipeIdx + 1);
			target = target.replace(/\\\|/g, '|').replace(/\\\\/g, '\\');
			alias = alias.replace(/\\\|/g, '|').replace(/\\\\/g, '\\');
		} else {
			// Fallback: treat first escaped pipe `\|` as alias delimiter (table case)
			const escIdx = innerRaw.indexOf('\\|');
			if (escIdx !== -1) {
				target = innerRaw.slice(0, escIdx);
				alias = innerRaw.slice(escIdx + 2);
				target = target.replace(/\\\|/g, '|').replace(/\\\\/g, '\\');
				alias = alias.replace(/\\\|/g, '|').replace(/\\\\/g, '\\');
			} else {
				target = target.replace(/\\\|/g, '|').replace(/\\\\/g, '\\');
			}
		}

		target = target.trim();
		if (alias !== null) alias = alias.trim();
		if (alias === '') alias = null;
		if (target === '') return false;

		// Split target into pagePart and headerPart on first '#'
		let pagePart = target;
		let headerPart = null;
		const hashIdx = target.indexOf('#');
		if (hashIdx !== -1) {
			pagePart = target.slice(0, hashIdx);
			headerPart = target.slice(hashIdx + 1);
			if (headerPart.trim() === '') headerPart = null;
		}

		pagePart = pagePart.trim();
		if (headerPart !== null) headerPart = headerPart.trim();

		// ---- Resolved case: pure header link like [[#Header]] or [[#Header|Alias]] ----
		const isPureHeaderLink = pagePart === '' && headerPart !== null;

		if (!isEmbed && isPureHeaderLink) {
			const isBlock = headerPart.startsWith('^');
			if (isBlock) {
				const blockId = headerPart.slice(1).trim();
				if (blockId === '') return false;
				const display = alias ?? blockId;
				const titleTarget = target;
				const open = state.push('span_open', 'span', 1);
				open.attrs = [
					['class', 'internal-link is-unresolved'],
					['title', `Block "${blockId}" – not available`],
					['data-href', titleTarget]
				];
				const textTok = state.push('text', '', 0);
				textTok.content = display;
				state.push('span_close', 'span', -1);
				state.pos = closeIdx + 2;
				return true;
			}

			const headerText = headerPart;
			if (headerText === '') return false;
			const slug = slugifyFn(headerText);
			const finalSlug = slug || encodeURIComponent(headerText);
			const idPart = getId() ?? '';
			const href = `#${idPart}-${finalSlug}`;
			const display = alias ?? headerText;

			const tokenOpen = state.push('link_open', 'a', 1);
			tokenOpen.attrs = [
				['href', href],
				['class', 'internal-link']
			];
			const tokenText = state.push('text', '', 0);
			tokenText.content = display;
			state.push('link_close', 'a', -1);
			state.pos = closeIdx + 2;
			return true;
		}

		// ---- Unresolved note link or embed placeholder ----
		let display;
		if (alias !== null) {
			display = alias;
		} else if (headerPart !== null && !headerPart.startsWith('^')) {
			display = headerPart;
		} else if (headerPart !== null && headerPart.startsWith('^')) {
			display = headerPart.slice(1).trim() || pagePart;
		} else {
			const leaf = (pagePart.split('/').pop() || pagePart).replace(/\.md$/i, '');
			display = leaf || pagePart;
		}
		if (!display) display = target;

		const titleTarget = target;
		const title = isEmbed
			? `Embedded note "${titleTarget}" – not shared`
			: `Link to "${titleTarget}" – note not shared`;

		const classes = isEmbed ? 'internal-link is-unresolved embed' : 'internal-link is-unresolved';
		const spanOpen = state.push('span_open', 'span', 1);
		spanOpen.attrs = [
			['class', classes],
			['title', title],
			['data-href', titleTarget]
		];
		const textTok = state.push('text', '', 0);
		textTok.content = display;
		state.push('span_close', 'span', -1);

		state.pos = closeIdx + 2;
		return true;
	}

	md.inline.ruler.before('link', 'wikilink', wikilinkRule);
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

	md.use(wikilinkPlugin, { slugify, getId });

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

	// open external links in new tab
	const defaultLinkOpen =
		md.renderer.rules.link_open ||
		function (tokens, idx, options, env, self) {
			return self.renderToken(tokens, idx, options);
		};
	md.renderer.rules.link_open = function (tokens, idx, options, env, self) {
		const href = tokens[idx].attrGet('href') || '';
		const isExternal = /^(https?:)?\/\//i.test(href);
		if (isExternal) {
			tokens[idx].attrSet('target', '_blank');
			tokens[idx].attrSet('rel', 'noopener noreferrer');
		}
		return defaultLinkOpen(tokens, idx, options, env, self);
	};

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
