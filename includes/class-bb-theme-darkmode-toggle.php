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
     * Active tab
     */
    private $active_tab = 'general';
    
    /**
     * Plugin integrations
     */
    private $plugin_integrations = array(
        'buddyboss' => 'BuddyBoss',
        'better_messages' => 'Better Messages',
        'tutor_lms' => 'Tutor LMS',
        'events_calendar' => 'The Events Calendar',
        'dokan' => 'Dokan'
    );

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
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Add settings page
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // For multisite, add network admin menu
        if (is_multisite()) {
            add_action('network_admin_menu', array($this, 'add_network_admin_menu'));
        }
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add theme settings to user profile
        add_action('bp_members_screen_display_settings', array($this, 'add_theme_settings_to_profile'));
        add_action('bp_core_general_settings_after_save', array($this, 'save_theme_settings'));
        
        // Add body class based on user preference
        add_filter('body_class', array($this, 'add_body_class'));
        
        // Add menu item to BuddyBoss profile menu
        add_action('bp_setup_nav', array($this, 'setup_nav'));
        
        // Register shortcode for theme toggle
        add_shortcode('bb_theme_darkmode_toggle', array($this, 'theme_darkmode_toggle_shortcode'));
        
        // Setup AJAX handler
        add_action('wp_ajax_bb_update_theme_preference', array($this, 'ajax_update_preference'));
        
        // Apply dark mode CSS variables
        add_action('wp_head', array($this, 'output_dark_mode_css'), 999);
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
            $options = $this->get_plugin_options();
            
            // Use network-wide user meta keys (no blog ID in the key)
            $theme_mode = get_user_meta($user_id, 'bb_theme_mode', true) ?: 'light';
            $is_logged_in = is_user_logged_in();
            $floating_toggle_visibility = isset($options['floating_toggle_visibility']) ? 
                $options['floating_toggle_visibility'] : 'all_users';
            
            wp_localize_script('bb-theme-toggle-js', 'bbThemeToggle', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bb-theme-toggle-nonce'),
                'userId' => $user_id,
                'themeMode' => $theme_mode,
                'networkWide' => true, // Flag indicating network-wide preferences
                'isLoggedIn' => $is_logged_in,
                'floatingToggleVisibility' => $floating_toggle_visibility
            ));
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on our settings page
        if ('settings_page_bb-theme-darkmode-toggle' !== $hook && 'settings_page_bb-theme-darkmode-toggle-network' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'bb-theme-toggle-admin-css',
            BB_THEME_DARKMODE_TOGGLE_PLUGIN_URL . 'assets/css/bb-theme-darkmode-toggle-admin.css',
            array(),
            BB_THEME_DARKMODE_TOGGLE_VERSION
        );
        
        wp_enqueue_script(
            'bb-theme-toggle-admin-js',
            BB_THEME_DARKMODE_TOGGLE_PLUGIN_URL . 'assets/js/bb-theme-darkmode-toggle-admin.js',
            array('jquery'),
            BB_THEME_DARKMODE_TOGGLE_VERSION,
            true
        );
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
     * Add network admin menu item
     */
    public function add_network_admin_menu() {
        add_submenu_page(
            'settings.php',
            __('BuddyBoss Theme Darkmode Toggle', 'bb-theme-darkmode-toggle'),
            __('BB Theme Darkmode Toggle', 'bb-theme-darkmode-toggle'),
            'manage_network_options',
            'bb-theme-darkmode-toggle-network',
            array($this, 'display_admin_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // For network admin, we'll handle saving manually
        if (!is_network_admin()) {
            register_setting(
                'bb_theme_darkmode_toggle_settings',
                'bb_theme_darkmode_toggle_settings',
                array($this, 'sanitize_settings')
            );
        }

        // General Settings Section
        add_settings_section(
            'bb_theme_darkmode_toggle_general_section',
            __('General Settings', 'bb-theme-toggle'),
            array($this, 'general_section_callback'),
            'bb_theme_darkmode_toggle_general'
        );
        
        add_settings_field(
            'enable_dark_mode',
            __('Enable Dark Mode', 'bb-theme-toggle'),
            array($this, 'enable_dark_mode_callback'),
            'bb_theme_darkmode_toggle_general',
            'bb_theme_darkmode_toggle_general_section'
        );

        add_settings_field(
            'floating_toggle_visibility',
            __('Floating Toggle Visibility', 'bb-theme-toggle'),
            array($this, 'floating_toggle_visibility_callback'),
            'bb_theme_darkmode_toggle_general',
            'bb_theme_darkmode_toggle_general_section'
        );
        
        // BuddyBoss Integration Section
        add_settings_section(
            'bb_theme_darkmode_toggle_buddyboss_section',
            __('BuddyBoss Integration', 'bb-theme-toggle'),
            array($this, 'buddyboss_section_callback'),
            'bb_theme_darkmode_toggle_buddyboss'
        );
        
        add_settings_field(
            'enable_buddyboss_integration',
            __('Enable BuddyBoss Integration', 'bb-theme-toggle'),
            array($this, 'enable_plugin_integration_callback'),
            'bb_theme_darkmode_toggle_buddyboss',
            'bb_theme_darkmode_toggle_buddyboss_section',
            array('plugin' => 'buddyboss')
        );
        
        add_settings_field(
            'buddyboss_dark_mode_colors',
            __('BuddyBoss Dark Mode Colors', 'bb-theme-toggle'),
            array($this, 'buddyboss_colors_callback'),
            'bb_theme_darkmode_toggle_buddyboss',
            'bb_theme_darkmode_toggle_buddyboss_section'
        );
        
        // Better Messages Integration Section
        add_settings_section(
            'bb_theme_darkmode_toggle_better_messages_section',
            __('Better Messages Integration', 'bb-theme-toggle'),
            array($this, 'better_messages_section_callback'),
            'bb_theme_darkmode_toggle_better_messages'
        );
        
        add_settings_field(
            'enable_better_messages_integration',
            __('Enable Better Messages Integration', 'bb-theme-toggle'),
            array($this, 'enable_plugin_integration_callback'),
            'bb_theme_darkmode_toggle_better_messages',
            'bb_theme_darkmode_toggle_better_messages_section',
            array('plugin' => 'better_messages')
        );
        
        // Tutor LMS Integration Section
        add_settings_section(
            'bb_theme_darkmode_toggle_tutor_lms_section',
            __('Tutor LMS Integration', 'bb-theme-toggle'),
            array($this, 'tutor_lms_section_callback'),
            'bb_theme_darkmode_toggle_tutor_lms'
        );
        
        add_settings_field(
            'enable_tutor_lms_integration',
            __('Enable Tutor LMS Integration', 'bb-theme-toggle'),
            array($this, 'enable_plugin_integration_callback'),
            'bb_theme_darkmode_toggle_tutor_lms',
            'bb_theme_darkmode_toggle_tutor_lms_section',
            array('plugin' => 'tutor_lms')
        );
        
        // The Events Calendar Integration Section
        add_settings_section(
            'bb_theme_darkmode_toggle_events_calendar_section',
            __('The Events Calendar Integration', 'bb-theme-toggle'),
            array($this, 'events_calendar_section_callback'),
            'bb_theme_darkmode_toggle_events_calendar'
        );
        
        add_settings_field(
            'enable_events_calendar_integration',
            __('Enable The Events Calendar Integration', 'bb-theme-toggle'),
            array($this, 'enable_plugin_integration_callback'),
            'bb_theme_darkmode_toggle_events_calendar',
            'bb_theme_darkmode_toggle_events_calendar_section',
            array('plugin' => 'events_calendar')
        );
        
        // Dokan Integration Section
        add_settings_section(
            'bb_theme_darkmode_toggle_dokan_section',
            __('Dokan Integration', 'bb-theme-toggle'),
            array($this, 'dokan_section_callback'),
            'bb_theme_darkmode_toggle_dokan'
        );
        
        add_settings_field(
            'enable_dokan_integration',
            __('Enable Dokan Integration', 'bb-theme-toggle'),
            array($this, 'enable_plugin_integration_callback'),
            'bb_theme_darkmode_toggle_dokan',
            'bb_theme_darkmode_toggle_dokan_section',
            array('plugin' => 'dokan')
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $output = array();
        
        // General Settings
        $output['enable_dark_mode'] = isset($input['enable_dark_mode']) && $input['enable_dark_mode'] === 'yes' ? 'yes' : 'no';
        // Floating toggle visibility
        $output['floating_toggle_visibility'] = isset($input['floating_toggle_visibility']) ? 
            sanitize_text_field($input['floating_toggle_visibility']) : 'all_users';

        // Plugin Integrations
        $output['plugin_integrations'] = array();
        
        foreach ($this->plugin_integrations as $plugin_key => $plugin_name) {
            $output['plugin_integrations'][$plugin_key] = isset($input['plugin_integrations'][$plugin_key]) && $input['plugin_integrations'][$plugin_key] === 'yes' ? 'yes' : 'no';
        }
        
        // BuddyBoss Colors
        if (isset($input['buddyboss_colors'])) {
            $output['buddyboss_colors'] = array();
            foreach ($input['buddyboss_colors'] as $key => $value) {
                $output['buddyboss_colors'][$key] = sanitize_hex_color($value);
            }
        }
        
        return $output;
    }

    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure general settings for the BuddyBoss Theme Darkmode Toggle plugin.', 'bb-theme-darkmode-toggle') . '</p>';
    }
    
    /**
     * BuddyBoss section callback
     */
    public function buddyboss_section_callback() {
        echo '<p>' . __('Configure BuddyBoss-specific dark mode settings.', 'bb-theme-darkmode-toggle') . '</p>';
    }
    
    /**
     * Better Messages section callback
     */
    public function better_messages_section_callback() {
        echo '<p>' . __('Configure Better Messages dark mode integration.', 'bb-theme-darkmode-toggle') . '</p>';
    }
    
    /**
     * Tutor LMS section callback
     */
    public function tutor_lms_section_callback() {
        echo '<p>' . __('Configure Tutor LMS dark mode integration. This will apply to the primary site.', 'bb-theme-darkmode-toggle') . '</p>';
    }
    
    /**
     * The Events Calendar section callback
     */
    public function events_calendar_section_callback() {
        echo '<p>' . __('Configure The Events Calendar dark mode integration. This will apply to the events subsite.', 'bb-theme-darkmode-toggle') . '</p>';
    }
    
    /**
     * Dokan section callback
     */
    public function dokan_section_callback() {
        echo '<p>' . __('Configure Dokan dark mode integration. This will apply to the store subsite.', 'bb-theme-darkmode-toggle') . '</p>';
    }

    /**
     * Enable dark mode callback
     */
    public function enable_dark_mode_callback() {
        $options = $this->get_plugin_options();
        
        $checked = isset($options['enable_dark_mode']) && $options['enable_dark_mode'] === 'yes';
        
        echo '<input type="checkbox" id="enable_dark_mode" name="bb_theme_darkmode_toggle_settings[enable_dark_mode]" value="yes" ' . checked(true, $checked, false) . ' />';
        echo '<label for="enable_dark_mode">' . __('Allow users to switch between light and dark mode', 'bb-theme-toggle') . '</label>';
    }
    
    /**
     * Enable plugin integration callback
     */
    public function enable_plugin_integration_callback($args) {
        $plugin = $args['plugin'];
        $options = $this->get_plugin_options();
        
        $checked = isset($options['plugin_integrations'][$plugin]) && $options['plugin_integrations'][$plugin] === 'yes';
        
        echo '<input type="checkbox" id="enable_' . esc_attr($plugin) . '_integration" name="bb_theme_darkmode_toggle_settings[plugin_integrations][' . esc_attr($plugin) . ']" value="yes" ' . checked(true, $checked, false) . ' />';
        echo '<label for="enable_' . esc_attr($plugin) . '_integration">' . sprintf(__('Enable dark mode integration for %s', 'bb-theme-toggle'), $this->plugin_integrations[$plugin]) . '</label>';
    }
    
    /**
     * BuddyBoss colors callback
     */
    public function buddyboss_colors_callback() {
        $options = $this->get_plugin_options();
        $default_colors = array(
            'body_background_color' => '#222',
            'content_background_color' => '#333',
            'headings_color' => '#647385',
            'primary_color' => '#647385',
            'body_text_color' => '#f2f2f2',
            'content_border_color' => '#555759',
            'header_background' => '#1c252b',
            'footer_background' => '#1c252b'
        );
        
        $buddyboss_colors = isset($options['buddyboss_colors']) ? $options['buddyboss_colors'] : $default_colors;
        
        ?>
        <div class="bb-color-settings">
            <div class="bb-color-field">
                <label for="buddyboss_body_background_color"><?php _e('Body Background Color', 'bb-theme-toggle'); ?></label>
                <input type="color" id="buddyboss_body_background_color" name="bb_theme_darkmode_toggle_settings[buddyboss_colors][body_background_color]" value="<?php echo esc_attr($buddyboss_colors['body_background_color']); ?>" />
                <span class="bb-color-value"><?php echo esc_html($buddyboss_colors['body_background_color']); ?></span>
            </div>
            <div class="bb-color-field">
                <label for="buddyboss_content_background_color"><?php _e('Content Background Color', 'bb-theme-toggle'); ?></label>
                <input type="color" id="buddyboss_content_background_color" name="bb_theme_darkmode_toggle_settings[buddyboss_colors][content_background_color]" value="<?php echo esc_attr($buddyboss_colors['content_background_color']); ?>" />
                <span class="bb-color-value"><?php echo esc_html($buddyboss_colors['content_background_color']); ?></span>
            </div>
            <div class="bb-color-field">
                <label for="buddyboss_headings_color"><?php _e('Headings Color', 'bb-theme-toggle'); ?></label>
                <input type="color" id="buddyboss_headings_color" name="bb_theme_darkmode_toggle_settings[buddyboss_colors][headings_color]" value="<?php echo esc_attr($buddyboss_colors['headings_color']); ?>" />
                <span class="bb-color-value"><?php echo esc_html($buddyboss_colors['headings_color']); ?></span>
            </div>
            <div class="bb-color-field">
                <label for="buddyboss_primary_color"><?php _e('Primary Color', 'bb-theme-toggle'); ?></label>
                <input type="color" id="buddyboss_primary_color" name="bb_theme_darkmode_toggle_settings[buddyboss_colors][primary_color]" value="<?php echo esc_attr($buddyboss_colors['primary_color']); ?>" />
                <span class="bb-color-value"><?php echo esc_html($buddyboss_colors['primary_color']); ?></span>
            </div>
            <div class="bb-color-field">
                <label for="buddyboss_body_text_color"><?php _e('Body Text Color', 'bb-theme-toggle'); ?></label>
                <input type="color" id="buddyboss_body_text_color" name="bb_theme_darkmode_toggle_settings[buddyboss_colors][body_text_color]" value="<?php echo esc_attr($buddyboss_colors['body_text_color']); ?>" />
                <span class="bb-color-value"><?php echo esc_html($buddyboss_colors['body_text_color']); ?></span>
            </div>
            <div class="bb-color-field">
                <label for="buddyboss_content_border_color"><?php _e('Content Border Color', 'bb-theme-toggle'); ?></label>
                <input type="color" id="buddyboss_content_border_color" name="bb_theme_darkmode_toggle_settings[buddyboss_colors][content_border_color]" value="<?php echo esc_attr($buddyboss_colors['content_border_color']); ?>" />
                <span class="bb-color-value"><?php echo esc_html($buddyboss_colors['content_border_color']); ?></span>
            </div>
            <div class="bb-color-field">
                <label for="buddyboss_header_background"><?php _e('Header Background', 'bb-theme-toggle'); ?></label>
                <input type="color" id="buddyboss_header_background" name="bb_theme_darkmode_toggle_settings[buddyboss_colors][header_background]" value="<?php echo esc_attr($buddyboss_colors['header_background']); ?>" />
                <span class="bb-color-value"><?php echo esc_html($buddyboss_colors['header_background']); ?></span>
            </div>
            <div class="bb-color-field">
                <label for="buddyboss_footer_background"><?php _e('Footer Background', 'bb-theme-toggle'); ?></label>
                <input type="color" id="buddyboss_footer_background" name="bb_theme_darkmode_toggle_settings[buddyboss_colors][footer_background]" value="<?php echo esc_attr($buddyboss_colors['footer_background']); ?>" />
                <span class="bb-color-value"><?php echo esc_html($buddyboss_colors['footer_background']); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Display admin page
     */
    public function display_admin_page() {
        // Handle form submission for network admin
        if (is_network_admin() && isset($_POST['bb_theme_darkmode_toggle_settings'])) {
            $this->save_network_settings();
        }
        
        if (isset($_GET['tab'])) {
            $this->active_tab = sanitize_text_field($_GET['tab']);
        } else {
            $this->active_tab = 'general';
        }
        
        // Check for settings updated message
        $updated = isset($_GET['settings-updated']) ? sanitize_text_field($_GET['settings-updated']) : '';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if ($updated === 'true' && !is_network_admin()): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Settings saved.', 'bb-theme-darkmode-toggle'); ?></p>
            </div>
            <?php endif; ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo $_GET['page']; ?>&tab=general" class="nav-tab <?php echo $this->active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('General', 'bb-theme-toggle'); ?></a>
                <a href="?page=<?php echo $_GET['page']; ?>&tab=buddyboss" class="nav-tab <?php echo $this->active_tab == 'buddyboss' ? 'nav-tab-active' : ''; ?>"><?php _e('BuddyBoss', 'bb-theme-toggle'); ?></a>
                <a href="?page=<?php echo $_GET['page']; ?>&tab=better_messages" class="nav-tab <?php echo $this->active_tab == 'better_messages' ? 'nav-tab-active' : ''; ?>"><?php _e('Better Messages', 'bb-theme-toggle'); ?></a>
                <a href="?page=<?php echo $_GET['page']; ?>&tab=tutor_lms" class="nav-tab <?php echo $this->active_tab == 'tutor_lms' ? 'nav-tab-active' : ''; ?>"><?php _e('Tutor LMS', 'bb-theme-toggle'); ?></a>
                <a href="?page=<?php echo $_GET['page']; ?>&tab=events_calendar" class="nav-tab <?php echo $this->active_tab == 'events_calendar' ? 'nav-tab-active' : ''; ?>"><?php _e('The Events Calendar', 'bb-theme-toggle'); ?></a>
                <a href="?page=<?php echo $_GET['page']; ?>&tab=dokan" class="nav-tab <?php echo $this->active_tab == 'dokan' ? 'nav-tab-active' : ''; ?>"><?php _e('Dokan', 'bb-theme-toggle'); ?></a>
            </h2>
            
            <?php if (is_network_admin()): ?>
            <!-- Network admin form submission -->
            <form method="post" action="">
            <?php else: ?>
            <!-- Regular admin form submission -->
            <form method="post" action="options.php">
            <?php endif; ?>
                
                <?php
                settings_fields('bb_theme_darkmode_toggle_settings');
                
                if ($this->active_tab == 'general') {
                    do_settings_sections('bb_theme_darkmode_toggle_general');
                } elseif ($this->active_tab == 'buddyboss') {
                    do_settings_sections('bb_theme_darkmode_toggle_buddyboss');
                } elseif ($this->active_tab == 'better_messages') {
                    do_settings_sections('bb_theme_darkmode_toggle_better_messages');
                } elseif ($this->active_tab == 'tutor_lms') {
                    do_settings_sections('bb_theme_darkmode_toggle_tutor_lms');
                } elseif ($this->active_tab == 'events_calendar') {
                    do_settings_sections('bb_theme_darkmode_toggle_events_calendar');
                } elseif ($this->active_tab == 'dokan') {
                    do_settings_sections('bb_theme_darkmode_toggle_dokan');
                }
                
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get plugin options
     */
    private function get_plugin_options() {
        // If we're in a network, prioritize network options
        if (is_multisite()) {
            $network_options = get_site_option('bb_theme_darkmode_toggle_settings');
            if (!empty($network_options)) {
                return $network_options;
            }
        }
        
        // Fall back to regular site option
        return get_option('bb_theme_darkmode_toggle_settings', array(
            'enable_dark_mode' => 'yes',
            'plugin_integrations' => array(
                'buddyboss' => 'yes',
                'better_messages' => 'no',
                'tutor_lms' => 'no',
                'events_calendar' => 'no',
                'dokan' => 'no'
            )
        ));
    }


    /**
     * Save network settings
     */
    public function save_network_settings() {
        // Check nonce
        check_admin_referer('bb_theme_darkmode_toggle_settings-options');
        
        // Get the submitted settings
        $input = $_POST['bb_theme_darkmode_toggle_settings'];
        
        // Sanitize settings
        $output = $this->sanitize_settings($input);
        
        // Save settings as a network option
        update_site_option('bb_theme_darkmode_toggle_settings', $output);
        
        // Add success message
        add_action('network_admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved.', 'bb-theme-darkmode-toggle') . '</p></div>';
        });
    }

    /**
     * Add theme settings to user profile
     */
    public function add_theme_settings_to_profile() {
        $user_id = bp_displayed_user_id();
        $theme_mode = get_user_meta($user_id, 'bb_theme_mode', true) ?: 'light';
        
        $options = $this->get_plugin_options();
        
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
        </div>
        <?php
    }

    /**
     * Save theme settings
     */
    public function save_theme_settings() {
        if (!isset($_POST['bb_theme_mode'])) {
            return;
        }
        
        $user_id = bp_displayed_user_id();
        
        if (isset($_POST['bb_theme_mode'])) {
            $theme_mode = sanitize_text_field($_POST['bb_theme_mode']);
            // Use network-wide meta key (no blog ID)
            update_user_meta($user_id, 'bb_theme_mode', $theme_mode);
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
            
            $classes[] = 'bb-theme-mode-' . $theme_mode;
        }
        
        return $classes;
    }

    /**
     * Floating toggle visibility callback
     */
    public function floating_toggle_visibility_callback() {
        $options = $this->get_plugin_options();
        
        $visibility = isset($options['floating_toggle_visibility']) ? $options['floating_toggle_visibility'] : 'all_users';
        
        ?>
        <select name="bb_theme_darkmode_toggle_settings[floating_toggle_visibility]" id="floating_toggle_visibility">
            <option value="all_users" <?php selected('all_users', $visibility); ?>><?php _e('Show to all users', 'bb-theme-toggle'); ?></option>
            <option value="visitors_only" <?php selected('visitors_only', $visibility); ?>><?php _e('Show to non-logged-in users only', 'bb-theme-toggle'); ?></option>
            <option value="logged_in_only" <?php selected('logged_in_only', $visibility); ?>><?php _e('Show to logged-in users only', 'bb-theme-toggle'); ?></option>
            <option value="hidden" <?php selected('hidden', $visibility); ?>><?php _e('Hide for everyone', 'bb-theme-toggle'); ?></option>
        </select>
        <p class="description"><?php _e('Control who can see the floating theme toggle button in the bottom-right corner.', 'bb-theme-toggle'); ?></p>
        <?php
    }

    /**
     * Setup navigation
     */
    public function setup_nav() {
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
        
        $options = $this->get_plugin_options();
        
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
        $options = $this->get_plugin_options();
        
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
        if ($pref_type !== 'theme_mode') {
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
        }
        
        wp_send_json_success(array(
            'message' => __('Preference updated successfully', 'bb-theme-toggle'),
            'pref_type' => $pref_type,
            'pref_value' => $pref_value
        ));
    }
    
    /**
     * Output dark mode CSS variables
     */
    public function output_dark_mode_css() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $theme_mode = get_user_meta($user_id, 'bb_theme_mode', true) ?: 'light';
        
        if ($theme_mode !== 'dark') {
            return;
        }
        
        $options = $this->get_plugin_options();
        $default_colors = array(
            'body_background_color' => '#222',
            'content_background_color' => '#333',
            'headings_color' => '#647385',
            'primary_color' => '#647385',
            'body_text_color' => '#f2f2f2',
            'content_border_color' => '#555759',
            'header_background' => '#1c252b',
            'footer_background' => '#1c252b'
        );
        
        $buddyboss_colors = isset($options['buddyboss_colors']) ? $options['buddyboss_colors'] : $default_colors;
        
        ?>
        <style id="bb-theme-darkmode-custom-css">
            .bb-theme-mode-dark {
                --bb-body-background-color: <?php echo esc_attr($buddyboss_colors['body_background_color']); ?>;
                --bb-content-background-color: <?php echo esc_attr($buddyboss_colors['content_background_color']); ?>;
                --bb-headings-color: <?php echo esc_attr($buddyboss_colors['headings_color']); ?>;
                --bb-primary-color: <?php echo esc_attr($buddyboss_colors['primary_color']); ?>;
                --bb-body-text-color: <?php echo esc_attr($buddyboss_colors['body_text_color']); ?>;
                --bb-content-border-color: <?php echo esc_attr($buddyboss_colors['content_border_color']); ?>;
                --bb-header-background: <?php echo esc_attr($buddyboss_colors['header_background']); ?>;
                --bb-footer-background: <?php echo esc_attr($buddyboss_colors['footer_background']); ?>;
                
                /* Additional compatibility variables */
                --bb-background-color: var(--bb-body-background-color);
                --bb-alternate-background-color: var(--bb-content-background-color);
                --bb-primary-text-color: var(--bb-headings-color);
                --bb-alternate-text-color: var(--bb-body-text-color);
                --bb-content-alternate-background-color: var(--bb-content-background-color);
                --bb-content-border-hover-color: var(--bb-content-border-color);
            }
            
            <?php if (isset($options['plugin_integrations']) && isset($options['plugin_integrations']['better_messages']) && $options['plugin_integrations']['better_messages'] === 'yes'): ?>
            /* Better Messages Dark Mode */
            .bb-theme-mode-dark .bp-better-messages-list {
                background-color: var(--bb-content-background-color);
                color: var(--bb-body-text-color);
            }
            
            .bb-theme-mode-dark .bp-messages-wrap .chat-header {
                background-color: var(--bb-header-background);
                border-color: var(--bb-content-border-color);
            }
            
            .bb-theme-mode-dark .bp-messages-wrap .chat-content {
                background-color: var(--bb-content-background-color);
                color: var(--bb-body-text-color);
            }
            <?php endif; ?>
            
            <?php if (isset($options['plugin_integrations']) && isset($options['plugin_integrations']['tutor_lms']) && $options['plugin_integrations']['tutor_lms'] === 'yes'): ?>
            /* Tutor LMS Dark Mode */
            .bb-theme-mode-dark .tutor-wrap,
            .bb-theme-mode-dark .tutor-course-content,
            .bb-theme-mode-dark .tutor-single-lesson-wrap {
                background-color: var(--bb-content-background-color);
                color: var(--bb-body-text-color);
            }
            
            .bb-theme-mode-dark .tutor-course-header {
                background-color: var(--bb-header-background);
                color: var(--bb-body-text-color);
            }
            <?php endif; ?>
            
            <?php if (isset($options['plugin_integrations']) && isset($options['plugin_integrations']['events_calendar']) && $options['plugin_integrations']['events_calendar'] === 'yes'): ?>
            /* The Events Calendar Dark Mode */
            .bb-theme-mode-dark .tribe-events-view,
            .bb-theme-mode-dark .tribe-common {
                background-color: var(--bb-content-background-color);
                color: var(--bb-body-text-color);
            }
            
            .bb-theme-mode-dark .tribe-events-header {
                background-color: var(--bb-header-background);
                border-color: var(--bb-content-border-color);
            }
            <?php endif; ?>
            
            <?php if (isset($options['plugin_integrations']) && isset($options['plugin_integrations']['dokan']) && $options['plugin_integrations']['dokan'] === 'yes'): ?>
            /* Dokan Dark Mode */
            .bb-theme-mode-dark .dokan-dashboard,
            .bb-theme-mode-dark .dokan-store {
                background-color: var(--bb-content-background-color);
                color: var(--bb-body-text-color);
            }
            
            .bb-theme-mode-dark .dokan-dashboard-header,
            .bb-theme-mode-dark .dokan-store-header {
                background-color: var(--bb-header-background);
                border-color: var(--bb-content-border-color);
            }
            <?php endif; ?>
        </style>
        <?php
    }
}