<?php

namespace Drupal\utsw_migration\Plugin\migrate\process;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\layout_builder\Section;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes default layouts for Layout Builder.
 *
 * This plugin is specifically for adding the default layout to the migration.
 *
 * Available configuration keys
 * - bundle: The node bundle the migration is acting on.
 *
 * @MigrateProcessPlugin(
 *   id = "default_layout"
 * )
 */
class DefaultLayout extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The immutable config factory service provided by Drupal core.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $pluginId, $pluginDefinition, MigrationInterface $migration, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $migration,
      $container->get('config.factory'),
    );
  }

  /**
   * Transform for DefaultLayout.
   *
   * @param mixed $value
   *   The values from the migration source.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The current migration executable.
   * @param \Drupal\migrate\Row $row
   *   The current migration row.
   * @param string $destination_property
   *   The destination property.
   *
   * @return \Drupal\layout_builder\Section[]|mixed
   *   An array of layout builder section or the values from the source field
   *   unchanged.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $components = [];
    $this->flatten($value, $components);
    $bundle = $this->configuration['bundle'];
    $section_label = $this->configuration['section'];
    $display = $this->configuration['display'] ?? 'default';
    if ($bundle) {
      $sections = $this->loadDefaultSections($bundle, $display);
      if (!empty($sections)) {

        $section = NULL;
        foreach ($sections as $sec) {
          if ($sec->getLayoutSettings()['label'] == $section_label) {
            $section = $sec;
          }
        }

        if ($section) {
          foreach ($components as $component) {
            $section->appendComponent($component);
          }
        }

        return $sections;
      }
      else {
        return NULL;
      }
    }
    return $value;
  }


  /**
   * Flattens a multi-dimensional array using recursion.
   *
   * @param array $elements
   *   The elements to flatten.
   * @param array $new_layout
   *   The resulting flattened array.
   */
  private function flatten(array $elements, array &$new_array) {
    foreach ($elements as $element) {
      if ($element !== NULL) {
        if (!is_array($element)) {
          $new_array[] = $element;
        }
        else {
          $this->flatten($element, $new_array);
        }
      }
    }
  }


  /**
   * Loads default layout builder sections for a content type.
   *
   * @param string $bundle
   *   The content type to load defaults from.
   *
   * @param string $display
   *   The display view.
   *
   * @return \Drupal\layout_builder\Section[]
   *   An array of the default layout builder section objects loaded from
   *   config.
   */
  protected function loadDefaultSections($bundle, $display = 'default') {
    $config = $this->configFactory->get("core.entity_view_display.node.{$bundle}.{$display}");
    $sections_array = $config->get('third_party_settings.layout_builder.sections');
    $sections = [];

    if (!empty($sections_array)) {
      foreach ($sections_array as $section_data) {
        $sections[] = Section::fromArray($section_data);
      }
    }
    return $sections;
  }

}
