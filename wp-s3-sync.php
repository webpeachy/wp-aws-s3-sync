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

        $this->bucket_name = 'wordpress-uploads-sync';
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

        } catch (S3Exception $e) {
            echo "There was an error uploading the file.\n";
        }
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
     * Initializes the plugin by setting up necessary hooks.
     *
     * @return void
     */
    public function init(): void {
        add_filter('wp_handle_upload', [$this, 'upload_s3'], 10, 1);
        add_action('delete_attachment', [$this, 'delete_image_from_s3'], 10, 1);
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