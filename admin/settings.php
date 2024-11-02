<?php
/**
 * Admin Settings Handler
 * 
 * Manages the WordPress Spaces settings page, including field registration,
 * rendering, and validation. Handles configuration for DigitalOcean Spaces
 * integration including credentials, region selection, and sync controls.
 * 
 * @package WordPress_Spaces
 * @subpackage Admin
 * @since 0.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initializes all plugin settings and registers them with WordPress.
 * 
 * Creates the settings section and registers all setting fields including:
 * - Access Key
 * - Secret Key
 * - Space Name
 * - Region
 * - CDN URL
 * - Subfolder settings
 * 
 * @since 0.6
 * @return void
 */
add_action('admin_init', 'wp_spaces_settings_init');
function wp_spaces_settings_init() {
    register_setting('wpSpaces', 'wp_spaces_settings', 'wp_spaces_sanitize_settings');

    add_settings_section(
        'wp_spaces_section',
        __('DigitalOcean Spaces Configuration', 'wp-spaces'),
        'wp_spaces_section_callback',
        'wpSpaces'
    );

    // Access Key
    add_settings_field(
        'wp_spaces_access_key',
        __('Access Key', 'wp-spaces'),
        'wp_spaces_access_key_render',
        'wpSpaces',
        'wp_spaces_section'
    );

    // Secret Key
    add_settings_field(
        'wp_spaces_secret_key',
        __('Secret Key', 'wp-spaces'),
        'wp_spaces_secret_key_render',
        'wpSpaces',
        'wp_spaces_section'
    );

    // Space Name
    add_settings_field(
        'wp_spaces_space_name',
        __('Space Name', 'wp-spaces'),
        'wp_spaces_space_name_render',
        'wpSpaces',
        'wp_spaces_section'
    );

    // Region
    add_settings_field(
        'wp_spaces_region',
        __('Region', 'wp-spaces'),
        'wp_spaces_region_render',
        'wpSpaces',
        'wp_spaces_section'
    );

    // CDN URL
    add_settings_field(
        'wp_spaces_cdn_url',
        __('CDN URL', 'wp-spaces'),
        'wp_spaces_cdn_url_render',
        'wpSpaces',
        'wp_spaces_section'
    );

    // Enable Subfolder Mode
    add_settings_field(
        'wp_spaces_use_subfolder',
        __('Use Subfolder Mode', 'wp-spaces'),
        'wp_spaces_use_subfolder_render',
        'wpSpaces',
        'wp_spaces_section'
    );

    // Subfolder Name (only shown when subfolder mode is enabled)
    add_settings_field(
        'wp_spaces_subfolder_name',
        __('Subfolder Name', 'wp-spaces'),
        'wp_spaces_subfolder_name_render',
        'wpSpaces',
        'wp_spaces_section'
    );
}

/**
 * Renders the settings section description.
 * 
 * Provides user guidance for configuring DigitalOcean Spaces credentials
 * and explains the sync functionality.
 * 
 * @since 0.6
 * @return void
 */
function wp_spaces_section_callback() {
    echo '<p>' . __('Enter your DigitalOcean Spaces credentials and configuration settings below.', 'wp-spaces') . '</p>';
    echo '<p>' . __('Use the "Enable Sync" button to synchronize your media files with Spaces.', 'wp-spaces') . '</p>';
}

// Render Functions for Settings Fields

/**
 * Renders the Access Key input field.
 * 
 * Handles both direct input and wp-config.php defined values,
 * masking the key when defined in configuration.
 * 
 * @since 0.6
 * @return void
 */
function wp_spaces_access_key_render() {
    $options = get_option('wp_spaces_settings');
    $access_key = defined('WP_SPACES_ACCESS_KEY') ? '**********' : (isset($options['access_key']) ? esc_attr($options['access_key']) : '');
    ?>
    <input type="text" name="wp_spaces_settings[access_key]" value="<?php echo $access_key; ?>" style="width: 50%;" <?php echo defined('WP_SPACES_ACCESS_KEY') ? 'disabled' : ''; ?>>
    <?php
    if (defined('WP_SPACES_ACCESS_KEY')) {
        echo '<p class="description">' . __('Access Key is defined in wp-config.php', 'wp-spaces') . '</p>';
    }
}

/**
 * Renders the Secret Key input field.
 * 
 * Handles both direct input and wp-config.php defined values,
 * masking the key when defined in configuration.
 * 
 * @since 0.6
 * @return void
 */
function wp_spaces_secret_key_render() {
    $options = get_option('wp_spaces_settings');
    $secret_key = defined('WP_SPACES_SECRET_KEY') ? '**********' : (isset($options['secret_key']) ? esc_attr($options['secret_key']) : '');
    ?>
    <input type="password" name="wp_spaces_settings[secret_key]" value="<?php echo $secret_key; ?>" style="width: 50%;" <?php echo defined('WP_SPACES_SECRET_KEY') ? 'disabled' : ''; ?>>
    <?php
    if (defined('WP_SPACES_SECRET_KEY')) {
        echo '<p class="description">' . __('Secret Key is defined in wp-config.php', 'wp-spaces') . '</p>';
    }
}

/**
 * Renders the Space Name input field.
 * 
 * Allows users to specify their DigitalOcean Space name
 * where media files will be stored.
 * 
 * @since 0.6
 * @return void
 */
function wp_spaces_space_name_render() {
    $options = get_option('wp_spaces_settings');
    ?>
    <input type="text" name="wp_spaces_settings[space_name]" value="<?php echo isset($options['space_name']) ? esc_attr($options['space_name']) : ''; ?>" style="width: 50%;">
    <?php
}

/**
 * Renders the Region selection dropdown.
 * 
 * Provides a list of available DigitalOcean datacenter regions
 * where Spaces can be created.
 * 
 * @since 0.6
 * @return void
 */
function wp_spaces_region_render() {
    $options = get_option('wp_spaces_settings');
    ?>
    <select name="wp_spaces_settings[region]" style="width: 52%;">
        <?php
        $regions = array(
            'nyc3'  => 'New York 3 (nyc3)',
            'ams3'  => 'Amsterdam 3 (ams3)',
            'sgp1'  => 'Singapore 1 (sgp1)',
            'sfo2'  => 'San Francisco 2 (sfo2)',
            'fra1'  => 'Frankfurt 1 (fra1)',
            'tor1'  => 'Toronto 1 (tor1)',
            'blr1'  => 'Bangalore 1 (blr1)',
            'lon1'  => 'London 1 (lon1)',
        );
        foreach ($regions as $key => $label) {
            $selected = (isset($options['region']) && $options['region'] === $key) ? 'selected' : '';
            echo "<option value='" . esc_attr($key) . "' $selected>" . esc_html($label) . "</option>";
        }
        ?>
    </select>
    <?php
}

/**
 * Renders the CDN URL input field.
 * 
 * Optional field for users who have configured a CDN
 * for their Space. Accepts full URL including protocol.
 * 
 * @since 0.6
 * @return void
 */
function wp_spaces_cdn_url_render() {
    $options = get_option('wp_spaces_settings');
    ?>
    <input type="text" name="wp_spaces_settings[cdn_url]" value="<?php echo isset($options['cdn_url']) ? esc_attr($options['cdn_url']) : ''; ?>" style="width: 50%;" placeholder="https://your-pullzone.b-cdn.net/">
    <?php
}

/**
 * Sanitizes and validates all settings before saving.
 * 
 * Performs validation on:
 * - Credentials (when not in wp-config.php)
 * - Region selection against allowed values
 * - URL formats for CDN
 * - Subfolder configuration and permissions
 * 
 * Includes error handling for invalid inputs and
 * permission checks for subfolder access.
 * 
 * @since 0.6
 * @param array $input Raw input from the settings form
 * @return array Sanitized settings array
 */
function wp_spaces_sanitize_settings($input) {
    $output = array();
    
    if (isset($input['access_key']) && !defined('WP_SPACES_ACCESS_KEY')) {
        $output['access_key'] = sanitize_text_field($input['access_key']);
    }

    if (isset($input['secret_key']) && !defined('WP_SPACES_SECRET_KEY')) {
        $output['secret_key'] = sanitize_text_field($input['secret_key']);
    }

    if (isset($input['space_name'])) {
        $output['space_name'] = sanitize_text_field($input['space_name']);
    }

    if (isset($input['region'])) {
        $allowed_regions = array('nyc3', 'ams3', 'sgp1', 'sfo2', 'fra1', 'tor1', 'blr1', 'lon1');
        if (in_array($input['region'], $allowed_regions)) {
            $output['region'] = $input['region'];
        }
    }

    if (isset($input['cdn_url'])) {
        $output['cdn_url'] = esc_url_raw($input['cdn_url']);
    }

    // Sanitize subfolder settings
    $output['use_subfolder'] = isset($input['use_subfolder']);
    
    if ($output['use_subfolder']) {
        // Sanitize and validate subfolder name
        $subfolder = sanitize_text_field($input['subfolder_name']);
        
        // Only allow valid domain names or simple paths
        if (preg_match('/^[a-z0-9-_.]+$/i', $subfolder)) {
            $output['subfolder_name'] = $subfolder;
        } else {
            add_settings_error(
                'wp_spaces_settings',
                'invalid_subfolder',
                __('Subfolder name contains invalid characters', 'wp-spaces')
            );
            $output['subfolder_name'] = get_option('wp_spaces_settings')['subfolder_name'];
        }
        
        // Validate access to the subfolder
        $s3 = wp_spaces_get_s3_client();
        if ($s3 && !wp_spaces_validate_subfolder_access($s3, $output['space_name'], $output['subfolder_name'])) {
            add_settings_error(
                'wp_spaces_settings',
                'subfolder_access',
                __('No permission to access specified subfolder', 'wp-spaces')
            );
            return get_option('wp_spaces_settings');
        }
    }

    return $output;
}

/**
 * Renders the main settings page.
 * 
 * Displays:
 * - Configuration fields for DigitalOcean Spaces
 * - Sync controls and progress indicators
 * - Status messages and error notifications
 * - Progress bar for active synchronization operations
 * - JavaScript-powered live progress updates
 * 
 * @since 0.6
 * @return void
 */
function wp_spaces_options_page() {
    $options = get_option('wp_spaces_settings');
    ?>
    <div class="wrap">
        <h1><?php _e('WordPress Spaces Settings', 'wp-spaces'); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('wpSpaces');
            do_settings_sections('wpSpaces');
            submit_button();
            ?>
        </form>

        <h2><?php _e('Synchronize Media Files with Spaces', 'wp-spaces'); ?></h2>

        <?php
        // Check if the option exists and is true
        $sync_enabled = get_option('wp_spaces_sync_enabled', false);
        $sync_in_progress = get_transient('wp_spaces_sync_in_progress');
        $reverse_sync_in_progress = get_transient('wp_spaces_reverse_sync_in_progress');

        // Progress bar styles
        ?>
        <style>
        .wp-spaces-progress {
            width: 100%;
            max-width: 600px;
            height: 20px;
            background-color: #f0f0f1;
            border-radius: 3px;
            margin: 10px 0;
        }
        .wp-spaces-progress-bar {
            height: 100%;
            background-color: #2271b1;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        </style>

        <?php
        if ($sync_in_progress || $reverse_sync_in_progress) {
            $total = get_option('wp_spaces_sync_total', 0);
            $progress = get_option('wp_spaces_sync_progress', 0);
            $percentage = $total > 0 ? round(($progress / $total) * 100) : 0;
            ?>
            <div class="wp-spaces-sync-progress">
                <p><?php printf(__('Sync Progress: %d of %d files processed (%d%%)', 'wp-spaces'), $progress, $total, $percentage); ?></p>
                <div class="wp-spaces-progress">
                    <div class="wp-spaces-progress-bar" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                </div>
            </div>
            <form method="post" action="">
                <?php wp_nonce_field('wp_spaces_cancel_sync', 'wp_spaces_cancel_sync_nonce'); ?>
                <input type="hidden" name="action" value="cancel_sync" />
                <p><input type="submit" class="button button-secondary" value="<?php _e('Cancel Sync', 'wp-spaces'); ?>" /></p>
            </form>
            <?php
        } else {
            // Single button form - either Enable, Disable, or Reverse Sync
            ?>
            <form method="post" action="">
                <?php wp_nonce_field('wp_spaces_sync_action', 'wp_spaces_sync_nonce'); ?>
                <?php if (!$sync_enabled): ?>
                    <input type="hidden" name="action" value="enable_sync" />
                    <p><input type="submit" class="button button-primary" value="<?php _e('Enable Sync', 'wp-spaces'); ?>" /></p>
                <?php else: ?>
                    <input type="hidden" name="action" value="disable_sync" />
                    <p><input type="submit" class="button button-primary" value="<?php _e('Disable Sync', 'wp-spaces'); ?>" /></p>
                <?php endif; ?>
            </form>
            <?php
        }
        ?>

        <?php if ($sync_in_progress || $reverse_sync_in_progress): ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                function updateProgress() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wp_spaces_get_progress',
                            nonce: '<?php echo wp_create_nonce('wp_spaces_progress_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                const data = response.data;
                                const percentage = data.total > 0 ? Math.round((data.progress / data.total) * 100) : 0;
                                
                                $('.wp-spaces-progress-bar').css('width', percentage + '%');
                                $('.wp-spaces-sync-progress p').text(
                                    `${data.type} Progress: ${data.progress} of ${data.total} files processed (${percentage}%)`
                                );

                                // If sync is complete, refresh the page
                                if (data.complete) {
                                    location.reload();
                                } else {
                                    // Continue polling
                                    setTimeout(updateProgress, 2000);
                                }
                            }
                        }
                    });
                }

                // Start the progress updates
                updateProgress();
            });
            </script>
        <?php endif; ?>
    </div>
<?php
}

/**
 * Renders the subfolder mode checkbox.
 * 
 * Allows users to enable storage of files in a specific
 * subfolder within their Space. When enabled, shows
 * additional configuration options.
 * 
 * @since 0.6
 * @return void
 */
function wp_spaces_use_subfolder_render() {
    $options = get_option('wp_spaces_settings');
    ?>
    <input type='checkbox' name='wp_spaces_settings[use_subfolder]' 
           <?php checked(isset($options['use_subfolder']) ? $options['use_subfolder'] : false); ?>>
    <p class="description">
        <?php _e('Enable to store files in a specific subfolder within the bucket', 'wp-spaces'); ?>
    </p>
    <?php
}

/**
 * Renders the subfolder name input field.
 * 
 * Provides an input for the subfolder name within the Space.
 * Defaults to the site's hostname and is only active when
 * subfolder mode is enabled. Includes validation for
 * proper naming conventions.
 * 
 * @since 0.6
 * @return void
 */
function wp_spaces_subfolder_name_render() {
    $options = get_option('wp_spaces_settings');
    $default_subfolder = parse_url(get_site_url(), PHP_URL_HOST);
    ?>
    <input type='text' name='wp_spaces_settings[subfolder_name]' 
           value='<?php echo isset($options['subfolder_name']) ? esc_attr($options['subfolder_name']) : esc_attr($default_subfolder); ?>'
           <?php echo !isset($options['use_subfolder']) ? 'disabled' : ''; ?>>
    <p class="description">
        <?php _e('Subfolder name (e.g., example.com) to organize your media files.', 'wp-spaces'); ?>
    </p>
    <?php
}

/**
 * Adds JavaScript to handle dynamic form behavior.
 * 
 * Initializes jQuery handlers to:
 * - Toggle the subfolder name input based on checkbox state
 * - Manage input field states dynamically
 * - Update UI elements based on user interactions
 * 
 * @since 0.6
 * @return void
 */
add_action('admin_footer', 'wp_spaces_subfolder_scripts');
function wp_spaces_subfolder_scripts() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        const subfolderToggle = $('input[name="wp_spaces_settings[use_subfolder]"]');
        const subfolderInput = $('input[name="wp_spaces_settings[subfolder_name]"]');
        
        function toggleSubfolderInput() {
            subfolderInput.prop('disabled', !subfolderToggle.is(':checked'));
        }
        
        subfolderToggle.on('change', toggleSubfolderInput);
        toggleSubfolderInput();
    });
    </script>
    <?php
}
