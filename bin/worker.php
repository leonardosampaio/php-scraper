<?php

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

/**
 * TODO these values will come from a json configuration file
 */
//Main url
$url = 'https://www.clasificadosonline.com/UDREListing.asp?RESPueblos=San+Juan+-+Condado-Miramar&Category=Apartamento&Bedrooms=%25&LowPrice=0&HighPrice=999999999&IncPrecio=1&Area=&Repo=Repo&BtnSearchListing=Listing&redirecturl=%2Fudrelistingmap.asp&Opt=Opt&offset=';
//Listing title
$titleSelector = '.link-blue-color';
//Listing pagination
$pagesCounterSelector = 'tr td .Tahoma14blacknound';
//Pagination format
$pagesCounterRegex = '/([0-9]*) al ([0-9]*) de ([0-9]*)/';
//Sleep between requests for X seconds
$sleepInSeconds = 0;
//Output file format
$csvFile = __DIR__.'/../output/'.date('Ymd-His').'.csv';
//Column separator
$csvSeparator = ';';
//String separator
$csvStringSeparator = '"';
//Replace separators in lines with this
$csvSeparatorAlternative = "'";

//Get search pages
$currentPage = 0;
$remainingPages = 1;
$offset = 0;
$titles = [];
while ($remainingPages)
{
    echo "[".date('Y-m-d H:i:s') . "] Retrieving page " . ($currentPage+1) . PHP_EOL;

    $client = new HttpBrowser(HttpClient::create(['timeout' => 60]));
    $crawler = $client->request('GET', $url . $offset);

    $titles = array_merge($titles, $crawler->filter($titleSelector)->each(
        function ($node) use ($csvSeparator,$csvSeparatorAlternative) {
            return str_replace($csvSeparator, $csvSeparatorAlternative, $node->text());
        }
    ));

    //TODO Get individual links and scrape them
    
    $pagesCurrentSizeTotal = $crawler->filter($pagesCounterSelector)->first()->each(
        function ($node) use ($pagesCounterRegex) {
            preg_match($pagesCounterRegex, $node->text(), $match);
            return isset($match[3]) ? $match : [];
        }
    )[0];

    $resultsPerPage = $pagesCurrentSizeTotal[2] - ($pagesCurrentSizeTotal[1]-1);
    $currentPage = floor($pagesCurrentSizeTotal[2] / $resultsPerPage);
    $totalPages = ceil($pagesCurrentSizeTotal[3] / $resultsPerPage);
    $remainingPages = $totalPages && $currentPage ? $totalPages - $currentPage : 0;
    $offset += $resultsPerPage;

    if ($sleepInSeconds)
    {
        sleep($sleepInSeconds);
    }
}

echo "[".date('Y-m-d H:i:s') . "] Writing ".sizeof($titles). " lines to file " . $csvFile . PHP_EOL;

//Write CSV file
$fp = fopen($csvFile, 'wb');
foreach ($titles as $line)
{
    $val = explode($csvStringSeparator . $csvSeparator . $csvStringSeparator, $line);
    fputcsv($fp, $val);
}
fclose($fp);