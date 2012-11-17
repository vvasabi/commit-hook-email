<?php

require 'vendor/autoload.php';

use Guzzle\Http\Client;

$githubToken = '';
$to = '';
$secret = '';

$config = __DIR__.'/config.php';
if (stream_resolve_include_path($config)) {
    include $config;
}

// check secret
if ($secret !== @$_GET['secret']) {
    exit;
}

// read hook request
$request = isset($_POST['payload']) ? $_POST['payload'] : '{}';
$json = json_decode($request);

$branch = $json->repository->name . ':' . $json->ref;
$many = count($json->commits) > 1;
$api = '/repos/' . $json->repository->owner->name . '/' . $json->repository->name . '/commits';

$client = new Client('https://api.github.com');

$i = 0;
foreach ($json->commits as $commit) {

    // prepare subject
    $subject = $commit->message . ' [' . $branch . ']';
    if ($many) {
        $subject .= '[' . $i++ . ']';
    }

    // prepare commit variables
    $id = $commit->id;
    $url = $commit->url;
    $from = $commit->author->name . ' <' . $commit->author->email . '>';

    // get commit diff from GitHub
    $response = $client->get($api . '/' . $id . '?access_token=' . $githubToken)->send();
    $body = (string) $response->getBody();

    $data = json_decode($body);

    $message = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body>';
    $message .= '<p>Commit: <a href="' . $url . '" class="commit">' . $id . '</a></p>';
    foreach ($data->files as $file) {

        $lines = explode("\n", $file->patch);

        $message .= '<pre style="margin: 0; border: 1px solid #d2e6ed;">';
        $message .= '<div style="background: #EAF2F5; color: #000; font-size: 11px; font-family: Monaco, monospace; padding: 7px 4px; border-bottom: 1px solid #e9f2f5;">' . $file->filename . '</div>';
        foreach ($lines as $line) {
            switch (substr($line, 0, 1)) {
                case '+':
                    $message .= '<div style="background: #DDFFDD; color: #000; font-size: 11px; font-family: Monaco, monospace;">'; // green
                    break;
                case '-':
                    $message .= '<div style="background: #FFDDDD; color: #000; font-size: 11px; font-family: Monaco, monospace;">'; // red
                    break;
                case '@':
                    $message .= '<div style="background: #f7fafb; color:#999999; font-size: 11px; font-family: Monaco, monospace;">'; // blue
                    break;
                default:
                    $message .= '<div style="background: #FFF; color: #000; font-size: 11px; font-family: Monaco, monospace;">'; // white, normal
            }
            $message .= htmlentities($line, ENT_QUOTES, "UTF-8");
            $message .= '</div>';
        }
        $message .= '</pre><br/>';
    }

    // send email
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
    $headers .= 'From: ' . $from . "\r\n";
    mail($to, $subject, $message, $headers);
}