<?php
namespace MyCrawler;

use MyCrawler\Service\HttpClient;
use MyCrawler\Service\HtmlParser;
use MyCrawler\Service\DatabaseManager;
use MyCrawler\Logger;
use MyCrawler\Exception\NetworkException;
use MyCrawler\Exception\ParseException;

class Crawler
{
    private HttpClient $httpClient;
    private HtmlParser $htmlParser;
    private DatabaseManager $dbManager;
    private Logger $logger;

    public function __construct(HttpClient $httpClient, HtmlParser $htmlParser, DatabaseManager $dbManager, Logger $logger)
    {
        $this->httpClient = $httpClient;
        $this->htmlParser = $htmlParser;
        $this->dbManager = $dbManager;
        $this->logger = $logger;
    }

    public function crawlArticle(string $url):?array
    {
        try {
            $this->logger->info("Gaunamas URL: ". $url);
            $htmlContent = $this->httpClient->fetch($url);

            if (!$htmlContent) {
                $this->logger->warning("Nėra turinio, gauto URL: ". $url);
                return null;
            }

            $this->logger->info("Analizuojamas HTML turinys iš: ". $url);
            $articleContent = $this->htmlParser->parseArticle($htmlContent);

            if (empty($articleContent)) {
                $this->logger->warning("Nerasta straipsnio turinio URL: ". $url);
                return null;
            }

            // Šiai konkrečiai užklausai, tarkime, kad analizuojamas tik vienas pagrindinis straipsnis arba sujungiami
            // Jei yra kelios <article> žymės, galbūt norėsite jas tvarkyti kaip eilučių masyvą.
            $combinedContent = implode("\n\n", $articleContent); // Sujungti, jei rasta kelios

            return ['url' => $url, 'content' => $combinedContent];

        } catch (NetworkException $e) {
            $this->logger->error("Nepavyko gauti URL ". $url. ": ". $e->getMessage());
            return null;
        } catch (ParseException $e) {
            $this->logger->error("Nepavyko analizuoti HTML iš URL ". $url. ": ". $e->getMessage());
            return null;
        } catch (\Exception $e) {
            $this->logger->error("Įvyko netikėta klaida nuskaitymo metu ". $url. ": ". $e->getMessage());
            return null;
        }
    }
}
