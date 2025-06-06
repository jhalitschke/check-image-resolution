# Check Image Resolution

A WordPress plugin that checks the resolution (DPI) and color model (RGB) of uploaded images, preventing uploads with DPI greater than 96 or non-RGB color models (e.g., CMYK). Also provides WP-CLI commands for bulk-checking images in the media library.

---

## Features

- **Block uploads** of images with DPI > 96.
- **Block uploads** of images not in RGB color model (e.g., CMYK images).
- **Admin notice** if the [Gmagick](https://www.php.net/manual/en/book.gmagick.php) PHP extension is not installed/enabled.
- **WP-CLI support** for bulk-checking all image attachments in the media library.

---

## Requirements

- WordPress 5.0+ (tested up to 6.5)
- PHP 7.4+
- [Gmagick PHP extension](https://www.php.net/manual/en/book.gmagick.php)

---

## Installation

1. Install the Gmagick PHP extension on your server.
   - For Ubuntu/Debian:  
     `sudo apt-get install php-gmagick`
2. Restart your web server (`apache2`, `nginx`, etc.) if needed.
3. Download or clone this repository to your WordPress plugins directory:
   ```
   git clone https://github.com/jhalitschke/check-image-resolution.git wp-content/plugins/check-image-resolution
   ```
4. Activate the plugin through the WordPress admin under **Plugins**.

---

## Usage

### Automatically Enforced on Upload

When a user uploads an image via the Media Library, the plugin will:

- **Block** files with DPI greater than 96.
- **Block** files not in the RGB color model.
- Show a relevant error message if the image fails these checks.

### Admin Notices

If the Gmagick extension is missing, admins will see an error notice in the WordPress dashboard.

---

## WP-CLI Commands

You can use the WP-CLI command to check all parent image attachments in your media library:

```bash
wp check-image-resolution check-attachments
```

#### Options

- `--bulk-size=<number>`: Number of images to process per batch. Default: `100`.
- `--url=<url>`: Set the site URL (handled by WP-CLI core).

#### Example

Check all images in batches of 50:

```bash
wp check-image-resolution check-attachments --bulk-size=50
```

---

## How It Works

- Uses the Gmagick extension to read image metadata.
- Checks `density` property for DPI and `colorspace` for color model.
- Hooks into `wp_handle_upload_prefilter` to validate images before upload completes.
- Adds admin notices for errors and missing requirements.

---

## Troubleshooting

- **Missing Gmagick?**  
  Ensure the extension is installed and enabled. The plugin will not function without it!
- **Uploads blocked unexpectedly?**  
  Check your image's DPI and color model using an image editor (e.g., Photoshop, GIMP).
- **WP-CLI errors?**  
  Run `php -m | grep gmagick` to verify Gmagick is available for CLI PHP.

---

## Contributing

Pull requests and suggestions are welcome! Please open an issue or PR on [GitHub](https://github.com/jhalitschke/check-image-resolution).

---

## Author

- **jhalitschke**  
  [GitHub Profile](https://github.com/jhalitschke)

---

## License

MIT License
