<?php

if (PHP_SAPI != 'cli') {
    die("This script must be run from the cli.\n");
}

require_once __DIR__ . '/../../../wp-load.php';
require_once __DIR__ . '/vendor/google-api-php-client/vendor/autoload.php';

$config = require_once __DIR__ . '/config.php';

if (empty($config['client_secrets'])) {
    die("Client secrets missing.");
}

$client = new Google_Client();

$client->setAuthConfig($config['client_secrets']);
$client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);

if (!empty($config['authorization_token'])) {
    $client->setAccessToken($config['authorization_token']);
}

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
$request->setViewId($config['view_id']);
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
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'post_views_count'");

foreach ($post_views_count as $post_id => $views) {
    $wpdb->query("
        INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
        VALUES ({$post_id}, 'post_views_count', {$views})
    ");
}

$wpdb->query("COMMIT");

if ($config['snitch']) {
    @file_get_contents($config['snitch']);
}
