<?php

namespace Drupal\utsw_migration\Plugin\migrate\process;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\SectionComponent;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Block Layout process plugin.
 *
 * Available configuration keys:
 *   - source: The source field containing paragraphs.
 *
 * @code
 * layout_builder__layout:
 *   plugin: layout_builder_layout
 *   source: field_paragraphs
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "block_layout"
 * )
 */
class BlockLayout extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * The uuid service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Block content Entity storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $blockContentStorage;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $pluginId, $pluginDefinition, MigrationInterface $migration, UuidInterface $uuid, Connection $db, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->uuid = $uuid;
    $this->db = $db;
    $this->entityTypeManager = $entityTypeManager;
    $this->blockContentStorage = $entityTypeManager->getStorage('block_content');
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
      $container->get('uuid'),
      $container->get('database'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Transform block_id source values into a Layout Builder sections.
   *
   * @param mixed $value
   *   The value to be transformed.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process. Normally, just transforming the value
   *   is adequate but very rarely you might need to change two columns at the
   *   same time or something like that.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *   with the $row above.
   *
   * @return \Drupal\layout_builder\Section
   *   A Layout Builder Section object populated with Section Components.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\migrate\MigrateException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!$value) {
      return NULL;
    }
    if (!isset($this->delta)) {
      $this->delta = 0;
    }

    $region = $this->configuration['region'];
    $component = $this->createComponent($value, $this->delta++, $region);

    if (!$component) {
      return NULL;
    }

    return $component;
  }


  /**
   * Creates a component from a block_id.
   *
   * @param string $block_id
   *   The custom block id.
   * @param string $delta
   *   Block delta in section.
   * @param string $region
   *   The region the component belongs within.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   A Layout Builder SectionComponent.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\migrate\MigrateException
   */
  public function createComponent($block_id,  $delta, $region = 'content') {
    $query = $this->db->select('block_content_field_data', 'b')
      ->fields('b', ['type'])
      ->condition('b.id', $block_id, '=');
    $block_type = $query->execute()->fetchField();
    if (!$block_type) {
      return NULL;
    }
    // Get the latest revision id for the block.
    $block_revision_id = $this->blockContentStorage->getLatestRevisionId($block_id);

    // Create a new component from the block.
    return $this->createSectionComponent($block_revision_id, $block_type, $delta, $region);
  }

  /**
   * Creates a layout builder section component.
   *
   * @param int|string $block_latest_revision_id
   *   The numeric block content revision id.
   * @param string $block_type
   *   The block type machine name to embed as an inline block for.
   * @param int $weight
   *   The weight of the component.
   * @param string $region
   *   The region of the layout the component will reside in.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   Returns the layout builder section component that gets added.
   */
  public function createSectionComponent($block_latest_revision_id, $block_type, $weight = 0, $region = 'content') {
    return SectionComponent::fromArray([
      'uuid' => $this->uuid->generate(),
      'region' => $region,
      'configuration' =>
        [
          'id' => "inline_block:{$block_type}",
          'label' => 'Layout Builder Inline Block',
          'provider' => 'layout_builder',
          'label_display' => '0',
          'view_mode' => 'full',
          'block_revision_id' => $block_latest_revision_id,
          'block_serialized' => NULL,
          'context_mapping' => [],
        ],
      'additional' => [],
      'weight' => $weight,
    ]);
  }
}
