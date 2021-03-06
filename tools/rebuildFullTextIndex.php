<?php
require_once __DIR__ . '/../phplib/Core.php';
ini_set('max_execution_time', '3600');
ini_set('memory_limit', '512M');
assert_options(ASSERT_BAIL, 1);
ORM::get_db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

Log::notice('started');
if (!Lock::acquire(Lock::FULL_TEXT_INDEX)) {
  OS::errorAndExit('Lock already exists!');
}

Log::info("Clearing table FullTextIndex.");
DB::execute('truncate table FullTextIndex');

// Build a map of stop words
$stopWordForms = array_flip(DB::getArray(
  'select distinct i.formNoAccent ' .
  'from Lexeme l, InflectedForm i ' .
  'where l.id = i.lexemeId ' .
  'and l.stopWord'));

// Build a map of inflectedForm => list of (lexemeId, inflectionId) pairs
Log::info("Building inflected form map.");
$dbResult = DB::execute("select formNoAccent, lexemeId, inflectionId from InflectedForm");
$ifMap = [];
foreach ($dbResult as $r) {
  $form = mb_strtolower($r['formNoAccent']);
  $s = isset($ifMap[$form])
     ? ($ifMap[$form] . ',')
     : '';
  $s .= $r['lexemeId'] . ',' . $r['inflectionId'];
  $ifMap[$form] = $s;
}
unset($dbResult);
Log::info("Inflected form map has %d entries.", count($ifMap));
Log::info("Memory used: %d MB", round(memory_get_usage() / 1048576, 1));

// Process definitions
$dbResult = DB::execute('select id, internalRep from Definition where status = 0');
$defsSeen = 0;
$indexSize = 0;
$fileName = tempnam(Config::get('global.tempDir'), 'index_');
$handle = fopen($fileName, 'w');
Log::info("Writing index to file $fileName.");
DebugInfo::disable();

foreach ($dbResult as $dbRow) {
  $rep = fullTextRep($dbRow[1]);
  $words = extractWords($rep);

  foreach ($words as $position => $word) {
    if (!isset($stopWordForms[$word])) {
      if (array_key_exists($word, $ifMap)) {
        $lexemeList = preg_split('/,/', $ifMap[$word]);
        for ($i = 0; $i < count($lexemeList); $i += 2) {
          fwrite($handle, $lexemeList[$i] . "\t" . $lexemeList[$i + 1] . "\t" . $dbRow[0] . "\t" . $position . "\n");
          $indexSize++;
        }
      }
    }
  }

  if (++$defsSeen % 10000 == 0) {
    $runTime = DebugInfo::getRunningTimeInMillis() / 1000;
    $speed = round($defsSeen / $runTime);
    Log::info("$defsSeen definitions indexed ($speed defs/sec). ");
  }
}
unset($dbResult);

fclose($handle);
Log::info("$defsSeen definitions indexed.");
Log::info("Index size: $indexSize entries.");

OS::executeAndAssert("chmod 666 $fileName");
Log::info("Importing file $fileName into table FullTextIndex");
DB::executeFromOS("load data local infile \"$fileName\" into table FullTextIndex");
OS::deleteFile($fileName);

if (!Lock::release(Lock::FULL_TEXT_INDEX)) {
  Log::warning('WARNING: could not release lock!');
}
Log::notice('finished; peak memory usage %d MB', round(memory_get_peak_usage() / 1048576, 1));

/***************************************************************************/

function extractWords($text) {
  $alphabet = 'abcdefghijklmnopqrstuvwxyzăâîșț';

  $text = mb_strtolower($text);
  $text = Str::removeAccents($text);

  // remove tonic accents (apostrophes not preceded by a backslash)
  $text = preg_replace("/(?<!\\\\)'/", '', $text);

  $result = [];
  $currentWord = '';
  $chars = Str::unicodeExplode($text);
  foreach ($chars as $c) {
    if (strpos($alphabet, $c) !== false) {
      $currentWord .= $c;
    } else {
      if ($currentWord) {
        $result[] = $currentWord;
      }
      $currentWord = '';
    }
  }

  if ($currentWord) {
    $result[] = $currentWord;
  }

  return $result;
}

/* Cleans up a definition's internal rep, throwing away text we shouldn't index */
function fullTextRep($s) {
  // throw away hidden text
  $s = preg_replace('/▶.*◀/sU', '', $s);

  // throw away footnotes
  $s = preg_replace('/(?<!\\\\)\{\{.*\}\}/sU', '', $s);

  return $s;
}
