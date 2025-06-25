document.addEventListener('DOMContentLoaded', function() {
	const switcher = document.getElementById('carbon-switcher-toggle');
	if (!switcher) return;

	// Set initial value from URL or cookie
	const urlParams = new URLSearchParams(window.location.search);
	const intensity = urlParams.get('grid_intensity') || 'live';
	switcher.value = intensity;

	// Update body class based on initial intensity
	document.body.classList.add('grid-intensity-' + intensity);

	// If intensity is 'live', fetch the current carbon intensity from API
	if (intensity === 'live') {
		fetchLiveIntensity();
	}

	// Handle intensity change
	switcher.addEventListener('change', function(e) {
		const newIntensity = e.target.value;
		
		// Update URL with new intensity
		const newUrl = new URL(window.location.href);
		newUrl.searchParams.set('grid_intensity', newIntensity);
		
		// If switching to live, fetch the current intensity
		if (newIntensity === 'live') {
			fetchLiveIntensity().then(() => {
				// Redirect to the new URL to trigger server-side changes
				window.location.href = newUrl.toString();
			}).catch(() => {
				// If API fails, still redirect but with fallback
				window.location.href = newUrl.toString();
			});
		} else {
			// Redirect to the new URL to trigger server-side changes
			window.location.href = newUrl.toString();
		}
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

/**
 * Add intensity information to the page
 */
function addIntensityInfo(data) {
	// Remove existing intensity info
	const existingInfo = document.querySelector('.grid-intensity-info');
	if (existingInfo) {
		existingInfo.remove();
	}
	
	// Create intensity info element
	const infoDiv = document.createElement('div');
	infoDiv.className = 'grid-intensity-info';
	infoDiv.setAttribute('data-intensity', data.intensity_level || 'unknown');
	
	const intensityText = data.intensity_level || 'unknown';
	const carbonValue = data.carbonIntensity !== undefined && data.carbonIntensity !== null ? Math.round(data.carbonIntensity) : 'N/A';
	const zone = data.zone || 'unknown';
	const errorText = data.error ? `<div style="color: #fca5a5; margin-top: 4px; font-size: 10px; border-top: 1px solid #555; padding-top: 4px;">Note: ${data.error}</div>` : '';
	
	infoDiv.innerHTML = `
		<div><strong>Grid Intensity:</strong> ${intensityText}</div>
		<div><strong>Carbon:</strong> ${carbonValue} gCO₂eq/kWh</div>
		<div><strong>Zone:</strong> ${zone}</div>
		${errorText}
	`;
	
	document.body.appendChild(infoDiv);
	
	// Auto-hide after 5 seconds if there's no error
	if (!data.error) {
		setTimeout(() => {
			if (infoDiv.parentNode) {
				infoDiv.style.opacity = '0';
				setTimeout(() => {
					if (infoDiv.parentNode) {
						infoDiv.remove();
					}
				}, 500);
			}
		}, 5000);
	}
} 