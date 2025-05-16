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
            this.applyProfileFrame();
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
         * Apply profile frame based on user preference
         */
        applyProfileFrame: function() {
            const profileFrame = bbThemeToggle.profileFrame || 'default';
            
            // Set frame class on avatar
            $('.bp-user .item-header img.avatar, .buddypress .user-nicename img.avatar').each(function() {
                // Remove existing frame classes
                $(this).removeClass('frame-default frame-1 frame-2 frame-3');
                
                // Add selected frame class
                $(this).addClass('frame-' + profileFrame);
                
                // Apply frame styling
                BBThemeToggle.applyFrameStyles($(this), profileFrame);
            });
        },

        /**
         * Apply dark mode styles
         */
        applyDarkModeStyles: function() {
            // Example - you can adjust these CSS variables based on BuddyBoss theme
            document.documentElement.style.setProperty('--bb-background-color', '#121212');
            document.documentElement.style.setProperty('--bb-alternate-background-color', '#1e1e1e');
            document.documentElement.style.setProperty('--bb-body-text-color', '#e0e0e0');
            document.documentElement.style.setProperty('--bb-primary-text-color', '#ffffff');
            document.documentElement.style.setProperty('--bb-alternate-text-color', '#aaaaaa');
            document.documentElement.style.setProperty('--bb-content-background-color', '#242424');
            document.documentElement.style.setProperty('--bb-content-alternate-background-color', '#2a2a2a');
            document.documentElement.style.setProperty('--bb-content-border-color', '#3a3a3a');
            document.documentElement.style.setProperty('--bb-content-border-hover-color', '#505050');
            
            // Add dark mode class to body
            $('body').addClass('dark-mode');
        },

        /**
         * Apply light mode styles
         */
        applyLightModeStyles: function() {
            // Reset to default BuddyBoss variables or your light theme
            document.documentElement.style.setProperty('--bb-background-color', '');
            document.documentElement.style.setProperty('--bb-alternate-background-color', '');
            document.documentElement.style.setProperty('--bb-body-text-color', '');
            document.documentElement.style.setProperty('--bb-primary-text-color', '');
            document.documentElement.style.setProperty('--bb-alternate-text-color', '');
            document.documentElement.style.setProperty('--bb-content-background-color', '');
            document.documentElement.style.setProperty('--bb-content-alternate-background-color', '');
            document.documentElement.style.setProperty('--bb-content-border-color', '');
            document.documentElement.style.setProperty('--bb-content-border-hover-color', '');
            
            // Remove dark mode class from body
            $('body').removeClass('dark-mode');
        },

        /**
         * Apply frame styles
         */
        applyFrameStyles: function($elem, frameType) {
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
                    
                // default - no special styles or reset to default
                default:
                    break;
            }
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
            
            // Profile frame selection in settings page
            $('input[name="bb_profile_frame"]').on('change', function() {
                const newFrame = $(this).val();
                
                // Update user preference via AJAX
                BBThemeToggle.updateUserPreference('profile_frame', newFrame);
                
                // Apply changes immediately
                bbThemeToggle.profileFrame = newFrame;
                BBThemeToggle.applyProfileFrame();
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