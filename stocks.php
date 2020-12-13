<?php
/*****************************************************************
 **                                                             **
 **                           CONFIG                            **
 **                                                             **
 *****************************************************************/

require('includes/simple_html_dom.php');
require('scrape/includes/mysql_details.php');
$matchesArr = array();
$conn       = new mysqli($servername, $username, $password, 'momtrack_momproducts');
mysqli_set_charset($conn, 'utf8mb4');
$stocks     = getStocks($conn);

/*****************************************************************
 **                                                             **
 **                       FETCH POSTS                           **
 **                                                             **
 *****************************************************************/

$matchesArr = getPosts($conn,"https://old.reddit.com/r/wallstreetbets/new/",1,$matchesArr,$stocks,'20');
$matchesArr = getPosts($conn,"https://old.reddit.com/r/stocks/new/",1,$matchesArr,$stocks,'15');
$matchesArr = getPosts($conn,"https://old.reddit.com/r/StockMarket/new/",1,$matchesArr,$stocks,'15');
$matchesArr = getPosts($conn,"https://old.reddit.com/r/investing/new/",1,$matchesArr,$stocks,'5');
printMatchCount($matchesArr,$stocks);

/*****************************************************************
 **                                                             **
 **                           MAIN                              **
 **                                                             **
 *****************************************************************/

function getPosts($conn,$startURL,$pageCount,$matchesArr,$stocks,$pages)
{
    $html  = createDOM($startURL);
    $posts = getRedditPosts($html);
    $matches = getAndPrintMatches($posts,$stocks);
    $matchesArr = createMatchArr($matches,$matchesArr);

    // Go to next page (recursive call)
    if ($pageCount < $pages) {
        $pageCount++;
        if (!empty($html)) $matchesArr = getPosts($conn,$html->find('.next-button',0)->find('a', 0)->href,$pageCount,$matchesArr,$stocks,$pages);
    }

    usort($matchesArr, 'compareCount');
    return $matchesArr;
}

/*****************************************************************
 **                                                             **
 **                          HELPERS                            **
 **                                                             **
 *****************************************************************/

function getAndPrintMatches($posts,$stocks)
{
    $count = count($posts);
    $matches = array();
    $matchCount = 0;
    for ($i = 0; $i < $count; $i++) {
        foreach ($stocks as $stock) {
            $titlePieces = explode(" ", $posts[$i]['title']);
            foreach ($titlePieces as $titlePiece) {
                if ($titlePiece == $stock['symbol']) {
                    $matches[$matchCount]['symbol'] = $stock['symbol'];
                    $matchCount++;
                    echo "<pre>".$posts[$i]['title'] . "<br><span style='color:green'>Match found: <b>".$stock['symbol']."</b> (".$stock['name'].")</span><br></pre>";
                }
            }
        }
    }
    return $matches;
}

function createMatchArr($matches,$matchesArr)
{
    $matchCount = count($matchesArr);
    foreach ($matches as $match) {
        $matchFound = false;
        $key = 0;
        foreach ($matchesArr as $existingMatch) {
            if ($match['symbol'] == $existingMatch['symbol']) {
                $matchFound = true;
                $matchesArr[$key]['count']++;
            }
            $key++;
        }
        if (!$matchFound) {
            $matchesArr[$matchCount]['symbol'] = $match['symbol'];
            $matchesArr[$matchCount]['count'] = 1;
        }
        $matchCount = count($matchesArr);
    }
    return $matchesArr;
}

function printMatchCount($matchesArr,$stocks)
{
    echo "<pre>";
        foreach ($matchesArr as $match) {
            if ($match['count']<3)
                break;
            echo $match['count'] . "\t" . $match['symbol'] . "\t" . getCompanyBySymbol($match['symbol'], $stocks) . "<br>";
        }
    echo "</pre>";
}

function getRedditPosts($html)
{
    $posts = array();
    $count = 0;
    // Get data
    foreach ($html->find('.thing') as $element)
    {
        $posts[$count]['title'] = trim($element->find('.title', 0)->find('a', 0)->innertext);
        $posts[$count]['title'] = preg_replace('/[^A-Za-z0-9\ ]/', '', $posts[$count]['title']);
        $posts[$count]['date']  = $element->find('.tagline', 0)->find('time', 0)->attr['title'];
        $count++;
    }
    return $posts;
}

function compareCount($a, $b)
{
    return $b['count']-$a['count'];
}

function createDOM($startURL)
{
    $html = new simple_html_dom();
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $startURL);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.A.B.C Safari/525.13");
    $str = curl_exec($ch);
    curl_close($ch);
    $html->load($str);
    return $html;
}

function getStocks($conn)
{
    return mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM stocks WHERE LENGTH(symbol)>1 AND symbol!='DD' AND symbol!='YOLO' AND symbol!='MOON' AND symbol!='IPO'"), MYSQLI_ASSOC);
}

function getCompanyBySymbol($symbol,$stocks)
{
    foreach($stocks as $stock)
        if ($symbol == $stock['symbol'])
            return $stock['name'];
}

function printStocks($stocks)
{
    $sCount = count($stocks);
    echo "<pre>";
    for ($i=0; $i<$sCount; $i++)
        echo "<br>".$i."\t\t".$stocks[$i]['symbol'];
    echo "</pre>";
}

?>
