import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { createRoot } from '@wordpress/element';
import { 
	Panel,
	PanelBody,
	PanelRow,
	ToggleControl,
	CheckboxControl,
	TextControl,
	Button,
	Notice,
	Spinner
} from '@wordpress/components';
import { PluginPreviewMenuItem, PluginDocumentSettingPanel } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { select } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import './settings.css';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';

const OPTION_NAME = 'grid_aware_wp_options';

const openPreviewWithGridIntensity = (intensity) => {
	const previewUrl = select('core/editor').getEditedPostPreviewLink();
	if (previewUrl) {
		const url = new URL(previewUrl, window.location.origin);
		url.searchParams.set('grid_intensity', intensity);
		window.open(url.toString(), '_blank');
	}
};

const wpPreviewIcon = (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" style={{ marginLeft: '0.5em', verticalAlign: 'middle' }}>
		<path d="M19.5 4.5h-7V6h4.44l-5.97 5.97 1.06 1.06L18 7.06v4.44h1.5v-7Zm-13 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3H17v3a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h3V5.5h-3Z"></path>
	</svg>
);

const GridIntensityPreview = () => (
	<>
		<PluginPreviewMenuItem onClick={() => openPreviewWithGridIntensity('low')}>
			Low Grid Intensity
		</PluginPreviewMenuItem>
		<PluginPreviewMenuItem onClick={() => openPreviewWithGridIntensity('medium')}>
			Medium Grid Intensity
		</PluginPreviewMenuItem>
		<PluginPreviewMenuItem onClick={() => openPreviewWithGridIntensity('high')}>
			High Grid Intensity
		</PluginPreviewMenuItem>
	</>
);

const GridAwareWPSettings = () => {
	const [options, setOptions] = useState({
		images: '1',
		videos: '1',
		typography: '1',
		api_key: '',
	});
	const [isSaving, setIsSaving] = useState(false);
	const [isTestingApi, setIsTestingApi] = useState(false);
	const [notice, setNotice] = useState(null);
	const [isLoading, setIsLoading] = useState(true);

	useEffect(() => {
		const fetchOptions = async () => {
			try {
				const response = await apiFetch({
					path: `/grid-aware-wp/v1/settings`,
				});
				
				if (response) {
					setOptions(response);
				}
			} catch (error) {
				setNotice({
					type: 'error',
					message: __('Error loading settings.', 'grid-aware-wp')
				});
			} finally {
				setIsLoading(false);
			}
		};

		fetchOptions();
	}, []);

	const updateOption = async (key, value) => {
		const newValue = key === 'api_key' ? value : (value ? '1' : '0');
		const newOptions = { ...options, [key]: newValue };
		setOptions(newOptions);
	};

	const saveOptions = async () => {
		setIsSaving(true);
		setNotice(null);

		try {
			const response = await apiFetch({
				path: `/grid-aware-wp/v1/settings`,
				method: 'POST',
				data: {
					options: options,
				},
			});

			if (response) {
				setNotice({
					type: 'success',
					message: __('Settings saved successfully.', 'grid-aware-wp')
				});
			}
		} catch (error) {
			setNotice({
				type: 'error',
				message: __('Error saving settings.', 'grid-aware-wp')
			});
		} finally {
			setIsSaving(false);
		}
	};

	const testApiConnection = async () => {
		if (!options.api_key) {
			setNotice({
				type: 'error',
				message: __('Please enter an API key first.', 'grid-aware-wp')
			});
			return;
		}

		setIsTestingApi(true);
		setNotice(null);

		try {
			const response = await apiFetch({
				path: `/grid-aware-wp/v1/test-api`,
				method: 'POST',
				data: {
					api_key: options.api_key,
					zone: 'ES', // Test with Spain as default
				},
			});

			if (response && response.success) {
				setNotice({
					type: 'success',
					message: __('API connection successful! Carbon intensity data is available.', 'grid-aware-wp')
				});
			} else {
				setNotice({
					type: 'error',
					message: __('API connection failed. Please check your API key.', 'grid-aware-wp')
				});
			}
		} catch (error) {
			setNotice({
				type: 'error',
				message: __('API connection failed: ' + (error.message || 'Unknown error'), 'grid-aware-wp')
			});
		} finally {
			setIsTestingApi(false);
		}
	};

	if (isLoading) {
		return (
			<div className="grid-aware-wp-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="grid-aware-wp-settings">
			{notice && (
				<Notice
					status={notice.type}
					onRemove={() => setNotice(null)}
				>
					{notice.message}
				</Notice>
			)}

			{/* API Key Section */}
			<h2>{__('Electricity Maps API Key', 'grid-aware-wp')}</h2>
			<p>{__('An API key is required to fetch real-time grid intensity data from Electricity Maps. This allows the plugin to optimize your site based on current energy conditions. You can get your API key from the ', 'grid-aware-wp')}
				<a href="https://portal.electricitymaps.com/dashboard" target="_blank" rel="noopener noreferrer">
					{__('Electricity Maps dashboard', 'grid-aware-wp')}
				</a>.
			</p>
			<div style={{ display: 'flex', gap: '1em', marginBottom: '1.5em' }}>
				<TextControl
					value={options.api_key}
					onChange={(val) => updateOption('api_key', val)}
					style={{ flex: 1, height: '36px' }}
					label={null}
					placeholder={__('Enter your API key', 'grid-aware-wp')}
					__next40pxDefaultSize={true}
					__nextHasNoMarginBottom={true}
				/>
				<Button
					variant="secondary"
					onClick={testApiConnection}
					isBusy={isTestingApi}
					disabled={isTestingApi || !options.api_key}
				>
					{isTestingApi ? __('Testing...', 'grid-aware-wp') : __('Test API Connection', 'grid-aware-wp')}
				</Button>
			</div>

			{/* Settings Section */}
			<h2>{__('Grid Aware WP Settings', 'grid-aware-wp')}</h2>
			<fieldset>
				<PanelRow>
					<CheckboxControl
						label={__('Enable grid-aware image handling', 'grid-aware-wp')}
						help={__('Optimizes images based on grid intensity, adjusting quality and loading strategies to reduce energy consumption.', 'grid-aware-wp')}
						checked={options.images === '1'}
						onChange={(val) => updateOption('images', val)}
						__nextHasNoMarginBottom={true}
					/>
				</PanelRow>
				<PanelRow>
					<CheckboxControl
						label={__('Enable grid-aware video handling', 'grid-aware-wp')}
						help={__('Manages video playback and quality based on grid conditions, potentially reducing resolution or deferring autoplay during high-intensity periods.', 'grid-aware-wp')}
						checked={options.videos === '1'}
						onChange={(val) => updateOption('videos', val)}
						__nextHasNoMarginBottom={true}
					/>
				</PanelRow>
				<PanelRow>
					<CheckboxControl
						label={__('Enable grid-aware typography handling', 'grid-aware-wp')}
						help={__('Adjusts font loading and rendering based on grid intensity, optimizing for energy efficiency while maintaining readability.', 'grid-aware-wp')}
						checked={options.typography === '1'}
						onChange={(val) => updateOption('typography', val)}
						__nextHasNoMarginBottom={true}
					/>
				</PanelRow>
			</fieldset>
			<Button
				variant="primary"
				onClick={saveOptions}
				isBusy={isSaving}
				disabled={isSaving}
				style={{ marginTop: '1em' }}
			>
				{isSaving ? __('Saving...', 'grid-aware-wp') : __('Save Settings', 'grid-aware-wp')}
			</Button>
		</div>
	);
};

const GridAwareWPPanel = () => {
	const [options, setOptions] = useState({
		images: '1',
		videos: '1',
		typography: '1',
		api_key: '',
	});
	const [isPageSpecific, setIsPageSpecific] = useState(false);

	useEffect(() => {
		const postId = select('core/editor').getCurrentPostId();
		
		const fetchOptions = async () => {
			try {
				const response = await apiFetch({
					path: `/grid-aware-wp/v1/settings${postId ? `?post_id=${postId}` : ''}`,
				});
				
				if (response) {
					setOptions(response);
					setIsPageSpecific(!!postId);
				}
			} catch (error) {
				const pluginOptions = window.gridAwareWPOptions || {};
				setOptions({
					images: pluginOptions.images !== undefined ? pluginOptions.images : '1',
					videos: pluginOptions.videos !== undefined ? pluginOptions.videos : '1',
					typography: pluginOptions.typography !== undefined ? pluginOptions.typography : '1',
					api_key: pluginOptions.api_key !== undefined ? pluginOptions.api_key : '',
				});
			}
		};

		fetchOptions();
	}, []);

	const updateOption = async (key, value) => {
		const newValue = key === 'api_key' ? value : (value ? '1' : '0');
		const newOptions = { ...options, [key]: newValue };
		
		try {
			const postId = select('core/editor').getCurrentPostId();
			const response = await apiFetch({
				path: `/grid-aware-wp/v1/settings${postId ? `?post_id=${postId}` : ''}`,
				method: 'POST',
				data: {
					options: newOptions,
				},
			});
			
			if (response) {
				setOptions(response);
			}
		} catch (error) {
			setOptions(options);
		}
	};

	return (
		<PluginDocumentSettingPanel
			name="grid-aware-wp-panel"
			title={__('Grid Aware WP', 'grid-aware-wp')}
			className="grid-aware-wp-panel"
		>
			<p>
				{isPageSpecific 
					? __('These settings will override the global settings for this page only.', 'grid-aware-wp')
					: __('These are the global settings that will be used by default.', 'grid-aware-wp')
				}
			</p>
			<PanelRow>
				<CheckboxControl
					label={__('Enable grid-aware image handling', 'grid-aware-wp')}
					checked={options.images === '1'}
					onChange={(val) => updateOption('images', val)}
					__nextHasNoMarginBottom={true}
				/>
			</PanelRow>
			<p className="description">
				{__('Optimizes images based on grid intensity, adjusting quality and loading strategies to reduce energy consumption.', 'grid-aware-wp')}
			</p>
			<PanelRow>
				<CheckboxControl
					label={__('Enable grid-aware video handling', 'grid-aware-wp')}
					checked={options.videos === '1'}
					onChange={(val) => updateOption('videos', val)}
					__nextHasNoMarginBottom={true}
				/>
			</PanelRow>
			<p className="description">
				{__('Manages video playback and quality based on grid conditions, potentially reducing resolution or deferring autoplay during high-intensity periods.', 'grid-aware-wp')}
			</p>
			<PanelRow>
				<CheckboxControl
					label={__('Enable grid-aware typography handling', 'grid-aware-wp')}
					checked={options.typography === '1'}
					onChange={(val) => updateOption('typography', val)}
					__nextHasNoMarginBottom={true}
				/>
			</PanelRow>
			<p className="description">
				{__('Adjusts font loading and rendering based on grid intensity, optimizing for energy efficiency while maintaining readability.', 'grid-aware-wp')}
			</p>
			<div style={{ marginTop: '1.5em' }}>
				<strong>{__('Preview with grid intensity:', 'grid-aware-wp')}</strong>
				<div style={{ display: 'flex', gap: '0.5em', marginTop: '0.5em' }}>
					<Button
						isSecondary
						onClick={() => openPreviewWithGridIntensity('low')}
					>
						{__('Low', 'grid-aware-wp')}
					</Button>
					<Button
						isSecondary
						onClick={() => openPreviewWithGridIntensity('medium')}
					>
						{__('Medium', 'grid-aware-wp')}
					</Button>
					<Button
						isSecondary
						onClick={() => openPreviewWithGridIntensity('high')}
					>
						{__('High', 'grid-aware-wp')}
					</Button>
				</div>
			</div>
		</PluginDocumentSettingPanel>
	);
};

// Initialize the settings page
document.addEventListener('DOMContentLoaded', () => {
	const settingsContainer = document.getElementById('grid-aware-wp-settings');
	if (settingsContainer) {
		const root = createRoot(settingsContainer);
		root.render(<GridAwareWPSettings />);
	}
});

// Only register plugins if we're in the editor context
if (window.wp?.plugins) {
	// Register the editor plugins
	registerPlugin('grid-aware-wp', {
		render: GridAwareWPPanel,
	});

	registerPlugin('grid-aware-wp-preview', {
		render: GridIntensityPreview,
		icon: wpPreviewIcon,
	});
}

// Add a warning notice to the Image block in the editor
const withGridAwareImageNotice = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const isImageBlock = props.name === 'core/image';
		const isYouTubeEmbed =
			props.name === 'core/embed' &&
			props.attributes &&
			typeof props.attributes.url === 'string' &&
			/^(https?:)?\/\/(www\.)?(youtube\.com|youtu\.be)\//.test(props.attributes.url);

		if (isImageBlock || isYouTubeEmbed) {
			return (
				<Fragment>
					<BlockEdit {...props} />
					<Notice
						status="warning"
						isDismissible={false}
					>
						{isImageBlock && __('Images may display differently depending on the current grid intensity (Grid Aware WP).', 'grid-aware-wp')}
						{isYouTubeEmbed && __('YouTube videos may display or behave differently depending on the current grid intensity (Grid Aware WP).', 'grid-aware-wp')}
						<br />
						{__('If you do not want this behavior, you can disable it in the ', 'grid-aware-wp')}
						<a
							href={window.location.origin + '/wp-admin/admin.php?page=grid-aware-wp'}
							onClick={e => {
								e.preventDefault();
								window.open(window.location.origin + '/wp-admin/admin.php?page=grid-aware-wp', '_blank', 'noopener,noreferrer');
							}}
							rel="noopener noreferrer"
						>
							{__('Grid Aware WP plugin general settings', 'grid-aware-wp')}
						</a>
						{__(' or in the sidebar settings for this page.', 'grid-aware-wp')}
					</Notice>
				</Fragment>
			);
		}
		return <BlockEdit {...props} />;
	};
}, 'withGridAwareImageNotice');

addFilter(
	'editor.BlockEdit',
	'grid-aware-wp/with-grid-aware-image-notice',
	withGridAwareImageNotice
); 