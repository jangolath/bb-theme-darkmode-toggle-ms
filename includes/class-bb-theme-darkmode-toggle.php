<?php
/**
 * Main plugin class
 */
class BB_Theme_Darkmode_Toggle {
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin
     */
    public function initialize() {
        // Load plugin text domain
        add_action('init', array($this, 'load_plugin_textdomain'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add settings page
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add profile settings section
        add_action('bp_members_screen_display_settings', array($this, 'add_theme_settings_to_profile'));
        add_action('bp_core_general_settings_after_save', array($this, 'save_theme_settings'));
        
        // Alternative: Add settings to Account > General Settings
        add_action('bp_after_profile_settings_submit_buttons', array($this, 'add_theme_settings_to_general'));
        
        // Add body class based on user preference
        add_filter('body_class', array($this, 'add_body_class'));
        
        // Add menu item to BuddyBoss profile menu
        add_action('bp_setup_nav', array($this, 'setup_nav'));
        
        // Register shortcodes
        add_shortcode('bb_theme_darkmode_toggle', array($this, 'theme_darkmode_toggle_shortcode'));
        add_shortcode('bb_profile_frame_selector', array($this, 'profile_frame_shortcode'));
        
        // Setup AJAX handler
        add_action('wp_ajax_bb_update_theme_preference', array($this, 'ajax_update_preference'));
    }

    /**
     * Load the plugin text domain for translation
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain('bb-theme-toggle', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on front-end
        if (!is_admin()) {
            wp_enqueue_style(
                'bb-theme-toggle-css',
                BB_THEME_DARKMODE_TOGGLE_PLUGIN_URL . 'assets/css/bb-theme-darkmode-toggle.css',
                array(),
                BB_THEME_DARKMODE_TOGGLE_VERSION
            );

            wp_enqueue_script(
                'bb-theme-toggle-js',
                BB_THEME_DARKMODE_TOGGLE_PLUGIN_URL . 'assets/js/bb-theme-darkmode-toggle.js',
                array('jquery'),
                BB_THEME_DARKMODE_TOGGLE_VERSION,
                true
            );

            // Pass data to JavaScript
            $user_id = get_current_user_id();
            
            // Use network-wide user meta keys (no blog ID in the key)
            $theme_mode = get_user_meta($user_id, 'bb_theme_mode', true) ?: 'light';
            $profile_frame = get_user_meta($user_id, 'bb_profile_frame', true) ?: 'default';
            
            wp_localize_script('bb-theme-toggle-js', 'bbThemeToggle', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bb-theme-toggle-nonce'),
                'userId' => $user_id,
                'themeMode' => $theme_mode,
                'profileFrame' => $profile_frame,
                'networkWide' => true // Flag indicating network-wide preferences
            ));
        }
    }

    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_options_page(
            __('BuddyBoss Theme Darkmode Toggle', 'bb-theme-darkmode-toggle'),
            __('BB Theme Darkmode Toggle', 'bb-theme-darkmode-toggle'),
            'manage_options',
            'bb-theme-darkmode-toggle',
            array($this, 'display_admin_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('bb_theme_darkmode_toggle_settings', 'bb_theme_darkmode_toggle_settings');
        
        add_settings_section(
            'bb_theme_darkmode_toggle_general_section',
            __('General Settings', 'bb-theme-toggle'),
            array($this, 'general_section_callback'),
            'bb_theme_darkmode_toggle'
        );
        
        add_settings_field(
            'enable_dark_mode',
            __('Enable Dark Mode', 'bb-theme-toggle'),
            array($this, 'enable_dark_mode_callback'),
            'bb_theme_darkmode_toggle',
            'bb_theme_darkmode_toggle_general_section'
        );
        
        add_settings_field(
            'enable_profile_frames',
            __('Enable Profile Frames', 'bb-theme-toggle'),
            array($this, 'enable_profile_frames_callback'),
            'bb_theme_darkmode_toggle',
            'bb_theme_darkmode_toggle_general_section'
        );
    }

    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure settings for BuddyBoss Theme Darkmode Toggle plugin.', 'bb-theme-darkmode-toggle') . '</p>';
    }

    /**
     * Enable dark mode callback
     */
    public function enable_dark_mode_callback() {
        $options = get_option('bb_theme_darkmode_toggle_settings', array(
            'enable_dark_mode' => 'yes',
            'enable_profile_frames' => 'yes'
        ));
        
        $checked = isset($options['enable_dark_mode']) ? $options['enable_dark_mode'] : 'yes';
        
        echo '<input type="checkbox" id="enable_dark_mode" name="bb_theme_darkmode_toggle_settings[enable_dark_mode]" value="yes" ' . checked('yes', $checked, false) . ' />';
        echo '<label for="enable_dark_mode">' . __('Allow users to switch between light and dark mode', 'bb-theme-toggle') . '</label>';
    }

    /**
     * Enable profile frames callback
     */
    public function enable_profile_frames_callback() {
        $options = get_option('bb_theme_darkmode_toggle_settings', array(
            'enable_dark_mode' => 'yes',
            'enable_profile_frames' => 'yes'
        ));
        
        $checked = isset($options['enable_profile_frames']) ? $options['enable_profile_frames'] : 'yes';
        
        echo '<input type="checkbox" id="enable_profile_frames" name="bb_theme_darkmode_toggle_settings[enable_profile_frames]" value="yes" ' . checked('yes', $checked, false) . ' />';
        echo '<label for="enable_profile_frames">' . __('Allow users to select profile frames', 'bb-theme-toggle') . '</label>';
    }

    /**
     * Display admin page
     */
    public function display_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('bb_theme_darkmode_toggle_settings');
                do_settings_sections('bb_theme_darkmode_toggle');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Add theme settings to user profile
     */
    public function add_theme_settings_to_profile() {
        $user_id = bp_displayed_user_id();
        $theme_mode = get_user_meta($user_id, 'bb_theme_mode', true) ?: 'light';
        $profile_frame = get_user_meta($user_id, 'bb_profile_frame', true) ?: 'default';
        
        $options = get_option('bb_theme_darkmode_toggle_settings', array(
            'enable_dark_mode' => 'yes',
            'enable_profile_frames' => 'yes'
        ));
        
        ?>
        <div class="bb-theme-toggle-settings">
            <h2><?php _e('Theme Settings', 'bb-theme-toggle'); ?></h2>
            
            <?php if (isset($options['enable_dark_mode']) && $options['enable_dark_mode'] === 'yes'): ?>
            <div class="bb-theme-mode-toggle">
                <label><?php _e('Theme Mode', 'bb-theme-toggle'); ?></label>
                <div class="theme-mode-options">
                    <label>
                        <input type="radio" name="bb_theme_mode" value="light" <?php checked('light', $theme_mode); ?> />
                        <?php _e('Light Mode', 'bb-theme-toggle'); ?>
                    </label>
                    <label>
                        <input type="radio" name="bb_theme_mode" value="dark" <?php checked('dark', $theme_mode); ?> />
                        <?php _e('Dark Mode', 'bb-theme-toggle'); ?>
                    </label>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (isset($options['enable_profile_frames']) && $options['enable_profile_frames'] === 'yes'): ?>
            <div class="bb-profile-frame-select">
                <label><?php _e('Profile Frame', 'bb-theme-toggle'); ?></label>
                <div class="profile-frame-options">
                    <label>
                        <input type="radio" name="bb_profile_frame" value="default" <?php checked('default', $profile_frame); ?> />
                        <span class="frame-preview frame-default"><?php _e('Default', 'bb-theme-toggle'); ?></span>
                    </label>
                    <label>
                        <input type="radio" name="bb_profile_frame" value="frame1" <?php checked('frame1', $profile_frame); ?> />
                        <span class="frame-preview frame-1"><?php _e('Frame 1', 'bb-theme-toggle'); ?></span>
                    </label>
                    <label>
                        <input type="radio" name="bb_profile_frame" value="frame2" <?php checked('frame2', $profile_frame); ?> />
                        <span class="frame-preview frame-2"><?php _e('Frame 2', 'bb-theme-toggle'); ?></span>
                    </label>
                    <label>
                        <input type="radio" name="bb_profile_frame" value="frame3" <?php checked('frame3', $profile_frame); ?> />
                        <span class="frame-preview frame-3"><?php _e('Frame 3', 'bb-theme-toggle'); ?></span>
                    </label>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add theme settings to general tab
     */
    public function add_theme_settings_to_general() {
        // Reuse the same function
        $this->add_theme_settings_to_profile();
    }

    /**
     * Save theme settings
     */
    public function save_theme_settings() {
        if (!isset($_POST['bb_theme_mode']) && !isset($_POST['bb_profile_frame'])) {
            return;
        }
        
        $user_id = bp_displayed_user_id();
        
        if (isset($_POST['bb_theme_mode'])) {
            $theme_mode = sanitize_text_field($_POST['bb_theme_mode']);
            // Use network-wide meta key (no blog ID)
            update_user_meta($user_id, 'bb_theme_mode', $theme_mode);
        }
        
        if (isset($_POST['bb_profile_frame'])) {
            $profile_frame = sanitize_text_field($_POST['bb_profile_frame']);
            // Use network-wide meta key (no blog ID)
            update_user_meta($user_id, 'bb_profile_frame', $profile_frame);
        }
    }

    /**
     * Add body class based on user preference
     */
    public function add_body_class($classes) {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            
            // Use network-wide meta keys (no blog ID)
            $theme_mode = get_user_meta($user_id, 'bb_theme_mode', true) ?: 'light';
            $profile_frame = get_user_meta($user_id, 'bb_profile_frame', true) ?: 'default';
            
            $classes[] = 'bb-theme-mode-' . $theme_mode;
            $classes[] = 'bb-profile-frame-' . $profile_frame;
        }
        
        return $classes;
    }

    /**
     * Setup navigation
     */
    public function setup_nav() {
        // Only add this if we're using a dedicated page approach
        $options = get_option('bb_theme_darkmode_toggle_settings', array());
        
        // Add the settings sub item
        bp_core_new_subnav_item(array(
            'name' => __('Theme Settings', 'bb-theme-toggle'),
            'slug' => 'theme-settings',
            'parent_url' => bp_loggedin_user_domain() . bp_get_settings_slug() . '/',
            'parent_slug' => bp_get_settings_slug(),
            'screen_function' => array($this, 'theme_settings_screen'),
            'position' => 30,
            'user_has_access' => bp_is_my_profile()
        ));
    }

    /**
     * Theme settings screen
     */
    public function theme_settings_screen() {
        // Add title and content here
        add_action('bp_template_title', array($this, 'theme_settings_title'));
        add_action('bp_template_content', array($this, 'theme_settings_content'));
        
        // Load the template
        bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
    }

    /**
     * Theme settings title
     */
    public function theme_settings_title() {
        echo __('Theme Settings', 'bb-theme-toggle');
    }

    /**
     * Theme settings content
     */
    public function theme_settings_content() {
        $user_id = bp_displayed_user_id();
        $theme_mode = get_user_meta($user_id, 'bb_theme_mode', true) ?: 'light';
        $profile_frame = get_user_meta($user_id, 'bb_profile_frame', true) ?: 'default';
        
        $options = get_option('bb_theme_darkmode_toggle_settings', array(
            'enable_dark_mode' => 'yes',
            'enable_profile_frames' => 'yes'
        ));
        
        ?>
        <form action="<?php echo bp_displayed_user_domain() . bp_get_settings_slug() . '/theme-settings'; ?>" method="post" class="standard-form" id="theme-settings-form">
            
            <div class="bb-theme-toggle-settings">
                <?php if (isset($options['enable_dark_mode']) && $options['enable_dark_mode'] === 'yes'): ?>
                <div class="bb-theme-mode-toggle">
                    <label><?php _e('Theme Mode', 'bb-theme-toggle'); ?></label>
                    <div class="theme-mode-options">
                        <label>
                            <input type="radio" name="bb_theme_mode" value="light" <?php checked('light', $theme_mode); ?> />
                            <?php _e('Light Mode', 'bb-theme-toggle'); ?>
                        </label>
                        <label>
                            <input type="radio" name="bb_theme_mode" value="dark" <?php checked('dark', $theme_mode); ?> />
                            <?php _e('Dark Mode', 'bb-theme-toggle'); ?>
                        </label>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (isset($options['enable_profile_frames']) && $options['enable_profile_frames'] === 'yes'): ?>
                <div class="bb-profile-frame-select">
                    <label><?php _e('Profile Frame', 'bb-theme-toggle'); ?></label>
                    <div class="profile-frame-options">
                        <label>
                            <input type="radio" name="bb_profile_frame" value="default" <?php checked('default', $profile_frame); ?> />
                            <span class="frame-preview frame-default"><?php _e('Default', 'bb-theme-toggle'); ?></span>
                        </label>
                        <label>
                            <input type="radio" name="bb_profile_frame" value="frame1" <?php checked('frame1', $profile_frame); ?> />
                            <span class="frame-preview frame-1"><?php _e('Frame 1', 'bb-theme-toggle'); ?></span>
                        </label>
                        <label>
                            <input type="radio" name="bb_profile_frame" value="frame2" <?php checked('frame2', $profile_frame); ?> />
                            <span class="frame-preview frame-2"><?php _e('Frame 2', 'bb-theme-toggle'); ?></span>
                        </label>
                        <label>
                            <input type="radio" name="bb_profile_frame" value="frame3" <?php checked('frame3', $profile_frame); ?> />
                            <span class="frame-preview frame-3"><?php _e('Frame 3', 'bb-theme-toggle'); ?></span>
                        </label>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="submit">
                <input type="submit" name="submit" value="<?php esc_attr_e('Save Changes', 'bb-theme-toggle'); ?>" class="auto" />
            </div>
            
            <?php wp_nonce_field('bp_settings_theme'); ?>
            
        </form>
        <?php
    }
    
    /**
     * Theme toggle shortcode
     */
    public function theme_darkmode_toggle_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(
            array(
                'style' => 'switch', // switch, toggle, buttons
                'label' => __('Theme Mode', 'bb-theme-toggle'),
                'class' => '',
            ),
            $atts,
            'bb_theme_darkmode_toggle'
        );
        
        // Check if dark mode is enabled
        $options = get_option('bb_theme_darkmode_toggle_settings', array(
            'enable_dark_mode' => 'yes'
        ));
        
        if (!isset($options['enable_dark_mode']) || $options['enable_dark_mode'] !== 'yes') {
            return '';
        }
        
        // Get current user's theme preference
        $user_id = get_current_user_id();
        $theme_mode = get_user_meta($user_id, 'bb_theme_mode', true) ?: 'light';
        
        // Start output buffering
        ob_start();
        
        // Generate HTML based on style
        ?>
        <div class="bb-theme-toggle-shortcode <?php echo esc_attr($atts['class']); ?> style-<?php echo esc_attr($atts['style']); ?>">
            <?php if (!empty($atts['label'])): ?>
            <span class="toggle-label"><?php echo esc_html($atts['label']); ?></span>
            <?php endif; ?>
            
            <?php if ($atts['style'] === 'switch'): ?>
            <label class="theme-mode-switch">
                <input type="checkbox" class="theme-mode-input" <?php checked('dark', $theme_mode); ?>>
                <span class="slider round"></span>
            </label>
            <?php elseif ($atts['style'] === 'toggle'): ?>
            <button class="theme-mode-toggle-btn" aria-pressed="<?php echo $theme_mode === 'dark' ? 'true' : 'false'; ?>">
                <span class="toggle-icon light">☀️</span>
                <span class="toggle-icon dark">🌙</span>
            </button>
            <?php else: // buttons ?>
            <div class="theme-mode-buttons">
                <button class="theme-btn light-btn <?php echo $theme_mode === 'light' ? 'active' : ''; ?>" data-mode="light">
                    <span class="btn-icon">☀️</span>
                    <span class="btn-text"><?php _e('Light', 'bb-theme-toggle'); ?></span>
                </button>
                <button class="theme-btn dark-btn <?php echo $theme_mode === 'dark' ? 'active' : ''; ?>" data-mode="dark">
                    <span class="btn-icon">🌙</span>
                    <span class="btn-text"><?php _e('Dark', 'bb-theme-toggle'); ?></span>
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                // Switch style
                $('.bb-theme-toggle-shortcode .theme-mode-switch input').on('change', function() {
                    const newMode = $(this).is(':checked') ? 'dark' : 'light';
                    updateThemeMode(newMode);
                });
                
                // Toggle button style
                $('.bb-theme-toggle-shortcode .theme-mode-toggle-btn').on('click', function() {
                    const currentMode = $(this).attr('aria-pressed') === 'true' ? 'dark' : 'light';
                    const newMode = currentMode === 'dark' ? 'light' : 'dark';
                    
                    $(this).attr('aria-pressed', newMode === 'dark' ? 'true' : 'false');
                    updateThemeMode(newMode);
                });
                
                // Button style
                $('.bb-theme-toggle-shortcode .theme-btn').on('click', function() {
                    const newMode = $(this).data('mode');
                    
                    $('.theme-btn').removeClass('active');
                    $(this).addClass('active');
                    
                    updateThemeMode(newMode);
                });
                
                // Common function to update theme mode
                function updateThemeMode(mode) {
                    // If BBThemeToggle object exists, use its methods
                    if (typeof BBThemeToggle !== 'undefined') {
                        bbThemeToggle.themeMode = mode;
                        BBThemeToggle.applyThemeMode();
                        BBThemeToggle.updateUserPreference('theme_mode', mode);
                    } else {
                        // Direct AJAX call if BBThemeToggle not loaded
                        $.ajax({
                            url: bbThemeToggle.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'bb_update_theme_preference',
                                user_id: <?php echo $user_id; ?>,
                                pref_type: 'theme_mode',
                                pref_value: mode,
                                nonce: bbThemeToggle.nonce
                            },
                            success: function(response) {
                                console.log('Theme updated:', response);
                                
                                // Apply visual changes immediately
                                $('body').removeClass('bb-theme-mode-light bb-theme-mode-dark');
                                $('body').addClass('bb-theme-mode-' + mode);
                                
                                // Apply CSS variables
                                if (mode === 'dark') {
                                    applyDarkModeStyles();
                                } else {
                                    applyLightModeStyles();
                                }
                                
                                // Store in localStorage
                                localStorage.setItem('bb_theme_mode', mode);
                            }
                        });
                    }
                }
                
                // Simplified dark mode styles
                function applyDarkModeStyles() {
                    document.documentElement.style.setProperty('--bb-background-color', '#121212');
                    document.documentElement.style.setProperty('--bb-body-text-color', '#e0e0e0');
                    $('body').addClass('dark-mode');
                }
                
                // Simplified light mode styles
                function applyLightModeStyles() {
                    document.documentElement.style.setProperty('--bb-background-color', '');
                    document.documentElement.style.setProperty('--bb-body-text-color', '');
                    $('body').removeClass('dark-mode');
                }
            });
        })(jQuery);
        </script>
        <?php
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * Profile frame shortcode
     */
    public function profile_frame_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(
            array(
                'style' => 'select', // select, radio, buttons
                'label' => __('Profile Frame', 'bb-theme-toggle'),
                'class' => '',
            ),
            $atts,
            'bb_profile_frame_selector'
        );
        
        // Check if profile frames are enabled
        $options = get_option('bb_theme_darkmode_toggle_settings', array(
            'enable_profile_frames' => 'yes'
        ));
        
        if (!isset($options['enable_profile_frames']) || $options['enable_profile_frames'] !== 'yes') {
            return '';
        }
        
        // Get current user's frame preference
        $user_id = get_current_user_id();
        $profile_frame = get_user_meta($user_id, 'bb_profile_frame', true) ?: 'default';
        
        // Available frames
        $frames = array(
            'default' => __('Default', 'bb-theme-toggle'),
            'frame1' => __('Frame 1', 'bb-theme-toggle'),
            'frame2' => __('Frame 2', 'bb-theme-toggle'),
            'frame3' => __('Frame 3', 'bb-theme-toggle')
        );
        
        // Start output buffering
        ob_start();
        
        // Generate HTML based on style
        ?>
        <div class="bb-profile-frame-shortcode <?php echo esc_attr($atts['class']); ?> style-<?php echo esc_attr($atts['style']); ?>">
            <?php if (!empty($atts['label'])): ?>
            <span class="frame-label"><?php echo esc_html($atts['label']); ?></span>
            <?php endif; ?>
            
            <?php if ($atts['style'] === 'select'): ?>
            <select class="frame-select">
                <?php foreach ($frames as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $profile_frame); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <?php elseif ($atts['style'] === 'radio'): ?>
            <div class="frame-radio-group">
                <?php foreach ($frames as $value => $label): ?>
                <label class="frame-radio-label">
                    <input type="radio" name="bb_frame_radio" value="<?php echo esc_attr($value); ?>" <?php checked($value, $profile_frame); ?>>
                    <span class="frame-preview frame-<?php echo esc_attr($value); ?>">
                        <?php echo esc_html($label); ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <?php else: // buttons ?>
            <div class="frame-buttons">
                <?php foreach ($frames as $value => $label): ?>
                <button class="frame-btn frame-<?php echo esc_attr($value); ?> <?php echo $value === $profile_frame ? 'active' : ''; ?>" data-frame="<?php echo esc_attr($value); ?>">
                    <span class="frame-preview"></span>
                    <span class="frame-name"><?php echo esc_html($label); ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="frame-preview-container">
                <div class="frame-preview-avatar <?php echo esc_attr('frame-' . $profile_frame); ?>">
                    <?php 
                    // Get current user's avatar
                    $avatar = get_avatar($user_id, 100);
                    echo $avatar;
                    ?>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                // Select style
                $('.bb-profile-frame-shortcode .frame-select').on('change', function() {
                    const newFrame = $(this).val();
                    updateProfileFrame(newFrame);
                });
                
                // Radio style
                $('.bb-profile-frame-shortcode .frame-radio-group input').on('change', function() {
                    const newFrame = $(this).val();
                    updateProfileFrame(newFrame);
                });
                
                // Button style
                $('.bb-profile-frame-shortcode .frame-btn').on('click', function() {
                    const newFrame = $(this).data('frame');
                    
                    $('.frame-btn').removeClass('active');
                    $(this).addClass('active');
                    
                    updateProfileFrame(newFrame);
                });
                
                // Common function to update profile frame
                function updateProfileFrame(frame) {
                    // Update preview
                    $('.frame-preview-avatar').removeClass('frame-default frame-1 frame-2 frame-3');
                    $('.frame-preview-avatar').addClass('frame-' + frame);
                    
                    // Apply frame styles to preview
                    applyFrameStyles($('.frame-preview-avatar img'), frame);
                    
                    // If BBThemeToggle object exists, use its methods
                    if (typeof BBThemeToggle !== 'undefined') {
                        bbThemeToggle.profileFrame = frame;
                        BBThemeToggle.applyProfileFrame();
                        BBThemeToggle.updateUserPreference('profile_frame', frame);
                    } else {
                        // Direct AJAX call if BBThemeToggle not loaded
                        $.ajax({
                            url: bbThemeToggle.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'bb_update_theme_preference',
                                user_id: <?php echo $user_id; ?>,
                                pref_type: 'profile_frame',
                                pref_value: frame,
                                nonce: bbThemeToggle.nonce
                            },
                            success: function(response) {
                                console.log('Frame updated:', response);
                                
                                // Apply to all avatars on page
                                $('.bp-user .item-header img.avatar, .buddypress .user-nicename img.avatar').each(function() {
                                    // Remove existing frame classes
                                    $(this).removeClass('frame-default frame-1 frame-2 frame-3');
                                    
                                    // Add selected frame class
                                    $(this).addClass('frame-' + frame);
                                    
                                    // Apply frame styling
                                    applyFrameStyles($(this), frame);
                                });
                            }
                        });
                    }
                }
                
                // Simplified frame styling function
                function applyFrameStyles($elem, frameType) {
                    // Reset styles
                    $elem.css({
                        'border': '',
                        'padding': '',
                        'box-shadow': '',
                        'border-radius': ''
                    });
                    
                    // Apply specific frame styles
                    switch(frameType) {
                        case 'frame1':
                            $elem.css({
                                'border': '3px solid var(--bb-primary-color)',
                                'padding': '2px',
                                'border-radius': '50%'
                            });
                            break;
                            
                        case 'frame2':
                            $elem.css({
                                'border': '2px solid #fff',
                                'box-shadow': '0 0 0 3px var(--bb-primary-color)',
                                'padding': '2px',
                                'border-radius': '50%'
                            });
                            break;
                            
                        case 'frame3':
                            $elem.css({
                                'border': '3px solid gold',
                                'padding': '2px',
                                'border-radius': '50%',
                                'box-shadow': '0 0 10px rgba(255, 215, 0, 0.5)'
                            });
                            break;
                    }
                }
            });
        })(jQuery);
        </script>
        <?php
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for updating user preferences
     */
    public function ajax_update_preference() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bb-theme-toggle-nonce')) {
            wp_send_json_error('Invalid nonce');
            exit;
        }
        
        // Check user permission
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$user_id || get_current_user_id() !== $user_id) {
            wp_send_json_error('Unauthorized');
            exit;
        }
        
        // Get preference type and value
        $pref_type = isset($_POST['pref_type']) ? sanitize_text_field($_POST['pref_type']) : '';
        $pref_value = isset($_POST['pref_value']) ? sanitize_text_field($_POST['pref_value']) : '';
        
        // Validate preference type
        if (!in_array($pref_type, array('theme_mode', 'profile_frame'))) {
            wp_send_json_error('Invalid preference type');
            exit;
        }
        
        // Update user meta based on preference type (network-wide)
        if ($pref_type === 'theme_mode') {
            // Validate theme mode
            if (!in_array($pref_value, array('light', 'dark'))) {
                wp_send_json_error('Invalid theme mode');
                exit;
            }
            
            // Use network-wide meta key (no blog ID)
            update_user_meta($user_id, 'bb_theme_mode', $pref_value);
        } else if ($pref_type === 'profile_frame') {
            // Validate profile frame
            if (!in_array($pref_value, array('default', 'frame1', 'frame2', 'frame3'))) {
                wp_send_json_error('Invalid profile frame');
                exit;
            }
            
            // Use network-wide meta key (no blog ID)
            update_user_meta($user_id, 'bb_profile_frame', $pref_value);
        }
        
        wp_send_json_success(array(
            'message' => __('Preference updated successfully', 'bb-theme-toggle'),
            'pref_type' => $pref_type,
            'pref_value' => $pref_value
        ));
    }
}