<?php

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;

try
{
    //Get configuration
    $configurationFile = __DIR__.'/../configuration/worker.json';
    if (!file_exists($configurationFile)) {
        die('Error: the configuration file "'.$configurationFile.'" does not exist');
    }

    $configuration = json_decode(file_get_contents($configurationFile), false, 512, JSON_THROW_ON_ERROR);
}
catch (\JsonException $e) {
    die('Error: the configuration file "'.$configurationFile.'" is not valid JSON');
}

//Output file name
$csvFile = __DIR__.'/../output/'.date($configuration->csvFileDateFormat).'.csv';

$listings = [];

//Optional column titles
if (!empty($configuration->columnTitles))
{
    $listings[0] = $configuration->columnTitles;
}

//Get search pages using HTML pagination values
$currentPage = 0;
$remainingPages = 1;
$offset = 0;
$totalPages = 0;

while ($remainingPages)
{
    echo "[".date($configuration->dateFormat) . "] Retrieving page " . ($currentPage+1) .
        ($totalPages ? " of " . $totalPages : "") .
        PHP_EOL;

    $client = new HttpBrowser(HttpClient::create(['timeout' => $configuration->httpTimeout]));
    $searchCrawler = $client->request('GET', $configuration->baseUrl . $configuration->searchUrl . $offset);

    $links = $searchCrawler->filter($configuration->selectors->searchTitle)->each(
        function ($node){
            return $node->closest('a')->attr('href');
        }
    );

    //Get listings
    foreach($links as $k => $link)
    {
        echo "[".date($configuration->dateFormat) . "] Retrieving listing " . ($k+1) . " of " . sizeof($links) . PHP_EOL;

        $client = new HttpBrowser(HttpClient::create(['timeout' => $configuration->httpTimeout]));
        $listingCrawler = $client->request('GET', $configuration->baseUrl . $link);

        $title =        $listingCrawler->filter($configuration->selectors->listingTitle)->text();
        $number =       $listingCrawler->filter($configuration->selectors->listingNumber)->text();
        $bedrooms =     $listingCrawler->filter($configuration->selectors->listingBedrooms)->eq(0)->text();
        $bathrooms =    $listingCrawler->filter($configuration->selectors->listingBathrooms)->eq(1)->text();

        //multiple
        $phones = $listingCrawler->filter($configuration->selectors->listingPhones)->each(
            function ($node){
                return $node->text();
            }
        ) ?? '';
        
        //optional
        $price = $listingCrawler->filter($configuration->selectors->listingPrice)->each(
            function ($node){
                return $node->text();
            }
        )[0] ?? '';

        //optional
        $description = $listingCrawler->filter($configuration->selectors->description)->each(
            function ($node){
                return $node->text();
            }
        )[0] ?? '';

        //optional
        $moreDescription = $listingCrawler->filter($configuration->selectors->moreDescription)->each(
            function ($node){
                return $node->text();
            }
        )[0] ?? '';

        $listings[] = [
            trim($title),
            preg_replace('~\D~', '', $number),
            preg_replace('~\D~', '', $bedrooms),
            trim(preg_replace('/[^0-9 \/]/', '', $bathrooms)),
            trim($price),
            trim(preg_replace('/[^0-9 ]/', '', implode(' ', $phones))),
            trim($description . $moreDescription)
        ];

        if ($configuration->sleepBetweenListingsInSeconds)
        {
            echo "[".date($configuration->dateFormat) . "] Sleeping for " . $configuration->sleepBetweenListingsInSeconds . " seconds" . PHP_EOL;
            sleep($configuration->sleepBetweenListingsInSeconds);
        }
    }

    $pagesCurrentSizeTotal = $searchCrawler->filter($configuration->selectors->searchPagesCounterSelector)->first()->each(
        function ($node) use ($configuration) {
            preg_match($configuration->selectors->searchPagesCounterRegex, $node->text(), $match);
            return isset($match[3]) ? $match : [];
        }
    )[0];

    //Get pagination values
    $resultsPerPage = $pagesCurrentSizeTotal[2] - ($pagesCurrentSizeTotal[1]-1);
    $currentPage = floor($pagesCurrentSizeTotal[2] / $resultsPerPage);
    $totalPages = ceil($pagesCurrentSizeTotal[3] / $resultsPerPage);
    $remainingPages = $totalPages && $currentPage ? $totalPages - $currentPage : 0;
    $offset += $resultsPerPage;

    if ($configuration->sleepBetweenSearchPagesInSeconds)
    {
        echo "[".date($configuration->dateFormat) . "] Sleeping for " . $configuration->sleepBetweenSearchPagesInSeconds . " seconds" . PHP_EOL;
        sleep($configuration->sleepBetweenSearchPagesInSeconds);
    }
}

echo "[".date($configuration->dateFormat) . "] Writing ".sizeof($links). " line(s) to file " . $csvFile . PHP_EOL;

//Write CSV file
$fp = fopen($csvFile, 'wb');
foreach ($listings as $listing)
{
    fputcsv($fp, $listing, $configuration->csvSeparator, $configuration->csvStringEnclosure);
}

echo "[".date($configuration->dateFormat) . "] " .
    (fclose($fp) ? "File written successfully" : "Error writing file") .
    PHP_EOL;