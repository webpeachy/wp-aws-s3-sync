<?php
/*
Plugin Name: WP S3 Sync
Plugin URI: https://webpeachy.io
Description: Syncs WordPress media library with Amazon S3
Version: 1.0
Author: WepPeachy
Author URI: https://webpeachy.io
*/

require(plugin_dir_path(__FILE__) . 'vendor/autoload.php');

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class MyS3Plugin {
    /**
     * The AWS S3 client instance.
     *
     * @var S3Client
     */
    private $s3;

    /**
     * The name of the S3 bucket.
     *
     * @var string
     */
    private $bucket_name;

     /**
     * The region of the S3 bucket.
     *
     * @var string
     */
    private $bucket_region;

    /**
     * Initializes a new instance of the MyS3Plugin class.
     */
    public function __construct() {
        $this->s3 = new S3Client([
            'version'     => 'latest',
            'region'      => 'us-east-1',
            'credentials' => [
                'key'    => AWS_S3_USER_ACCESS_KEY,
                'secret' => AWS_S3_USER_SECRET_KEY,
            ],
            'use_aws_shared_config_files' => false
        ]);

        $this->bucket_name = 'your-bucket-url';
        $this->bucket_region = 'us-east-1';
        $this->cdn_url = 'cloudfront-url-from-cloudformation';
    }

     /**
     * Initializes the plugin by setting up necessary hooks.
     *
     * @return void
     */
    public function init(): void {
        add_filter('wp_handle_upload', [$this, 'upload_s3'], 10, 1);
        add_action('delete_attachment', [$this, 'delete_image_from_s3'], 10, 1);
        add_filter('intermediate_image_sizes_advanced', [$this, 'remove_image_sizes']);
        add_filter('wp_get_attachment_url', [$this, 'replace_image_base_url'], 10, 2);
    }

    /**
     * Uploads a file to S3.
     *
     * @param string $file_path The local path of the file to upload.
     * @param string $file_key  The S3 key to use for the uploaded file.
     *
     * @return void
     */
    public function upload_to_s3(string $file_path, string $file_key): void {
        try {
            $result = $this->s3->putObject([
                'Bucket' => $this->bucket_name,
                'Key'    => 'uploads/' . $file_key,
                'Body'   => fopen($file_path, 'r'),
                'ACL'    => 'private',
            ]);

            // Check if the file was uploaded successfully
            if (isset($result['ObjectURL'])) {
                // Delete the file from the local disk
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }

        } catch (S3Exception $e) {
            echo "There was an error uploading the file.\n";
        }
    }

    /**
     * Deletes a file from disk.
     *
     * @param string $file_path The relative file path to delete.
     *
     * @return bool True if the file was successfully deleted, false otherwise.
     */
    function delete_from_disk(string $file_path): bool {
        // Get the upload directory
        $upload_dir = wp_upload_dir();

        // Combine the base directory with the file path
        $full_file_path = trailingslashit($upload_dir['basedir']) . $file_path;

        // Check if the file exists and then delete it
        if (file_exists($full_file_path)) {
            return unlink($full_file_path);
        }

        return false;
    }

    /**
     * Deletes a file from S3.
     *
     * @param string $file_key The S3 key of the file to delete.
     *
     * @return void
     */
    public function delete_from_s3(string $file_key): void {
        try {
            if (is_null($this->s3)) {
                return;
            }
            $this->s3->deleteObject([
                'Bucket' => $this->bucket_name,
                'Key'    => 'uploads/' . $file_key,
            ]);

        } catch (AS3Exception $e) {
            echo "There was an error uploading the file.\n";
        }
    }

    /**
     * Deletes an image file from S3.
     *
     * @param int $post_id The ID of the post to which the image is attached.
     *
     * @return void
     */
    public function delete_image_from_s3(int $post_id): void {
        $attachment_url = wp_get_attachment_url($post_id);
        $path = explode(wp_upload_dir()['baseurl'] . '/', $attachment_url)[1];
        $this->delete_from_s3($path);
    }

    /**
     * Uploads a file to S3 and returns its metadata.
     *
     * @param array $metadata The metadata of the file being uploaded.
     *
     * @return array
     */
    public function upload_s3(array $metadata): array {
        $upload_path = explode(wp_upload_dir()['baseurl'] . '/', $metadata['url'])[1];
        $this->upload_to_s3($metadata['file'], $upload_path);
        return $metadata;
    }

    /**
     * Prevent WordPress from creating additional image sizes.
     *
     * @param array $sizes An array of image sizes.
     *
     * @return array An empty array.
     */
    public function remove_image_sizes(array $sizes): array {
        return [];
    }

    /**
     * Replace the default WordPress image base URL with the S3 bucket URL.
     *
     * @param string $url The original attachment URL.
     * @param int $post_id The attachment post ID.
     *
     * @return string The modified attachment URL.
     */
    function replace_image_base_url(string $url, int $post_id): string {
        $bucket_name = $this->bucket_name; // Replace with your actual bucket name

        $upload_dir = wp_get_upload_dir();
        $base_url = $upload_dir['baseurl'];

        if (strpos($url, $base_url) === 0) {
            $relative_path = substr($url, strlen($base_url));
            //return $this->get_s3_url($relative_path);
            return $this->get_aws_image_handler_cdn_url($relative_path);
        }
    }

   /**
     * Get the S3 URL for a given relative path.
     *
     * This method returns the URL of the file in the S3 bucket.
     *
     * @param string $relative_path The relative path of the file within the S3 bucket.
     *
     * @return string The S3 URL for the file.
     */
    public function get_s3_url(string $relative_path): string {
        // Construct the base URL for the S3 bucket.
        $new_base_url = "https://".$this->bucket_name.".s3.amazonaws.com/uploads";
        
        // Return the complete URL for the file in the S3 bucket.
        return $new_base_url . $relative_path;
    }

    /**
     * Get the AWS Image Handler CDN URL for a given relative path.
     *
     * This method returns the CDN URL for the file using the AWS Image Handler.
     *
     * @param string $relative_path The relative path of the file within the S3 bucket.
     *
     * @return string The AWS Image Handler CDN URL for the file.
     */
    public function get_aws_image_handler_cdn_url(string $relative_path): string {
        // Prepare the JSON object with the bucket name and file key.
        $json = json_encode(array("bucket" => $this->bucket_name, "key" => "uploads" . $relative_path));

        // Encode the JSON object in base64 format.
        $json_base64 = base64_encode($json);

        // Construct the CDN URL using the AWS Image Handler.
        $url = $this->cdn_url . "/" . $json_base64;

        // Return the CDN URL for the file.
        return $url;
    }
}
/**
 * Checks if the AWS S3 user access and secret keys are defined, and initializes the S3 plugin if they are.
 *
 * @return void
 */
if (defined('AWS_S3_USER_ACCESS_KEY') && defined('AWS_S3_USER_SECRET_KEY')) {
    $my_s3_plugin = new MyS3Plugin();
    $my_s3_plugin->init();
}