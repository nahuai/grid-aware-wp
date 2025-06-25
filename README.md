# Grid Aware WordPress

A WordPress plugin that dynamically adjusts your website's content based on the current carbon intensity of the electrical grid in your visitors' location. This helps reduce the environmental impact of your website by optimizing content delivery based on real-time grid conditions.

## Description

Grid Aware WordPress is a plugin that helps website owners and developers create more sustainable and efficient websites by implementing grid-aware features. The plugin allows you to:

- Optimize images for grid-based layouts
- Manage video content in grid systems
- Control typography within grid layouts

## Features

- **Real-time Grid Intensity Detection**: Uses the Electricity Maps API to get live carbon intensity data
- **Automatic Geolocation**: Detects visitor location and fetches corresponding grid data
- **Dynamic Content Optimization**: Adjusts images, videos, and typography based on grid intensity
- **Live Preview**: Test different intensity levels in the WordPress editor
- **API Connection Testing**: Verify your Electricity Maps API key works correctly
- Grid-aware image handling
- Grid-aware video handling
- Grid-aware typography handling
- Easy-to-use settings page
- Translation ready

## Installation

1. Upload the plugin files to `/wp-content/plugins/grid-aware-wp/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Grid Aware WP' in the admin menu to configure settings

## Setup

### 1. Get an Electricity Maps API Key

1. Visit the [Electricity Maps Portal](https://portal.electricitymaps.com/dashboard)
2. Create an account and get your API key
3. The API key is required for live grid intensity data

### 2. Configure the Plugin

1. Go to **Grid Aware WP** in your WordPress admin menu
2. Enter your Electricity Maps API key
3. Click **Test API Connection** to verify it works
4. Enable the features you want (images, videos, typography)
5. Save your settings

### 3. Page-Specific Settings

You can also configure settings for individual pages:
1. Edit any page or post
2. Look for the **Grid Aware WP** panel in the document settings
3. Configure page-specific settings that override global settings

## How It Works

### Grid Intensity Levels

The plugin categorizes grid intensity into three levels:

- **Low** (< 200 gCO₂eq/kWh): Renewable-heavy grids
- **Medium** (200-500 gCO₂eq/kWh): Mixed grids  
- **High** (> 500 gCO₂eq/kWh): Fossil-heavy grids

### Content Optimization

#### Images
- **Low Intensity**: Original images with lazy loading
- **Medium Intensity**: Smaller image sizes for faster loading
- **High Intensity**: Images replaced with alt text to reduce bandwidth

#### Videos
- **Low Intensity**: Normal video playback
- **Medium Intensity**: Reduced quality or deferred autoplay
- **High Intensity**: Videos disabled or replaced with placeholders

#### Typography
- **Low Intensity**: Full font loading
- **Medium Intensity**: Optimized font loading
- **High Intensity**: System fonts only

### Live Mode

When visitors select "Live" mode, the plugin:
1. Detects their location via IP address
2. Fetches current carbon intensity from Electricity Maps
3. Automatically applies the appropriate optimization level
4. Shows a live data indicator with current grid information

## API Endpoints

The plugin provides several REST API endpoints:

- `GET /wp-json/grid-aware-wp/v1/intensity` - Get current carbon intensity
- `POST /wp-json/grid-aware-wp/v1/test-api` - Test API connection
- `GET /wp-json/grid-aware-wp/v1/settings` - Get plugin settings
- `POST /wp-json/grid-aware-wp/v1/settings` - Update plugin settings

## Supported Zones

The plugin supports major electricity grid zones including:
- Europe: FR, DE, GB, ES, IT, NL, BE, AT, CH, SE, NO, DK, FI, PL, CZ
- North America: US, CA
- Asia-Pacific: AU, JP, KR, CN, IN
- South America: BR, MX

## Troubleshooting

### API Connection Issues
- Verify your API key is correct
- Check that your API key has the necessary permissions
- Ensure your server can make outbound HTTPS requests

### Geolocation Issues
- The plugin uses IP-based geolocation
- Some VPNs or corporate networks may affect location detection
- Fallback to medium intensity if location cannot be determined

### Performance
- API responses are cached for 5 minutes to reduce API calls
- The plugin gracefully degrades if the API is unavailable
- No impact on site performance when disabled

## Development

### Building Assets
```bash
npm install
npm run build
```

### Development Mode
```bash
npm run start
```

## License

This plugin is licensed under the GPL v2 or later.

## Author

[Nahuai](https://github.com/nahuai)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. 

## Support

For support and feature requests, please visit the plugin's GitHub repository. 