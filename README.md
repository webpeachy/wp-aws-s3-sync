WP Media AWS S3 Sync
====================

WP Media AWS S3 Sync is a WordPress plugin that allows you to easily sync your WordPress media files to Amazon S3. With this plugin, you can sync all of your media files, including images, videos, and audio files, to an S3 bucket. This helps you reduce the storage space used by your website and improve its loading time.

Installation
------------

To install WP Media AWS S3 Sync, follow these simple steps:

1.  Download the latest release of the plugin from the GitHub repository.
    
2.  Upload the plugin directory to the `/wp-content/plugins/` directory.
    
3.  Activate the plugin through the 'Plugins' menu in WordPress.
    
4.  Go to the plugin settings page and configure the options as needed.
    
5.  Set your AWS access key and secret key in your `wp-config.php` file by adding the following lines:
    
    
    `define('AWS_ACCESS_KEY_ID', 'your_access_key_here'); define('AWS_SECRET_ACCESS_KEY', 'your_secret_key_here');`
    
    Replace `your_access_key_here` and `your_secret_key_here` with your actual AWS access key and secret key.
    

Disclaimer
----------

This plugin is not intended for use in production environments and is provided for educational purposes only. Use of this plugin in any other context is at your own risk.
