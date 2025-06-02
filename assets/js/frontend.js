document.addEventListener('DOMContentLoaded', function() {
	const switcher = document.getElementById('carbon-switcher-toggle');
	if (!switcher) return;

	// Set initial value from URL or cookie
	const urlParams = new URLSearchParams(window.location.search);
	const intensity = urlParams.get('grid_intensity') || 'live';
	switcher.value = intensity;

	// Update body class based on initial intensity
	document.body.classList.add('grid-intensity-' + intensity);

	// Handle intensity change
	switcher.addEventListener('change', function(e) {
		const newIntensity = e.target.value;
		
		// Update URL with new intensity
		const newUrl = new URL(window.location.href);
		newUrl.searchParams.set('grid_intensity', newIntensity);
		
		// Redirect to the new URL to trigger server-side changes
		window.location.href = newUrl.toString();
	});

	// Handle tooltip
	const tooltip = document.getElementById('tooltip');
	if (tooltip) {
		tooltip.addEventListener('click', function() {
			const isExpanded = this.getAttribute('aria-expanded') === 'true';
			this.setAttribute('aria-expanded', !isExpanded);
		});
	}
}); 