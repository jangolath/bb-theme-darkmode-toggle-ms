/**
 * BuddyBoss Theme Darkmode Toggle Admin JavaScript
 */
(function($) {
    'use strict';

    // Update color value display when color picker changes
    $(document).ready(function() {
        // Update color value text when color picker changes
        $('input[type="color"]').on('input change', function() {
            const $this = $(this);
            const $colorValue = $this.siblings('.bb-color-value');
            
            if ($colorValue.length) {
                $colorValue.text($this.val());
            }
        });
        
        // Ensure checkboxes are properly updated
        $('#enable_dark_mode').on('change', function() {
            console.log('Dark mode checkbox changed:', $(this).prop('checked'));
        });
        
        // Plugin integration checkboxes
        $('input[name^="bb_theme_darkmode_toggle_settings[plugin_integrations]"]').on('change', function() {
            const pluginName = $(this).attr('id').replace('enable_', '').replace('_integration', '');
            const isEnabled = $(this).prop('checked');
            
            console.log('Plugin ' + pluginName + ' integration changed:', isEnabled);
        });
    });

})(jQuery);