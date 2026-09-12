<script lang="ts">
	import { CAP_ENDPOINT, isCapRateLimitError } from '$lib/utils/cap';
	import 'cap-widget';

	const REPORT_REASONS = [
		{ value: 'spam', label: 'Spam' },
		{ value: 'broken', label: 'Broken or empty' },
		{ value: 'inappropriate', label: 'Inappropriate content' },
		{ value: 'copyright', label: 'Copyright violation' },
		{ value: 'illegal', label: 'Illegal content or abuse' },
		{ value: 'other', label: 'Other' }
	];

	let {
		open = false,
		currentId = undefined as string | undefined,
		onclose = () => {}
	} = $props<{
		open: boolean;
		currentId?: string;
		onclose?: () => void;
	}>();

	let reportReason = $state(REPORT_REASONS[0].value);
	let reportMessage = $state('');
	let reportStatus = $state<'idle' | 'solving' | 'sending' | 'done' | 'error'>('idle');
	let reportError = $state('');
	let reportCapProgress = $state(0);

	// Reset when opened
	$effect(() => {
		if (open) {
			reportReason = REPORT_REASONS[0].value;
			reportMessage = '';
			reportStatus = 'idle';
			reportError = '';
			reportCapProgress = 0;
		}
	});

	async function submitReport() {
		if (!currentId) return;
		reportError = '';

		// Solve PoW fresh for each report submission (token is single-use)
		reportStatus = 'solving';
		reportCapProgress = 0;
		let capToken = '';
		try {
			const CapCtor = (globalThis as unknown as { Cap: new (o: { apiEndpoint: string }) => CapInstance }).Cap;
			const reportCap = new CapCtor({ apiEndpoint: CAP_ENDPOINT });
			const onProg = (e: { detail?: { progress?: number } }) => {
				reportCapProgress = Math.round((e.detail?.progress ?? 0) * 100);
			};
			(reportCap as unknown as { addEventListener?: (ev: string, fn: (e: unknown) => void) => void }).addEventListener?.(
				'progress',
				onProg as (e: unknown) => void
			);
			const { token } = await reportCap.solve();
			(reportCap as unknown as { removeEventListener?: (ev: string, fn: (e: unknown) => void) => void }).removeEventListener?.(
				'progress',
				onProg as (e: unknown) => void
			);
			reportCapProgress = 100;
			capToken = token as string;
		} catch (err) {
			reportError = isCapRateLimitError(err)
				? 'Verification is temporarily rate-limited. Please wait a few minutes and try again.'
				: 'Verification failed. Please try again.';
			reportStatus = 'error';
			return;
		}

		reportStatus = 'sending';
		try {
			const res = await fetch('./report', {
				method: 'POST',
				headers: { 'content-type': 'application/json' },
				body: JSON.stringify({
					id: currentId,
					reason: reportReason,
					message: reportMessage,
					'cap-token': capToken
				})
			});
			if (res.status === 429) {
				reportError = "You've already reported this note recently - thanks, it's in the queue.";
				reportStatus = 'error';
				return;
			}
			if (!res.ok) {
				reportError = 'Something went wrong submitting the report. Please try again later.';
				reportStatus = 'error';
				return;
			}
			reportStatus = 'done';
		} catch {
			reportError = 'Could not reach the server. Please try again later.';
			reportStatus = 'error';
		}
	}

	type CapInstance = {
		solve: () => Promise<{ token: string }>;
		addEventListener?: (ev: string, fn: (e: unknown) => void) => void;
		removeEventListener?: (ev: string, fn: (e: unknown) => void) => void;
	};
</script>

{#if open}
	<div class="modal-overlay" onclick={onclose} role="presentation">
		<div
			class="modal"
			onclick={(e) => e.stopPropagation()}
			role="dialog"
			aria-modal="true"
			aria-label="Report this note"
		>
			{#if reportStatus === 'done'}
				<p>Thanks - this note has been flagged for review.</p>
				<div class="modal-actions">
					<button type="button" onclick={onclose}>Close</button>
				</div>
			{:else}
				<h3>Report this note</h3>
				<label>
					Reason
					<select bind:value={reportReason}>
						{#each REPORT_REASONS as r (r.value)}
							<option value={r.value}>{r.label}</option>
						{/each}
					</select>
				</label>
				<label>
					Additional details (optional)
					<textarea bind:value={reportMessage} maxlength="1000" rows="3"></textarea>
				</label>
				{#if reportStatus === 'solving'}
					<div class="report-cap-wrap">
						<div class="report-cap-track">
							<div class="report-cap-bar" style="width: {reportCapProgress}%"></div>
						</div>
					</div>
				{/if}
				{#if reportError}<p class="modal-error">{reportError}</p>{/if}
				<div class="modal-actions">
					<button
						type="button"
						onclick={onclose}
						disabled={reportStatus === 'solving' || reportStatus === 'sending'}>Cancel</button
					>
					<button
						type="button"
						onclick={submitReport}
						disabled={reportStatus === 'solving' || reportStatus === 'sending'}
					>
						{reportStatus === 'solving'
							? 'Verifying…'
							: reportStatus === 'sending'
								? 'Sending…'
								: 'Submit report'}
					</button>
				</div>
			{/if}
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

	.modal label {
		display: block;
		font-size: 0.9em;
		margin: 12px 0;
	}

	.modal select,
	.modal textarea {
		display: block;
		width: 100%;
		margin-top: 4px;
		font-family: inherit;
		font-size: 1em;
		color: inherit;
		background: transparent;
		border: 1px solid #8b8b8b;
		border-radius: 5px;
		padding: 6px 8px;
		box-sizing: border-box;
	}

	.modal-error {
		color: #c0392b;
		font-size: 0.9em;
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

	.modal-actions button:disabled {
		opacity: 0.6;
		cursor: default;
	}

	@media (prefers-color-scheme: dark) {
		.modal {
			background: #2a2a2a;
			color: #dadada;
		}
	}

	.report-cap-wrap {
		display: flex;
		justify-content: center;
		margin: 10px 0 4px;
	}

	.report-cap-track {
		width: 50px;
		height: 4px;
		background-color: rgba(34, 34, 34, 0.12);
		border-radius: 9999px;
		overflow: hidden;
	}

	.report-cap-bar {
		height: 100%;
		background-color: #222222;
		opacity: 0.55;
		border-radius: 9999px;
		transition: width 0.2s ease-out;
		max-width: 100%;
	}
</style>
