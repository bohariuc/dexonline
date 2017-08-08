<?php
/**
 * Check if lexemes from an Entry can be easily mapped to trees from the same entry.
 * I'm investigating how to simplify search results.
 **/

require_once __DIR__ . '/../phplib/Core.php';

Log::notice('started');

$entries = Model::factory('Entry')
         ->where_gte('structStatus', Entry::STRUCT_STATUS_UNDER_REVIEW)
         ->order_by_asc('description')
         ->find_many();
foreach ($entries as $e) {
  $lexems = $e->getLexems();
  $trees = $e->getTrees();

  // keep track of how many lexemes each tree receives
  $count = [];
  $desc = [];
  foreach ($trees as $t) {
    $count[$t->id] = 0;

    // get the part before the (, if any, lowercased and trimmed of spaces and dashes
    $part = explode('(', $t->description)[0];
    $desc[$t->id] = mb_strtolower(trim($part, ' -'));
  }

  $orphans = false;

  if (count($trees) > 1) {
    foreach ($lexems as $l) {
      $found = false;

      foreach ($trees as $t) {
        if (mb_strtolower($l->formNoAccent) == $desc[$t->id]) {
          $count[$t->id]++;
          $found = true;
        }
      }

      if (!$found) {
        $l->orphan = true;
        $orphans = true;
      }
    }

    foreach ($trees as $t) {
      if (!$count[$t->id]) {
        $t->orphan = true;
        $orphans = true;
      }
    }
  }

  if ($orphans) {
    print "lexeme:";
    foreach ($lexems as $l) {
      printf(" [%s%s]", $l->orphan ? '*' : '', $l);
    }
    print "\narbori:";
    foreach ($trees as $t) {
      printf(" [%s%s]", $t->orphan ? '*' : '', $t->description);
    }
    print "\n----\n";
  }
}

Log::notice('finished');
