# PHP scraper

## Dependencies

`php 7.4+`

`php openssl module`

## Configuration

Edit the `configuration/worker.json` file to change the following settings:

| Key | Description |
| ----------- | ----------- |
| baseUrl | main domain |
| searchUrl | search page, where the pagination occurs |
| selectors | CSS selectors that are used by each element on the page |
| columnTitles | optional, column titles that will be output at the first line of the CSV |
| sleepBetweenSearchPagesInSeconds | optional, number of seconds between each search page |
| sleepBetweenListingsInSeconds | optional, number of seconds between each listing page |
| csvSeparator | character that separates columns |
| csvStringEnclosure | character that enclosures string values on columns |
| httpTimeout | time in seconds to wait for a server response |
| dateFormat | date format used to print messages |
| csvFileDateFormat | date format used to generate the CSV file name |


## Instructions

1. Run with `/path/to/php bin/worker.php`, CSV files with be put in the `output` folder