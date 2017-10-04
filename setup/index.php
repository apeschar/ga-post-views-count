<?php

require_once '../src/bootstrap.php';

$scopes = [
    Google_Service_Analytics::ANALYTICS_READONLY,
];

$error = null;
$success = false;

$token_file = dirname(dirname(__FILE__)) . '/config/authorization_token.php';

$exists = file_exists($token_file);
if($exists)
    goto view;

$client_secrets = file_get_contents(dirname(__FILE__) . '/../config/client_secrets.json.php');
if (!$client_secrets)
    die("Could not read config/client_secrets.json.php");

$client_secrets = preg_replace('~^\<\?.*\?>\s*~', '', $client_secrets);

$client_secrets = json_decode($client_secrets, true);
if (!$client_secrets)
    die("Could not decode config/client_secrets.json.php");

$client = new Google_Client();
$client->setAuthConfig($client_secrets);
$client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');

if(!empty($_POST['auth_code'])) {
    $auth_code = trim($_POST['auth_code']);

    try {
        $token = $client->authenticate($_POST['auth_code']);
    } catch(Google_Auth_Exception $e) {
        $error = "Authorization failed. ({$e->getMessage()})";
        goto view;
    }

    if(empty($token)) {
        $error = "Authorization failed. (No data was returned.)";
        goto view;
    }

    if(empty($token['refresh_token'])) {
        $error = "Authorization failed. (Token does not contain 'refresh_token')";
        goto view;
    }

    $success = true;

    $token_output = sprintf("<?php return %s;\n", trim(var_export($token, true)));

    if(!@file_put_contents($token_file, $token_output)) {
        $token_uri = 'data:application/octet-stream;base64,' . base64_encode($token);
        $token_basename = basename($token_file);
        $error = "I tried to save the authorization token to {$token_file}, but couldn't. Please save the file manually.<p class=text-right><a download='$token_basename' href='$token_uri' class='btn btn-primary'>Download authorization token</a></p>";
        goto view;
    }
}

view:

if(!$exists && !$success) {
    foreach ($scopes as $scope) {
        $client->addScope($scope);
    }
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $auth_url = $client->createAuthUrl();
}

?>
<!doctype html>
<html>
<head>
<title>Connect My Business API</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
<style>
body { padding-top: 100px; }
</style>
</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-lg-6 col-lg-offset-3">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Connect My Business API</h3>
                </div>
                <div class="panel-body">
                    <?php if($error): ?>
                    <div class="alert alert-danger">
                        <strong>Oops!</strong> <?= $error; ?>
                    </div>
                    <?php endif; ?>
                    <?php if($success): ?>
                    <div class="alert alert-success">
                        The authorization token was retrieved successfully.
                    </div>
                    <?php elseif($exists): ?>
                    <div class="alert alert-warning">
                        The authorization token file already exists. You'll need to delete it first.
                    </div>
                    <?php else: ?>
                    <p>
                        <strong>Step 1.</strong>
                        Click this button and copy the authorization code.
                    </p>
                    <p>
                        <a href="<?= $auth_url; ?>" class="btn btn-block btn-primary" target="_blank">
                            Retrieve authorization code
                        </a>
                    </p>
                    <p>
                        <strong>Step 2.</strong>
                        Paste the authorization code in the field and press the button.
                    </p>
                    <form action="<?= htmlentities($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" method="POST">
                        <p>
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Paste authorization code here..." name="auth_code">
                                <span class="input-group-btn">
                                    <button class="btn btn-default btn-success" type="submit">Authorize</button>
                                </span>
                            </div>
                        </p>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
