<?php

require 'vendor/autoload.php';

use Goutte\Client;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

$gitlabToken = '';
$from = '';
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
$request = file_get_contents('php://input');
$json = json_decode($request);

$branch = $json->repository->name . ':' . $json->ref;
$many = count($json->commits) > 1;

$i = 0;
foreach ($json->commits as $commit) {

    // prepare subject
    $subject = $commit->message . ' [' . $branch . ']';
    if ($many) {
        $subject .= '[' . $i++ . ']';
    }

    // prepare commit variables
    $id = $commit->id;
    $url = str_replace('commits', 'commit', $commit->url);

    // crawl commit diff from GitLab
    $client = new Client();
    $crawler = $client->request('GET', $url . '?private_token=' . $gitlabToken);

    // remove GitLab layout
    $html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body>';
    $html .= '<p>Commit: <a href="' . $url . '" class="commit">' . $id . '</a></p>';
    foreach ($crawler->filter('.diff_file') as $node) {
        $html .= $node->ownerDocument->saveHTML($node);
    }
    $html .= '</body></html>';

    $css = file_get_contents('style.css');

    // convert CSS to inline styles for GMail
    $inline = new CssToInlineStyles($html, $css);
    $message = $inline->convert();

    // send email
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
    $headers .= 'From: ' . $commit->author->name . ' <' . $from . '>' . "\r\n";
    $headers .= 'Reply-To: ' . $commit->author->email . "\r\n";
    mail(filter_to($to, $commit->author->email), $subject, $message, $headers);
}

function filter_to($to, $exclude) {
  $receipients = explode(',', $to);
  $result = array();
  foreach ($receipients as $receipient) {
    if (trim($receipient) != $exclude) {
      array_push($result, $receipient);
    }
  }
  return implode(',', $result);
}

