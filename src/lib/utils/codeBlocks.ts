export const COPY_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>`;
export const CHECK_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>`;

export function enhanceCodeBlocks(root: HTMLElement | null) {
	if (!root) return;
	for (const pre of root.querySelectorAll('pre')) {
		if (pre.querySelector('.code-copy-btn')) continue;
		const code = pre.querySelector('code');
		if (!code) continue;
		pre.classList.add('code-block');
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'code-copy-btn';
		btn.setAttribute('aria-label', 'Copy code to clipboard');
		btn.title = 'Copy';
		btn.innerHTML = COPY_SVG;
		let resetTimer: ReturnType<typeof setTimeout> | undefined;
		btn.addEventListener('click', async () => {
			const targetCode = pre.querySelector('code');
			const text = targetCode ? (targetCode.textContent ?? '') : (pre.textContent ?? '');
			try {
				if (navigator.clipboard?.writeText) {
					await navigator.clipboard.writeText(text);
				} else {
					throw new Error('clipboard unavailable');
				}
			} catch {
				const ta = document.createElement('textarea');
				ta.value = text;
				ta.setAttribute('readonly', '');
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild(ta);
				ta.select();
				try {
					document.execCommand('copy');
				} catch {}
				ta.remove();
			}
			btn.innerHTML = CHECK_SVG;
			btn.classList.add('copied');
			btn.title = 'Copied!';
			btn.setAttribute('aria-label', 'Copied!');
			clearTimeout(resetTimer);
			resetTimer = setTimeout(() => {
				btn.innerHTML = COPY_SVG;
				btn.classList.remove('copied');
				btn.title = 'Copy';
				btn.setAttribute('aria-label', 'Copy code to clipboard');
			}, 2000);
		});
		pre.appendChild(btn);
	}
}

export function tick(): Promise<void> {
	return new Promise((resolve) => requestAnimationFrame(() => resolve()));
}
