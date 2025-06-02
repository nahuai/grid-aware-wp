import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { createRoot } from '@wordpress/element';
import { 
	Panel,
	PanelBody,
	PanelRow,
	ToggleControl,
	CheckboxControl,
	Button,
	Notice,
	Spinner
} from '@wordpress/components';
import { PluginPreviewMenuItem, PluginDocumentSettingPanel } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { select } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import './settings.css';

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
	});
	const [isSaving, setIsSaving] = useState(false);
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
		const newOptions = { ...options, [key]: value ? '1' : '0' };
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

			<h2>{__('Grid Aware WP Settings', 'grid-aware-wp')}</h2>
			<fieldset>
				<PanelRow>
					<CheckboxControl
						label={__('Enable grid-aware image handling', 'grid-aware-wp')}
						help={__('Optimizes images based on grid intensity, adjusting quality and loading strategies to reduce energy consumption.', 'grid-aware-wp')}
						checked={options.images === '1'}
						onChange={(val) => updateOption('images', val)}
					/>
				</PanelRow>
				<PanelRow>
					<CheckboxControl
						label={__('Enable grid-aware video handling', 'grid-aware-wp')}
						help={__('Manages video playback and quality based on grid conditions, potentially reducing resolution or deferring autoplay during high-intensity periods.', 'grid-aware-wp')}
						checked={options.videos === '1'}
						onChange={(val) => updateOption('videos', val)}
					/>
				</PanelRow>
				<PanelRow>
					<CheckboxControl
						label={__('Enable grid-aware typography handling', 'grid-aware-wp')}
						help={__('Adjusts font loading and rendering based on grid intensity, optimizing for energy efficiency while maintaining readability.', 'grid-aware-wp')}
						checked={options.typography === '1'}
						onChange={(val) => updateOption('typography', val)}
					/>
				</PanelRow>
			</fieldset>
			<Button
				variant="primary"
				onClick={saveOptions}
				isBusy={isSaving}
				disabled={isSaving}
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
				});
			}
		};

		fetchOptions();
	}, []);

	const updateOption = async (key, value) => {
		const newOptions = { ...options, [key]: value ? '1' : '0' };
		
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