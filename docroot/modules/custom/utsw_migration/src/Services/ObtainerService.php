<?php

namespace Drupal\utsw_migration\Services;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Service for obtaining data from a URL.
 */
class ObtainerService {

  /**
   * @var \Symfony\Component\DomCrawler\Crawler
   */
  private $crawler;

  /**
   * ObtainerService constructor.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DOM crawler object.
   */
  public function __construct(Crawler $crawler) {
    $this->crawler = $crawler;
  }

  /**
   * Create a scraper service from a URL.
   *
   * @param string $url
   *   The URL from which to obtain data.
   *
   * @return self
   *   The ObtainerService object.
   */
  public static function createFromUrl(string $url): self {
    $client = new Client();
    $response = $client->request('GET', $url);
    $html = $response->getBody()->getContents();
    $scraperService = new self(new Crawler($html));
    return $scraperService;
  }

  /**
   * Get the crawler.
   *
   * @return \Symfony\Component\DomCrawler\Crawler
   *   The DOM crawler object.
   */
  public function crawl(): Crawler {
    return $this->crawler;
  }

  /**
   * Extract text from crawler without worrying about "empty node list" error.
   *
   * @param string $selector
   *   The CSS selector to filter elements.
   *
   * @return string|null
   *   The extracted text or NULL if no elements matched the selector.
   */
  public function safeText(string $selector): ?string {
    if ($this->crawler->filter($selector)->count() == 0) {
      return NULL;
    }
    return $this->crawler->filter($selector)->text();
  }

  /**
   * Slugifies a string.
   *
   * @param string $string
   *   The string to slugify.
   *
   * @return string
   *   The slugified string.
   */
  public function slugify($string) {
    $string = preg_replace('~[^\pL\d]+~u', '-', $string);
    $string = iconv('utf-8', 'us-ascii//TRANSLIT', $string);
    $string = preg_replace('~[^-\w]+~', '', $string);
    $string = trim($string, '-');
    $string = strtolower($string);
    if (empty($string)) {
      return 'n-a';
    }
    return $string;
  }

}
