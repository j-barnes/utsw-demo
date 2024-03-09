<?php

namespace Drupal\utsw_migration\Commands;

use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Drush\Commands\DrushCommands;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DomCrawler\Crawler;
use Drupal\block_content\Entity\BlockContent;
use Drupal\utsw_migration\Services\ObtainerService;

/**
 * Defines a Drush command for the UTSW Migration module.
 */
final class ObtainerCommands extends DrushCommands {

  /**
   * Executes the obtainer:fetch command.
   *
   * @command obtainer:fetch
   * @aliases obt-fetch
   * @usage obtainer:fetch
   *   Fetches data from specified URLs.
   */
  public function fetch(): void {
    $url = 'https://www.utsouthwestern.edu';
    $scraper = ObtainerService::createFromUrl($url);
    $page = [
      'page_title' => $scraper->crawl()->filter('title')->text(),
      'pid' => $scraper->slugify($url),
      'url' => $url,
      'components' => [],
    ];

    // Found cards.
    if ($scraper->crawl()->filter('.article__main .cards')->count()) {

      // Check for layouts.
      $scraper->crawl()->filter('.article__main .cards .card')->each(function (Crawler $card) use (&$page) {
        $row = new ObtainerService($card);

        $title = $row->safeText('.card__title');
        $title_link = $card->filter('.card__title a')->count() ? $card->filter('.card__title a')->attr('href') : '';
        $media_image_url = $card->filter('.card__media img')->count() ? $page['url'] . $card->filter('.card__media img')->attr('src') : '';
        $media_image_alt = $card->filter('.card__media img')->count() ? $card->filter('.card__media img')->attr('alt') : '';

        $page['components'][] = [
          'bid' => $page['pid'] . '|block|icon_link|' . $row->slugify($title),
          'title' => $title,
          'title_link' => $title_link,
          'media_image_url' => $media_image_url,
          'media_image_alt' => $media_image_alt,
          'type' => 'icon_link',
        ];

        // Output card array.
        $this->output()->writeln(print_r($page, TRUE));
      });

      // Finished.
      $module_path = \Drupal::moduleHandler()->getModule('utsw_migration')->getPath();
      file_put_contents($module_path . '/artifacts/example.json', json_encode(['pages' => [$page]], JSON_PRETTY_PRINT));
    }
    else {
      $this->output()->writeln("No data found for {$url}");
    }
  }

}
