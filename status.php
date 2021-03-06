<?php

include 'WorkflowCreator.php';
include 'funcs.php';

$creator = new WorkflowCreator();

$ghrepos = [];
foreach (array_values($creator->createCrons('ci')) as $_ghrepos) {
    $ghrepos = array_merge($ghrepos, $_ghrepos);
}

$title = 'MNT Use GitHub Actions CI';

$h = [];
$h[] = '<style>';
$h[] = 'table { border-collapse: collapse; margin: 0 auto; }';
$h[] = 'td { border: 1px solid #ddd; padding: 4px; font-family: arial; font-size: 13px; }';
$h[] = '.odd td { background-color: #eee; }';
$h[] = '.even td { background-color: #dfdfdf; }';
$h[] = '</style>';
$h[] = '<table>';
$class = 'even';
foreach ($ghrepos as $ghrepo) {
    if (in_array($ghrepo, [
        'fallback',
        'silverstripe-upgrader',
        'webpack-config',
        'esling-config',
        'silverstripe-simple'
    ])) {
        continue;
    }
    $pr_branch = 'unknown';
    $pr_link = "https://github.com/$ghrepo/pulls";
    echo "Fetching PR data for $ghrepo\n";
    $ch = create_ch("https://api.github.com/repos/$ghrepo/pulls");
    $result = curl_exec($ch);
    $resp_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!$result) {
        echo "Could not fetch pull-requests for $ghrepo, exiting\n";
        die;
    }
    foreach (json_decode($result) as $pr) {
        if ($pr->title == $title) {
            $pr_link = $pr->html_url;
            $pr_branch = urlencode($pr->head->ref);
            break;
        }
    }
    $ss_minor_branch = 'unknown';
    $default_branch = 'master';
    echo "Fetching branch data for $ghrepo\n";
    $ch = create_ch("https://api.github.com/repos/$ghrepo/branches");
    $result = curl_exec($ch);
    $resp_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!$result) {
        echo "Could not fetch pull-requests for $ghrepo, exiting\n";
        die;
    }
    $minor_branches = [];
    $default_branches = [];
    foreach (json_decode($result) as $br) {
        $branch = $br->name;
        if (preg_match('#^[1-9]$#', $branch)) {
            $default_branches[] = $branch;
        }
        if (preg_match('#^[1-9]\.[0-9]+$#', $branch)) {
            $minor_branches[] = $branch;
        }
    }
    natsort($minor_branches);
    $minor_branches = array_reverse($minor_branches);
    if (count($minor_branches) == 1) {
        $ss_minor_branch = $minor_branches[0];
    }
    if (count($minor_branches) >= 2) {
        $ss_minor_branch = $minor_branches[1];
    }
    if (count($default_branches)) {
        $default_branch = max($default_branches);
    }
    $ccs_repo = 'creative-commoners/' . explode('/', $ghrepo)[1];
    $ccs_badge_src = "https://github.com/$ccs_repo/actions/workflows/ci.yml/badge.svg?branch=$pr_branch";
    $ccs_actions_href = "https://github.com/$ccs_repo/actions?query=branch%3A$pr_branch";
    $ss_badge_src = "https://github.com/$ghrepo/actions/workflows/ci.yml/badge.svg?branch=$ss_minor_branch";
    $ss_actions_href = "https://github.com/$ghrepo/actions?query=branch%3A$ss_minor_branch";
    $workflow_href = "https://github.com/$ghrepo/blob/$ss_minor_branch/.github/workflows/ci.yml";
    $travis_href = "https://github.com/$ghrepo/blob/$default_branch/.travis.yml";
    $class = $class == 'even' ? 'odd' : 'even';
    $h[] = implode("\n", [
        "<tr class=\"$class\">",
            "<td><a href=\"$pr_link\" target=\"_blank\">$ghrepo</a></td>",
            "<td><a href=\"$ccs_actions_href\" target=\"_blank\"><img src=\"$ccs_badge_src\"></a></td>",
            "<td><a href=\"$ss_actions_href\" target=\"_blank\"><img src=\"$ss_badge_src\"></a></td>",
            "<td><a href=\"$travis_href\" target=\"_blank\">.travis.yml</a></td>",
            "<td><a href=\"$workflow_href\" target=\"_blank\">merged ci.yml</td>",
        '</tr>'
    ]);
}
$h[] = '</table>';
file_put_contents('status.html', implode("\n", $h));
