<?php
// bin/console.php
require_once __DIR__. '/../vendor/autoload.php';

use MyCrawler\Service\HttpClient;
use MyCrawler\Service\HtmlParser;
use MyCrawler\Service\DatabaseManager;
use MyCrawler\Crawler;
use MyCrawler\Logger; // Tarkime, paprastas Logger apvalkalas Monologui
use MyCrawler\Exception\NetworkException;
use MyCrawler\Exception\ParseException;
use MyCrawler\Exception\DatabaseException;

// Įkelti duomenų bazės konfigūraciją
$dbConfig = require_once __DIR__. '/../config/database.php';

// Inicijuoti Logger (pvz., Monolog)
$logger = new Logger('Crawler'); // Tarkime, Logger klasė naudoja Monolog

try {
    // 1. Inicijuoti paslaugas
    $httpClient = new HttpClient($logger); // HttpClient tvarko užklausų ribojimą viduje
    $htmlParser = new HtmlParser($logger);
    $dbManager = new DatabaseManager($dbConfig, $logger);

    // 2. Inicijuoti pagrindinį skaitytuvą
    $crawler = new Crawler($httpClient, $htmlParser, $dbManager, $logger);

    // 3. Nustatyti tikslinį URL (hipotetinis, NE tikrasis Google News dėl paslaugų teikimo sąlygų)
    // Demonstracijai naudokite testavimo URL arba vietinį HTML failą.
    $targetUrl = "https://example.com/news-article-page"; // PAKEISTI į galiojantį, nuskaitytiną URL testavimui
    $articleId = 1; // Demonstracijai, tarkime, kad gauname konkretų straipsnį

    $logger->info("Pradedamas skaitymas URL: ". $targetUrl);

    // 4. Vykdyti skaitymo procesą
    $crawledData = $crawler->crawlArticle($targetUrl);

    if ($crawledData) {
        // 5. Saugoti duomenis
        $dbManager->beginTransaction();
        try {
            $inserted = $dbManager->insertArticle($targetUrl, $crawledData['content']);
            if ($inserted) {
                $logger->info("Straipsnio turinys iš ". $targetUrl. " sėkmingai išsaugotas DB.");
                $dbManager->commit();
            } else {
                $logger->warning("Nepavyko įterpti straipsnio turinio iš ". $targetUrl. ".");
                $dbManager->rollback();
            }
        } catch (DatabaseException $e) {
            $dbManager->rollback();
            $logger->error("Duomenų bazės transakcija nepavyko ". $targetUrl. ": ". $e->getMessage());
        }
    } else {
        $logger->warning("Nėra turinio, išgauto iš ". $targetUrl. ".");
    }

    $logger->info("Skaitymo procesas baigtas.");

} catch (NetworkException $e) {
    $logger->critical("Tinklo klaida skaitymo metu: ". $e->getMessage());
} catch (ParseException $e) {
    $logger->error("Analizavimo klaida skaitymo metu: ". $e->getMessage());
} catch (DatabaseException $e) {
    $logger->critical("Kritinė duomenų bazės klaida: ". $e->getMessage());
} catch (\Exception $e) {
    $logger->critical("Įvyko netikėta klaida: ". $e->getMessage());
}
