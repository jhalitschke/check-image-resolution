<?php
/*
Plugin Name: Check Image Resolution
Description: Checks if the uploaded image has a DPI greater than 96 and if it is not in RGB color model.
Version: 1.0
Author: jhalitschke
*/

namespace JHalitschke\CheckImageResolution;

use WP_CLI;

/**
 * Main plugin class for Check Image Resolution.
 */
if (!class_exists('\JHalitschke\CheckImageResolution\Plugin')) {

    /**
     * Class Plugin
     *
     * Handles all plugin logic for checking image DPI and color model on upload.
     */
    class Plugin {

        /** @var string Option key for tracking Gmagick extension state */
        public const GMAGICK_OPTION_KEY = 'check_image_resolution_gmagick_missing';

        /**
         * Plugin constructor.
         *
         * Registers hooks for plugin activation, admin notices, and upload filtering.
         */
        public function __construct() {
            // Register activation hook
            register_activation_hook(__FILE__, [$this, 'on_activation']);

            // Admin notices
            add_action('admin_notices', [$this, 'show_gmagick_missing_admin_notice']);
            add_action('admin_init', [$this, 'clear_gmagick_missing_flag_if_available']);

            // Image upload filter
            add_action('wp_handle_upload_prefilter', [$this, 'check_image_properties']);

            // Register WP-CLI command if WP_CLI is defined
            if (defined('WP_CLI') && WP_CLI) {
                WP_CLI::add_command('check-image-resolution', [WPCLI::class, 'check_attachments_command']);
            }
        }

        /**
         * Plugin activation callback.
         *
         * Sets an option if Gmagick extension is missing on activation.
         *
         * @return void
         */
        public function on_activation(): void {
            if (!extension_loaded('gmagick')) {
                update_option(self::GMAGICK_OPTION_KEY, 1);
            } else {
                delete_option(self::GMAGICK_OPTION_KEY);
            }
        }

        /**
         * Show admin notice if Gmagick extension is missing.
         *
         * @return void
         */
        public function show_gmagick_missing_admin_notice(): void {
            if (get_option(self::GMAGICK_OPTION_KEY)) {
                echo '<div class="notice notice-error"><p><strong>Check Image Resolution:</strong> The <code>Gmagick</code> PHP extension is not installed or enabled. This plugin will not function correctly without it.</p></div>';
            }
        }

        /**
         * Optionally clear the missing Gmagick flag if the extension is now available.
         *
         * @return void
         */
        public function clear_gmagick_missing_flag_if_available(): void {
            if (extension_loaded('gmagick') && get_option(self::GMAGICK_OPTION_KEY)) {
                delete_option(self::GMAGICK_OPTION_KEY);
            }
        }

        /**
         * Checks image properties on upload: DPI and color model.
         *
         * @param array $image Uploaded image array.
         * @return array|\WP_Error
         */
        public function check_image_properties(array $image): array|\WP_Error {
            $dpi_check = $this->check_image_dpi($image);
            if ($dpi_check instanceof \WP_Error) {
                $this->add_upload_error_notice($dpi_check->get_error_message());
                return $dpi_check;
            }

            $color_model_check = $this->check_image_color_model($image);
            if ($color_model_check instanceof \WP_Error) {
                $this->add_upload_error_notice($color_model_check->get_error_message());
                return $color_model_check;
            }

            return $image;
        }

        /**
         * Checks if the uploaded image has a DPI greater than 96.
         *
         * @param array $image Uploaded image array.
         * @return array|\WP_Error
         */
        public function check_image_dpi(array $image): array|\WP_Error {
            if (!extension_loaded('gmagick')) {
                return new \WP_Error('gmagick_missing', 'Gmagick PHP extension is not loaded.');
            }
            $gmagick = new \Gmagick($image['file']);
            // Try to get density property, fallback to 0 if not available
            $density = $gmagick->getimageproperty('density'); // e.g. "300x300"
            if ($density) {
                $dpi = explode('x', $density);
                $x = isset($dpi[0]) ? (int)$dpi[0] : 0;
                $y = isset($dpi[1]) ? (int)$dpi[1] : 0;
            } else {
                $x = $y = 0;
            }
            if ($x > 96 || $y > 96) {
                return new \WP_Error('dpi_error', 'Image DPI resolution is greater than 96.');
            }
            return $image;
        }

        /**
         * Checks if the uploaded image is in RGB color model.
         *
         * @param array $image Uploaded image array.
         * @return array|\WP_Error
         */
        public function check_image_color_model(array $image): array|\WP_Error {
            if (!extension_loaded('gmagick')) {
                return new \WP_Error('gmagick_missing', 'Gmagick PHP extension is not loaded.');
            }
            $gmagick = new \Gmagick($image['file']);
            $colorSpace = $gmagick->getimagecolorspace();
            if ($colorSpace != \Gmagick::COLORSPACE_RGB) {
                return new \WP_Error('cmyk_error', 'Image is not in RGB color model.');
            }
            return $image;
        }

        /**
         * Adds an admin notice for upload errors.
         *
         * @param string $message The error message to display.
         * @return void
         */
        public function add_upload_error_notice(string $message): void {
            add_action('admin_notices', function() use ($message) {
                echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            });
        }
    }

    /**
     * WP-CLI sub-class for image resolution checks.
     */
    class WPCLI {

        /**
         * Checks all parent image attachments in the WordPress media library for DPI and color model.
         *
         * ## OPTIONS
         *
         * [--bulk-size=<number>]
         * : Number of images to process in one batch. Default: 100
         *
         * [--url=<url>]
         * : Set the site URL to operate on (handled by WP-CLI core).
         *
         * ## EXAMPLES
         *
         *     wp check-image-resolution check-attachments --bulk-size=50
         *
         * @when after_wp_load
         *
         * @param array $args WP-CLI positional arguments.
         * @param array $assoc_args WP-CLI associative args.
         * @return void
         */
        public static function check_attachments_command(array $args, array $assoc_args): void
        {
            $bulk_size = isset($assoc_args['bulk-size']) && is_numeric($assoc_args['bulk-size'])
                ? max(1, (int)$assoc_args['bulk-size'])
                : 100;

            $plugin = new Plugin();

            $total   = self::get_total_parent_attachments();
            $offset  = 0;
            $checked = 0;
            $errors  = 0;

            WP_CLI::log("Checking {$total} parent attachments in batches of {$bulk_size}...");

            while ($offset < $total) {
                $attachments = self::get_parent_attachments($bulk_size, $offset);
                if (empty($attachments)) {
                    break;
                }
                foreach ($attachments as $attachment) {
                    $file = get_attached_file($attachment->ID);
                    if (!$file || !file_exists($file)) {
                        WP_CLI::warning("Attachment {$attachment->ID} ({$attachment->post_title}) file missing: $file");
                        continue;
                    }

                    $image = [
                        'file' => $file,
                        'name' => basename($file),
                    ];

                    $dpi_check = $plugin->check_image_dpi($image);
                    $color_model_check = $plugin->check_image_color_model($image);

                    if ($dpi_check instanceof \WP_Error) {
                        WP_CLI::warning("Attachment {$attachment->ID} ({$attachment->post_title}): " . $dpi_check->get_error_message());
                        $errors++;
                    }
                    if ($color_model_check instanceof \WP_Error) {
                        WP_CLI::warning("Attachment {$attachment->ID} ({$attachment->post_title}): " . $color_model_check->get_error_message());
                        $errors++;
                    }
                    if (!($dpi_check instanceof \WP_Error) && !($color_model_check instanceof \WP_Error)) {
                        WP_CLI::success("Attachment {$attachment->ID} ({$attachment->post_title}) passed checks.");
                    }
                    $checked++;
                }
                $offset += $bulk_size;
            }

            WP_CLI::success("Checked $checked attachments. Found $errors errors.");
        }

        /**
         * Get the total number of parent image attachments.
         *
         * @return int
         */
        protected static function get_total_parent_attachments(): int
        {
            global $wpdb;
            return (int) $wpdb->get_var(
                "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' AND post_parent = 0"
            );
        }

        /**
         * Get a batch of parent image attachments.
         *
         * @param int $limit
         * @param int $offset
         * @return array
         */
        protected static function get_parent_attachments(int $limit, int $offset): array
        {
            global $wpdb;
            $sql = $wpdb->prepare(
                "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' AND post_parent = 0 ORDER BY ID ASC LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
            return $wpdb->get_results($sql);
        }
    }
}

/**
 * Initialize the Check Image Resolution plugin.
 */
if (class_exists('\JHalitschke\CheckImageResolution\Plugin')) {
    new \JHalitschke\CheckImageResolution\Plugin();
}
