<script lang="ts">
	import LegalModal from './LegalModal.svelte';
	let { open = false, onclose = () => {} } = $props<{ open: boolean; onclose?: () => void }>();
</script>

<LegalModal {open} title="Terms of Service" {onclose}>
	<h4>1. Service Provision & Disclaimer</h4>
	<p>
		Just Share Please is provided on an "AS IS" and "AS AVAILABLE" basis without warranties of any kind,
		express or implied. The service operator makes no guarantees regarding availability, reliability,
		security, or data retention.
	</p>
	<h4>2. Limitation of Liability</h4>
	<p>
		To the maximum extent permitted by applicable law, the owner and operator of this service shall NOT be
		held liable for any direct, indirect, incidental, special, consequential, or exemplary damages,
		including but not limited to loss of data, loss of profits, service interruption, server downtime, or
		any issues arising from the use of or inability to use this service.
	</p>
	<h4>3. User Responsibility & Content Restrictions</h4>
	<p>
		You are solely responsible for all content you share using this service. You agree not to upload or
		share any content that is illegal, abusive, defamatory, copyright-infringing, contains malware, or
		violates the privacy of others.
	</p>
	<h4>4. Content Removal</h4>
	<p>
		We reserve the right to remove any shared note or disable access to content at any time, for any
		reason, without prior notice.
	</p>
</LegalModal>
