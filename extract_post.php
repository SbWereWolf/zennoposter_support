<?php
/**
 * Created by PhpStorm.
 * User: SbWereWolf
 * Date: 2017-06-17
 * Time: 18:56
 */

$dbConnectionAttributes = include('configuration/db_write.php');
$password = $dbConnectionAttributes['password'];
$login = $dbConnectionAttributes['login'];

$driver = $dbConnectionAttributes['driver'];
$host = $dbConnectionAttributes['host'];
$database = $dbConnectionAttributes['db_name'];

$dataSourceName = "$driver:host=$host;dbname=$database";

$dbConnection = new PDO($dataSourceName,$login,$password);

$getAccount = "
SELECT
  a.id    AS id,
  a.login AS login
FROM
  tag t
  JOIN tag_account ta
    ON t.id = ta.tag_id
  JOIN account a
    ON ta.account_id = a.id
WHERE
  t.code = 'RELIABLE';
";

const FETCH_MODE = PDO::FETCH_ASSOC;
$accountCollection = $dbConnection->query($getAccount,FETCH_MODE)->fetchAll();

$accountCount = count($accountCollection);

$getPost = "
SELECT
  p.id as post_id,
  p.title as post_title,
  p.body as post_body
FROM
  post p
  LEFT JOIN account_post ap
    ON p.id = ap.post_id
WHERE
  p.is_hidden IS TRUE
  AND ap.id IS NULL
  LIMIT $accountCount;
";

$postCollection = $dbConnection->query($getPost,FETCH_MODE)->fetchAll();


const POST_OUTPUT_PATH = __DIR__.'/post/';

$accountIndex = 0;

foreach ($postCollection as $postEntity){

    $titleCandidate = $postEntity['post_title'];

    $lineBreakPosition = mb_strpos($titleCandidate,"\n");

    $title='';
    if( $lineBreakPosition !== false && $lineBreakPosition > 1){
        $title = mb_substr($titleCandidate,0,$lineBreakPosition-1);
    }

    if($lineBreakPosition === false){
        $title = $titleCandidate;
    }

    $login = $accountCollection[$accountIndex]['login'];

    $titleFilename = POST_OUTPUT_PATH.$login.'_title.txt';

    $titleFile = fopen($titleFilename, 'w');

    if($titleFile){
        fwrite($titleFile, $title);
        fclose($titleFile);

    }

    $postBody = $postEntity['post_body'];

    $postFilename = POST_OUTPUT_PATH.$login.'_post.txt';

    $postFile = fopen($postFilename, 'w');

    if($postFile){
        fwrite($postFile, $postBody);
        fclose($postFile);
    }

    $accountId = $accountCollection[$accountIndex]['id'];
    $postId = $postEntity['post_id'];

    $setPostToAccount = "
INSERT INTO account_post (account_id, post_id) 
VALUES($accountId,$postId);
";

    $isSuccess = $dbConnection->exec($setPostToAccount);

    $accountIndex++;

}
