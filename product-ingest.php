#!/usr/bin/php
<?php
/* 
 * CHSWX Product Ingestor
 * Command-line tool
 * Main entry point for LDM ingest. This hands off to a factory which generates a class for specific products.
 * Many thanks to @blairblends, @edarc, and the Updraft team for help and inspiration
 */

// Start timing script execution.
$time_start = microtime(true);

//
// Support Files
//

// Bring in configuration.
include('conf/chswx.conf.php');

// Composer autoload.
include('vendor/autoload.php');

use Abraham\TwitterOAuth\TwitterOAuth;

// Bring in the abstract class definition for NWSProduct.
include('inc/NWSProduct.class.php');

// And its factory
include('inc/NWSProductFactory.class.php');

// Mustache library
include('lib/mustache/Mustache.php');

// Geodata library
include('inc/geo/GeoLookup.class.php');

// Tweet generation library
include('inc/output/WxTweet.class.php');

// Initialize Mustache
$m = new Mustache;

// Bring in the Twitter OAuth lib and local config.
if(!defined('LOCAL_DEBUG')) {
    include('oauth.config.php');
}

//
// Execution time
//

// Get the file path from the command line.
$file_path = $argv[1];

// Bring in the file
$m_text = file_get_contents($file_path);

// Sanitize the file
$output = trim($m_text, "\x00..\x1F");

// Get the WMO ID
preg_match('/[A-Z][A-Z][A-Z][A-Z][0-9][0-9]/',$output,$matches);
$wmo_id = $matches[0];
//echo "WMO ID is $wmo_id";

log_message("Product ingest running - WMO ID: " . $wmo_id . " File Path: " . $file_path);

//
// TODO: Move this check back later in the sequence
//

// Check if the product contains $$ identifiers for multiple products
if(strpos($output, "$$")) {
    // Loop over the file for multiple products within one file identified by $$
    $products = explode('$$',trim($output), -1);
}
else {
    // No delimiters
    $products = array(trim($output));
}

//
// Kick off the factory for each parsed product
//

foreach($products as $product)
{
    $product_parsed = NWSProductFactory::parse_product($wmo_id,$product);
    if(!is_null($product_parsed)) {
        //$product_data = $product_parsed->get_properties();
        if($product_parsed->can_relay() && $product_parsed->in_zone($active_zones)) {
            mail('jared@chswx.com', $product_parsed->get_name() . " for " . $product_parsed->get_location_string(), $product_parsed->get_product_text(),'From: alerts@chswx.com');
        }
        // Authenticate with Twitter
        if(class_exists('Abraham\TwitterOAuth\TwitterOAuth')) {
            $twitter = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);
        }
        $tweets = $product_parsed->get_tweets();

        if(!empty($tweets)) {
            foreach($tweets as $tweet_text) {
                //echo "Length of tweet: " . strlen($tweet_text) . "\n";
                log_message("Tweeting: " . $tweet_text);
                if(isset($twitter)) {
                    $response = $twitter->post('statuses/update',array('status' => $tweet_text));
                    log_message("Twitter responded with: ");
                    var_dump($response);
                    if(!$response) {
                        log_message("product-ingest.php: Tweet of length " . strlen($tweet_text) . " failed: " . $tweet_text);
                    }
                } else {
                    log_message("There is no Twitter library!");
                }
            }
        }
        else
        {
            log_message("product-ingest.php: No tweet for $wmo_id from " . $product_parsed->get_vtec_wfo());
        }
    }
    else {
        log_message("product-ingest.php: Product parser for $wmo_id is null.");
    }
}

function log_message($message) {
    $log_format = "[" . date('m-d-Y g:i:s A') . "] " . $message . "\n";
    $log_location = '/home/ldm/chswx-error.log';
    $log_mode = 0; 	// defaults to syslog/stderr

    //echo $message;

    if(file_exists('/home/ldm/chswx-error.log')) {
        $log_mode = 3;
        error_log($log_format,$log_mode,$log_location);
    }
    else {
        error_log($log_format,$log_mode);
    }
}

$time_end = microtime(true);
$time = $time_end - $time_start;
log_message("Script execution completed in " . $time . " seconds.");
