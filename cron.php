<?php

if (PHP_SAPI != 'cli') {
    die("This script must be run from the cli.\n");
}

require_once __DIR__ . '/../../../wp-load.php';
require_once __DIR__ . '/vendor/google-api-php-client/vendor/autoload.php';
require_once __DIR__ . '/config.php';

$client_secrets = file_get_contents(__DIR__ . '/config/client_secrets.json.php');
if (!$client_secrets)
    die("Could not read config/client_secrets.json.php");

$client_secrets = preg_replace('~^\<\?.*\?>\s*~', '', $client_secrets);

$client_secrets = json_decode($client_secrets, true);
if (!$client_secrets)
    die("Could not decode config/client_secrets.json.php");

$credentials = require 'config/authorization_token.php';

if (empty($credentials)) {
    die("Failed to read credentials.\n");
}

$client = new Google_Client();
$client->setAuthConfig($client_secrets);
$client->setAccessToken($credentials);

$analytics = new Google_Service_AnalyticsReporting($client);

$dateRange = new Google_Service_AnalyticsReporting_DateRange();
$dateRange->setStartDate("7daysAgo");
$dateRange->setEndDate("today");

$path = new Google_Service_AnalyticsReporting_Dimension();
$path->setName("ga:pagePath");

$sessions = new Google_Service_AnalyticsReporting_Metric();
$sessions->setExpression("ga:sessions");
$sessions->setAlias("sessions");

$sessions_order = new Google_Service_AnalyticsReporting_OrderBy();
$sessions_order->setFieldName('ga:sessions');
$sessions_order->setSortOrder('DESCENDING');

$request = new Google_Service_AnalyticsReporting_ReportRequest();
$request->setViewId($view_id);
$request->setDateRanges($dateRange);
$request->setDimensions(array($path));
$request->setMetrics(array($sessions));
$request->setOrderBys(array($sessions_order));

$body = new Google_Service_AnalyticsReporting_GetReportsRequest();
$body->setReportRequests(array($request));
$report = $analytics->reports->batchGet($body);

foreach ($report->getReports() as $report) {
    foreach ($report->getData()->getRows() as $row) {
        $path = $row->getDimensions()[0];
        $sessions = $row->getMetrics()[0]->getValues()[0];

        if ($post_id = url_to_postid($path)) {
            if (!isset($post_views_count[$post_id])) {
                $post_views_count[$post_id] = 0;
            }
            $post_views_count[$post_id] += $sessions;
        }
    }
}

if (empty($post_views_count)) {
    echo "No posts?\n";
    exit;
}

if (posix_isatty(STDOUT)) {
    print_r($post_views_count);
}

$wpdb->query("BEGIN");
$wpdb->query("DELETE FROM wp_postmeta WHERE meta_key = 'post_views_count'");

foreach ($post_views_count as $post_id => $views) {
    $wpdb->query("
        INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
        VALUES ({$post_id}, 'post_views_count', {$views})
    ");
}

$wpdb->query("COMMIT");

if ($snitch) {
    @file_get_contents($snitch);
}
