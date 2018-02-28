<?php

/**
 * All sorts of cleanup and sanity checks for existing CrawlerUrls.
 **/

require_once __DIR__ . '/../../phplib/Core.php';
require_once __DIR__ . '/../../phplib/third-party/simple_html_dom.php';

$varMap = []; // map of siteIDs to variables

$config = parse_ini_file('crawler.conf', true);
foreach ($config as $section => $vars) {
  if (Str::startsWith($section, 'site-')) {
    $siteId = explode('-', $section, 2)[1];
    $varMap[$siteId] = $vars;
  }
}

$root = $config['global']['path'];

define('BATCH_SIZE', 100);
$offset = 0;

do {
  $cus = Model::factory('CrawlerUrl')
       ->order_by_asc('id')
       ->limit(BATCH_SIZE)
       ->offset($offset)
       ->find_many();

  foreach ($cus as $cu) {
    $cu->setRoot($root);
    $cu->createParser();

    $vars = $varMap[$cu->siteId];
    $changed = false;

    $oldAuthor = $cu->author;
    $cu->extractAuthor($vars['authorSelector'], $vars['authorRegexp']);
    if ($cu->author != $oldAuthor) {
      $changed = true;
      Log::warning("[%d] author changed for [%s]:\n[%s] ->\n[%s]",
                   $cu->id, $cu->url, $oldAuthor, $cu->author);
    }

    $oldTitle = $cu->title;
    $cu->extractTitle($vars['titleSelector']);
    if ($cu->title != $oldTitle) {
      $changed = true;
      Log::warning("[%d] title changed for [%s]:\n[%s] ->\n[%s]",
                   $cu->id, $cu->url, $oldTitle, $cu->title);
    }

    if ($changed) {
      $cu->save();
    }

    $cu->loadBody();
    $oldBody = $cu->getBody();
    $cu->extractBody($vars['bodySelector']);
    if ($cu->getBody() != $oldBody) {
      Log::warning("[%d] body changed for [%s]", $cu->id, $cu->url);
      $cu->saveBody();
    }

    $cu->freeParser();
  }

  $offset += count($cus);
  Log::info("Processed $offset crawled URLs.");
} while (count($cus) == BATCH_SIZE);
