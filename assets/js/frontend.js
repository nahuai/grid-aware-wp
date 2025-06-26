document.addEventListener('DOMContentLoaded', function() {
	// New: Checkbox-based grid intensity switcher
	const toggleGroup = document.querySelector('.grid-intensity-toggle-group');
	if (toggleGroup) {
		toggleGroup.addEventListener('click', function(e) {
			const label = e.target.closest('label.grid-intensity-toggle');
			if (!label) return;

			const input = label.querySelector('input[type="checkbox"]');
			if (!input) return;

			// Only allow one active at a time
			toggleGroup.querySelectorAll('input[type="checkbox"]').forEach(cb => {
				cb.checked = false;
				cb.parentElement.classList.remove('active');
			});
			input.checked = true;
			label.classList.add('active');

			const newIntensity = input.value;
			const newUrl = new URL(window.location.href);
			newUrl.searchParams.set('grid_intensity', newIntensity);
			window.location.href = newUrl.toString();
		});
	}

	// Tooltip logic (unchanged)
	const tooltip = document.getElementById('tooltip');
	if (tooltip) {
		tooltip.addEventListener('click', function() {
			const isExpanded = this.getAttribute('aria-expanded') === 'true';
			this.setAttribute('aria-expanded', !isExpanded);
		});
	}

	// Default to 'low' if grid_intensity is not set or is 'live'
	const urlParams = new URLSearchParams(window.location.search);
	let intensity = urlParams.get('grid_intensity');
	if (!intensity || intensity === 'live') {
		intensity = 'low';
		urlParams.set('grid_intensity', 'low');
		window.history.replaceState({}, '', `${window.location.pathname}?${urlParams}`);
	}
});

/**
 * Fetch live carbon intensity from the API
 */
async function fetchLiveIntensity() {
	try {
		// Get the REST API URL
		const restUrl = window.wpApiSettings ? window.wpApiSettings.root : '/wp-json/';
		const response = await fetch(restUrl + 'grid-aware-wp/v1/intensity');

		const data = await response.json();

		if (!response.ok) {
			// Use the error message from the backend if available
			const errorMessage = data.message || 'Failed to fetch live intensity data.';
			throw new Error(errorMessage);
		}
		
		// Update the page with the live intensity
		updatePageWithIntensity(data.intensity_level, data);
		
		return data;
	} catch (error) {
		console.error('Grid Aware WP Error:', error.message);
		
		// Fallback to medium intensity if API fails
		updatePageWithIntensity('medium', {
			intensity_level: 'medium',
			carbonIntensity: 300,
			zone: 'unknown (fallback)',
			timestamp: new Date().toISOString(),
			error: error.message
		});
		
		throw error;
	}
}

/**
 * Update the page with the given intensity level
 */
function updatePageWithIntensity(intensityLevel, data) {
	// Remove existing intensity classes
	document.body.classList.remove('grid-intensity-low', 'grid-intensity-medium', 'grid-intensity-high');
	
	// Add new intensity class
	document.body.classList.add('grid-intensity-' + intensityLevel);
	
	// Update the switcher if it exists
	const switcher = document.getElementById('carbon-switcher-toggle');
	if (switcher) {
		switcher.value = intensityLevel;
	}
	
	// Add intensity info to the page
	addIntensityInfo(data);
	
	// Trigger any custom events for other scripts
	window.dispatchEvent(new CustomEvent('gridIntensityChanged', {
		detail: {
			intensity: intensityLevel,
			data: data
		}
	}));
}
