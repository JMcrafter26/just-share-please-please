<script lang="ts">
	import LegalModal from './LegalModal.svelte';
	let { open = false, onclose = () => {} } = $props<{ open: boolean; onclose?: () => void }>();
</script>

<LegalModal {open} title="Privacy Policy" {onclose}>
	<h4>1. Minimal Data Collection</h4>
	<p>
		We value your privacy. We do not collect personal identifying information. No user accounts,
		registration, email addresses, or cookies are required to share or view notes.
	</p>
	<h4>2. Shared Note Data</h4>
	<p>
		When you share a note, the raw text content is stored securely on the server to render it publicly
		for anyone with the shared link. Anyone with the unique link can view the note.
	</p>
	<h4>3. Privacy-Friendly Analytics</h4>
	<p>
		This website may collect basic, privacy-friendly usage metrics using tools like Plausible Analytics.
		Plausible Analytics is GDPR-compliant, cookie-less, and does not collect or track any personal
		identifying data.
	</p>
	<h4>4. IP Processing</h4>
	<p>
		IP addresses are processed temporarily in memory strictly for security and abuse mitigation (e.g.
		rate limiting note report submissions) and are never sold or shared with third parties.
	</p>
</LegalModal>
