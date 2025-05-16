# BuddyBoss Theme Darkmode Toggle

A WordPress plugin that adds light/dark mode toggle and profile frame options for BuddyBoss users, with full WordPress Multisite compatibility.

## Features

- Light/Dark mode toggle that persists across sessions
- Profile frame selection for avatar customization
- Settings page in WordPress admin
- Integration with BuddyBoss profile settings
- Shortcodes for adding toggle controls on any page
- Mobile-friendly design
- **Network-wide user preferences** - settings follow users across all subsites in multisite

## Requirements

- WordPress 6.8+
- BuddyBoss Platform / BuddyPress
- PHP 7.4+

## Installation in Multisite

### Option 1: Network Activation (recommended)
1. Upload the plugin files to the `/wp-content/plugins/bb-theme-darkmode-toggle` directory.
2. Network activate the plugin through the 'Plugins' screen in Network Admin.

### Option 2: Individual Site Activation
1. Upload the plugin files to the `/wp-content/plugins/bb-theme-darkmode-toggle` directory.
2. Activate the plugin individually on each site where you want to use it.

## Multisite Behavior

This plugin is specifically designed for multisite environments using BuddyBoss:

- User preferences (theme mode and profile frame) are stored as network-wide user meta
- Settings made on the main site will automatically sync to all subsites
- The admin settings page is available on each subsite, but changes affect all sites
- Users only need to set their preferences once, and they will be applied across all subsites

## Usage

### Admin Settings

Navigate to Settings -> BB Theme Darkmode Toggle to enable/disable features:

- Enable Dark Mode Toggle
- Enable Profile Frames

### User Profile Integration

The plugin adds a Theme Settings tab to the BuddyBoss user profile settings, allowing users to:

- Switch between light and dark mode
- Select a profile frame for their avatar

### Shortcodes

The plugin provides two shortcodes for adding toggle controls to any WordPress page:

#### Theme Toggle Shortcode

```
[bb_theme_darkmode_toggle style="switch" label="Theme Mode" class="custom-class"]
```

Options:
- `style`: Type of toggle control (switch, toggle, buttons)
- `label`: Text label for the toggle
- `class`: Additional CSS classes

Examples:
```
[bb_theme_darkmode_toggle]
[bb_theme_darkmode_toggle style="toggle" label="Switch Darkmode Theme"]
[bb_theme_darkmode_toggle style="buttons" label=""]
```

#### Profile Frame Selector Shortcode

```
[bb_profile_frame_selector style="select" label="Profile Frame" class="custom-class"]
```

Options:
- `style`: Type of selection control (select, radio, buttons)
- `label`: Text label for the selector
- `class`: Additional CSS classes

Examples:
```
[bb_profile_frame_selector]
[bb_profile_frame_selector style="radio" label="Choose Your Frame"]
[bb_profile_frame_selector style="buttons" label="Frame Selection"]
```

## Multisite Troubleshooting

- If preferences aren't syncing across sites, verify that the plugin is properly activated on all sites
- If changes made on one site aren't appearing on other sites, try clearing browser cache
- For advanced customization, network admins can modify the plugin code to use site-specific preferences instead of network-wide ones

## Customization

### CSS Variables

The plugin uses BuddyBoss CSS variables for seamless integration. You can override these variables in your theme's stylesheet:

```css
:root {
  --bb-primary-color: #your-color-code;
  /* More variables */
}
```

### Adding New Frames

To add new profile frames, you'll need to:

1. Add the new frame option in the main plugin class (`class-bb-theme-darkmode-toggle.php`)
2. Add corresponding CSS styles for the new frame in `bb-theme-darkmode-toggle.css`
3. Update the JavaScript function that applies frame styles

## License

GPL v2 or later