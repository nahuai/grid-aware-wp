[!WARNING]
> This is an experimental plugin in alpha stage and active development. It is currently not suitable for production use.

# Grid Aware WordPress

A WordPress plugin that dynamically adjusts your website's content based on the current carbon intensity of the electrical grid in your visitors' location. This helps reduce the environmental impact of your website by optimizing content delivery based on real-time grid conditions.

## The Context

This plugin has been created as a consequence of being part of the Green Web Foundation's [Grid-aware websites project](https://www.thegreenwebfoundation.org/news/introducing-our-grid-aware-websites-project/). You can visit [the article we wrote on Branch Magazine](https://branch.climateaction.tech/issues/issue-9/adapting-wordpress-to-grid-aware-web-experiences/) to better understand the context, how the plugin works and what it tries to achieve.

## Description

Grid Aware WordPress is a plugin that dynamically adjusts your website's content based on the current carbon intensity of the electrical grid in your visitors' location. With approximately 1.5 billion live websites, the internet's carbon footprint is comparable to that of the global aviation or shipping industry. Since 43% of these sites run on WordPress, even small improvements can have a real impact and help raise awareness about the internet's effect on the environment.

The plugin's main objective is to provide awareness through a tool that informs both users and publishers about the impact of their online activity. It provides standardized options for reducing that impact and paves the way to develop future WordPress websites more sustainably.

When the grid intensity is medium or high (meaning more fossil-fuel-based energy is being used), it automatically adapts the most demanding elements in terms of consumption — in this case, images, videos, and fonts — and includes additional information so that the user can decide whether to load these elements individually.

You can see the plugin in action in the [example page created on Sustain WP](https://sustainwp.com/grid-aware-wordpress/).

## Changelog

### Version 0.9.1 (Latest)
- **Simplified API Implementation**: Now exclusively uses the Electricity Maps `/carbon-intensity-level/latest` endpoint for cleaner, more focused functionality
- **Removed Carbon Intensity Values**: No longer displays raw gCO2eq/kWh values in the frontend, focusing on user-friendly intensity levels (low/medium/high)
- **Streamlined User Experience**: Simplified top bar displays only essential grid information without overwhelming technical details
- **Enhanced Code Clarity**: Removed zone-specific functionality and complex carbon intensity calculations for better maintainability

## Features

The plugin works on two fronts:

**Backend Features:**
- **Settings Page**: Define global or per-page behavior, enabling editors to decide which elements are critical and which can be progressively added as the grid becomes cleaner
- **Block Editor Controls**: Preview links, sidebar options, and block-specific controls that help editors make informed design decisions regarding sustainability
- **Admin Banner**: Visible warning message alerting site owners that visitors on a fossil-fuel-heavy grid will see a modified experience
- **Real-time Grid Intensity Detection**: Uses the Electricity Maps `/carbon-intensity-level/` API to get live grid intensity levels
- **API Connection Testing**: Verify your Electricity Maps API key works correctly

**Frontend Features:**
- **Top Bar**: Always visible information bar showing grid intensity level and zone with options to explore different views
- **Automatic Geolocation**: Detects visitor location and fetches corresponding grid intensity level
- **Dynamic Content Optimization**: Adjusts images, videos, and typography based on grid intensity level
- **Live Preview**: Test different intensity levels in the WordPress editor

## Current Limitations

* The changes on images, videos and typography is limited. At the moment:
  * Images: only modified when is a block image
  * Videos: only when is a Youtube embed video.
  * Typography: only when the active theme is a block theme.
* It relies on render_block filters for images and Youtube embeds modifications, so it only applies when block editor it's used. 
* The typography manipulation it's only done on block themes.
* There is no UI to change colors and typography of the frontend top bar.
* The frontend top bar hasn't be tested on mobile.

## Known Issues

* The Grid Aware settings on the block editor can't be saved if there is no change to the content.
* The placeholders won't display properly on small images.

## Installation

1. Upload the plugin files to `/wp-content/plugins/grid-aware-wp/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Grid Aware WP' in the admin menu to configure settings

## Setup

### 1. Get an Electricity Maps API Key

1. Visit the [Electricity Maps Portal](https://portal.electricitymaps.com/dashboard)
2. Create an account and get your API key
3. The API key is required for live grid intensity data
4. For detailed API documentation, visit the [Electricity Maps API Reference](https://portal.electricitymaps.com/developer-hub/api/reference#latest-carbon-intensity-level)

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

The plugin uses the Electricity Maps `/carbon-intensity-level/latest` API endpoint to categorize grid intensity into three levels based on relative comparisons to historical data:

- **Low**: Cleaner than usual grid conditions - more renewable energy in the mix
- **Medium**: Typical grid conditions - moderate mix of energy sources  
- **High**: Dirtier than usual grid conditions - more fossil fuel-based energy

These intensity levels are determined directly by the [Electricity Maps carbon-intensity-level API](https://portal.electricitymaps.com/developer-hub/api/reference#latest-carbon-intensity-level) and provide a relative measure of grid cleanliness without exposing raw carbon intensity values to users.

### Content Optimization

According to the 2024 HTTP Archive, the average web page size weighs 2,652 KB (2,311 KB on mobile) – and it's growing. Images alone account for almost 40% of that, JavaScript about 24%, and fonts 5%. An embedded YouTube video can add more than 1 MB.

The plugin adapts the display of heavy elements based on grid intensity:

#### Images
- **Low Intensity**: Images load exactly as they were added in the editor
- **Medium Intensity**: A smaller, slightly blurred version is displayed. Hovering it reaveals the alt text, an explanatory message, and a button to load the original image
- **High Intensity**: Images don't load by default. Instead, a placeholder with informative text and a button to load the content is displayed, along with alt text and a button to load the original image

#### Videos
- **Low Intensity**: Videos load exactly as they were added in the editor
- **Medium Intensity**: Only the cover art loads. Hovering over it reveals the video title, explanatory message, and a button to load the full video
- **High Intensity**: Videos don't load by default. Instead, a placeholder with informative text along with the video title and a button to load the content is displayed

#### Typography
- **Low Intensity**: Full font loading
- **Medium Intensity**: Full font loading
- **High Intensity**: System fonts only are loaded (Helvetica is displayed by default)

### Live Mode

When visitors select "Live" mode, the plugin:
1. Detects their location via IP address
2. Fetches current grid intensity level from Electricity Maps
3. Automatically applies the appropriate optimization level
4. Shows a live data indicator with current grid information (intensity level and zone)

The information bar has a standardized appearance, regardless of the website on which the plugin is installed. For consistency, we've kept the "Low," "Medium," and "High" labels in the view selector. However, these can potentially be confusing: Since the buttons change the layout, the user might interpret "Low" as offering a simpler design, when in fact it refers to a low-intensity power grid. 
Feedback on this is more than welcome.

## How to Use It

### For Testing and Development

**Recommended Setup:**
- Use a block theme (like Twenty Twenty-Five) for full functionality
- Create test pages with images and YouTube embeds
- Get an Electricity Maps API key for live data
- Use a VPN to test different geographic zones

**Testing Scenarios:**
- Test all three intensity levels (Low/Medium/High)
- Verify image and video placeholders work correctly
- Check typography changes on block themes
- Verify API fallback behavior when grid data is unavailable

**Development Notes:**
- The plugin uses render_block filters for content modification
- Works best with block editor content
- Typography changes only apply to block themes
- API responses are cached to reduce calls
- Graceful degradation when API is unavailable

## API Endpoints

The plugin provides several REST API endpoints:

- `GET /wp-json/grid-aware-wp/v1/intensity` - Get current grid intensity level (low/medium/high)
- `POST /wp-json/grid-aware-wp/v1/test-api` - Test API connection
- `GET /wp-json/grid-aware-wp/v1/settings` - Get plugin settings
- `POST /wp-json/grid-aware-wp/v1/settings` - Update plugin settings

## Supported Zones

The plugin supports all zones that Electricity Maps (EM) provides data for. This includes major electricity grid zones across Europe, North America, Asia-Pacific, South America, and other regions. The exact list of supported zones depends on the current EM API coverage and may change over time as new zones are added to their service.

## Next Planned Steps

* Use [the web component created by the GWF](https://github.com/thegreenwebfoundation/gaw-web-component) for the frontend top bar.
* Provide a UI to customize colors and typography of the frontend top bar.
* Use Modern Font Stacks to improve typography appearance on high-intensity.
* Improve caching (with transients). This has not yet be implemented to facilitate the plugin testing.
* Add a documentation tab with documentation and screenshots.
* Apply server-side changes to feature image block (and other blocks that display images). First solve how to display correctly the placeholder on small images (may be reduce the amount of information on it).
* Optimize CSS from frontend.css (reduce and load it conditionally).
* Improve placeholder size management so it has the same size as the image or embed video.
* Explore ways to modify typography on more themes, safely.
* Explore ways to modify images and embeds on the classic editor on a safe way.

## Troubleshooting

### API Connection Issues
- Verify your API key is correct
- Check that your API key has the necessary permissions
- Ensure your server can make outbound HTTPS requests

## Development (In case you want to change the plugin or editor settings)

### Building Assets
```bash
npm install
npm run build
```

### Development Mode
```bash
npm run start
```

## Feedback

We welcome feedback on:
* Overall usability of the plugin.
* Wording changes to make the plugin usage and undertanding better are more than welcome.
* Suggestions/doubts about the plugin functionality.
* The plugin now changes the Youtube embeds to be loaded from youtube-nocookie.com domain. This is quite opinionated, it adds privacy and reduces the tracking but there is not much savings in term of bytes. I still see the benefit but I don't want to "impose". What do you think?
* Decide if remove lite-youtube (a lightweight Youtube player) assets (used on early stages of the plugin but not now) or think if could be beneficial on some scenarios. Right now it does not look to add anything extra feature since we already use placeholders and it looks like it needs to load the whole player JS anyway when the play it's hit.
* Decide if apply the lazy-load with [IntersectionObserver API](https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API) on Youtube embeds. I saw some reducement on the loading size depending on the page scroll on a few test I did.

## License

This plugin is licensed under the GPL v2 or later.

## Authors

[Nahuai Badiola](https://nbadiola.com) - Development

[Nora Ferreirós](https://noraferreiros.com) - UX/UI Design

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. 

## Support

For support and feature requests, please visit the plugin's GitHub repository. 
