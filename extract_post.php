<?php
/**
 * Created by PhpStorm.
 * User: SbWereWolf
 * Date: 2017-06-17
 * Time: 18:56
 */

/**
 * @param $titleCandidate
 * @return string
 */
function getTextFirstLine($titleCandidate): string
{
    $lineBreakPosition = mb_strpos($titleCandidate, "\n");

    $title = '';
    if ($lineBreakPosition !== false && $lineBreakPosition > 1) {
        $title = mb_substr($titleCandidate, 0, $lineBreakPosition - 1);
    }

    if ($lineBreakPosition === false) {
        $title = $titleCandidate;
    }
    return $title;
}

/**
 * @param $login
 * @param $title
 * @param $fileSuffix
 * @return bool
 */
function writeTextToFile(string $login, string $title, string $fileSuffix): bool
{
    $result = false;

    $titleFilename = POST_OUTPUT_PATH . $login . $fileSuffix;

    $titleFile = fopen($titleFilename, 'w');

    if ($titleFile) {
        $result = fwrite($titleFile, $title);
        fclose($titleFile);

        echo $titleFilename . " , bytes $result written \n";

        $result = $result > 0 ? true : false;

    }

    return $result;
}

/**
 * @param $accountId
 * @param $postId
 * @param $dbConnection
 * @return bool
 */
function linkPostToAccount($accountId, $postId, \PDO $dbConnection): bool
{
    $setPostToAccount = "
INSERT INTO account_post (account_id, post_id) 
VALUES($accountId,$postId);
";

    $isSuccess = $dbConnection->exec($setPostToAccount);

    return $isSuccess;
}

/**
 * @param $postId
 * @param $dbConnection
 * @return bool
 */
function setPostAsVisible($postId, \PDO $dbConnection): bool
{
    $setVisibleForPost = "
UPDATE post
 set is_hidden = false
 WHERE 
 id = $postId
;
";

    $isSuccess = $dbConnection->exec($setVisibleForPost);

    return $isSuccess;
}

$dbConnectionAttributes = include('configuration/db_write.php');
$password = $dbConnectionAttributes['password'];
$login = $dbConnectionAttributes['login'];

$driver = $dbConnectionAttributes['driver'];
$host = $dbConnectionAttributes['host'];
$database = $dbConnectionAttributes['db_name'];

$dataSourceName = "$driver:host=$host;dbname=$database";

$dbConnection = new PDO($dataSourceName, $login, $password);

$getAccount = "
SELECT
  a.login AS login,
  a.id    AS id
FROM
  account a
WHERE
  EXISTS(
    SELECT NULL FROM tag t JOIN tag_account ta ON t.id = ta.tag_id WHERE ta.account_id = a.id AND t.code = 'RELIABLE'
  )
AND
  EXISTS(
    SELECT NULL FROM tag t JOIN tag_account ta ON t.id = ta.tag_id WHERE ta.account_id = a.id AND t.code = 'PROMO_ME'
  )
;
";

const FETCH_MODE = PDO::FETCH_ASSOC;
$accountCollection = $dbConnection->query($getAccount, FETCH_MODE)->fetchAll();

$accountCount = count($accountCollection);

$getPost = "
SELECT
  p.id as post_id,
  p.title as post_title,
  p.body as post_body,
  p.bulk_tags as post_tags
FROM
  post p
  LEFT JOIN account_post ap
    ON p.id = ap.post_id
WHERE
  p.is_hidden IS TRUE
  AND ap.id IS NULL
  LIMIT $accountCount;
";

$postCollection = $dbConnection->query($getPost, FETCH_MODE)->fetchAll();


const POST_OUTPUT_PATH = __DIR__ . '/post/';
const TITLE_SUFFIX = '_title.txt';
const POST_SUFFIX = '_body.txt';
const TAG_SUFFIX = '_tag.txt';

mb_internal_encoding('UTF-8');

$accountIndex = 0;

foreach ($postCollection as $postEntity) {

    $login = $accountCollection[$accountIndex]['login'];

    $titleCandidate = $postEntity['post_title'];
    $postBody = $postEntity['post_body'];
    $postTag = $postEntity['post_tags'];

    $title = getTextFirstLine($titleCandidate);

    $isSuccess = !empty($title) && !empty($postBody) && !empty($postTag);

    if ($isSuccess) {
        writeTextToFile($login, $title, TITLE_SUFFIX);
        writeTextToFile($login, $postBody, POST_SUFFIX);
        writeTextToFile($login, $postTag, TAG_SUFFIX);
    }

    $accountId = $accountCollection[$accountIndex]['id'];
    $postId = $postEntity['post_id'];

    linkPostToAccount($accountId, $postId, $dbConnection);

    setPostAsVisible($postId, $dbConnection);

    $accountIndex++;

}
