/**
 * BuddyBoss Theme Darkmode Toggle JavaScript
 */
(function($) {
    'use strict';

    // Main object
    const BBThemeToggle = {
        /**
         * Initialize
         */
        init: function() {
            this.applyThemeMode();
            this.setupEventListeners();
        },

        /**
         * Apply theme mode based on user preference
         */
        applyThemeMode: function() {
            const themeMode = bbThemeToggle.themeMode || 'light';
            
            // Remove existing classes
            $('body').removeClass('bb-theme-mode-light bb-theme-mode-dark');
            
            // Add appropriate class
            $('body').addClass('bb-theme-mode-' + themeMode);
            
            // Apply CSS variables
            if (themeMode === 'dark') {
                this.applyDarkModeStyles();
            } else {
                this.applyLightModeStyles();
            }
            
            // Store preference in localStorage for instant application on page load
            localStorage.setItem('bb_theme_mode', themeMode);
        },

        /**
         * Apply dark mode styles
         */
        applyDarkModeStyles: function() {
            // Default variables - will be overridden by CSS custom properties
            $('body').addClass('dark-mode');
        },

        /**
         * Apply light mode styles
         */
        applyLightModeStyles: function() {
            // Reset to default BuddyBoss variables or your light theme
            $('body').removeClass('dark-mode');
        },

        /**
         * Setup event listeners
         */
        setupEventListeners: function() {
            // Theme mode toggle in settings page
            $('input[name="bb_theme_mode"]').on('change', function() {
                const newMode = $(this).val();
                
                // Update user preference via AJAX
                BBThemeToggle.updateUserPreference('theme_mode', newMode);
                
                // Apply changes immediately
                bbThemeToggle.themeMode = newMode;
                BBThemeToggle.applyThemeMode();
            });
            
            // Initialize floating toggle button if enabled
            this.initFloatingToggle();
        },

        /**
         * Initialize floating toggle
         */
        initFloatingToggle: function() {
            // Only add if not already present
            if ($('.bb-floating-theme-toggle').length === 0) {
                const currentMode = bbThemeToggle.themeMode || 'light';
                const toggleIcon = currentMode === 'light' ? '🌙' : '☀️';
                
                // Create toggle button
                const $toggle = $('<button>', {
                    'class': 'bb-floating-theme-toggle',
                    'aria-label': 'Toggle Theme Mode',
                    'html': toggleIcon
                }).appendTo('body');
                
                // Add click event
                $toggle.on('click', function() {
                    const newMode = bbThemeToggle.themeMode === 'dark' ? 'light' : 'dark';
                    
                    // Update user preference via AJAX
                    BBThemeToggle.updateUserPreference('theme_mode', newMode);
                    
                    // Apply changes immediately
                    bbThemeToggle.themeMode = newMode;
                    BBThemeToggle.applyThemeMode();
                    
                    // Update toggle icon
                    $(this).html(newMode === 'light' ? '🌙' : '☀️');
                });
            }
        },

        /**
         * Update user preference via AJAX
         */
        updateUserPreference: function(prefType, value) {
            // Only make AJAX call for logged in users
            if (bbThemeToggle.userId) {
                $.ajax({
                    url: bbThemeToggle.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'bb_update_theme_preference',
                        user_id: bbThemeToggle.userId,
                        pref_type: prefType,
                        pref_value: value,
                        network_wide: true, // Always use network-wide preferences
                        nonce: bbThemeToggle.nonce
                    },
                    success: function(response) {
                        console.log('Preference updated:', response);
                    }
                });
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BBThemeToggle.init();
    });

})(jQuery);