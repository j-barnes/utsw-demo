<?php

namespace Drupal\utsw_migration\Plugin\migrate\process;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\Plugin\MigrateProcessInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Constructs Layouts derived from data.
 *
 * @MigrateProcessPlugin(
 *   id = "utsw_layout_plugin_id",
 *   handle_multiples = TRUE
 * )
 */
class LayoutPluginId extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The block_content entity storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockContentStorage;

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockStorage;

  /**
   * The Drupal migrate lookup service.
   *
   * @var \Drupal\migrate\MigrateLookupInterface
   */
  protected $migrateLookup;

  /**
   * The migration plugin.
   *
   * @var \Drupal\migrate\Plugin\MigrateProcessInterface
   */
  protected $migrationPlugin;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * Constructs a Block Plugin lookup service.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Plugin storage.
   * @param \Drupal\migrate\Plugin\MigrateProcessInterface $migration_plugin
   *   Migration plugin.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   UUID service.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   Block manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface|null $block_storage
   *   Block storage.
   * @param \Drupal\migrate\MigrateLookupInterface $migrate_lookup
   *   Migrate lookup.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $storage, MigrateProcessInterface $migration_plugin, UuidInterface $uuid_service, BlockManagerInterface $block_manager, EntityStorageInterface $block_storage = NULL, MigrateLookupInterface $migrate_lookup) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->blockContentStorage = $storage;
    $this->migrationPlugin = $migration_plugin;
    $this->uuidService = $uuid_service;
    $this->blockManager = $block_manager;
    $this->blockStorage = $block_storage;
    $this->migrateLookup = $migrate_lookup;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    $entity_manager = $container->get('entity_type.manager');
    $migration_configuration = [
      'migration' => [],
    ];

    // Handle any custom migrations leveraging this plugin.
    $migration_dependencies = $migration->getMigrationDependencies();
    if (isset($migration_dependencies['required'])) {
      foreach ($migration_dependencies['required'] as $dependency) {
        if (strpos($dependency, 'block') !== FALSE ||
            strpos($dependency, 'media') !== FALSE ||
            strpos($dependency, 'node') !== FALSE) {
          $migration_configuration['migration'][] = $dependency;
        }
      }
    }

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_manager->getDefinition('block_content') ? $entity_manager->getStorage('block_content') : NULL,
      $container->get('plugin.manager.migrate.process')->createInstance('migration_lookup', $migration_configuration, $migration),
      $container->get('uuid'),
      $container->get('plugin.manager.block'),
      $entity_manager->hasHandler('block', 'storage') ? $entity_manager->getStorage('block') : NULL,
      $container->get('migrate.lookup')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $sections = [];

    foreach ($value['sections'] as $section_value) {
      $section = new Section($section_value['layout_id'], $section_value['layout_settings']);
      $sections[] = $section;
      foreach ($section_value['components'] as $tmp_component) {
        switch ($tmp_component['configuration']['provider']) {
          case 'block_content':
            $block_id = $this->lookupBlock($tmp_component['configuration']['migration'], $tmp_component['configuration']['id']);
            if ($block_id) {
              $block = $this->blockContentStorage->load($block_id);
              $tmp_component['configuration']['id'] = "inline_block:{$block->bundle()}";
              $tmp_component['configuration']['status'] = TRUE;
              $tmp_component['configuration']['info'] = '';
              $tmp_component['configuration']['provider'] = 'layout_builder';
              $tmp_component['configuration']['label_display'] = '0';
              $tmp_component['configuration']['view_mode'] = 'full';
              $tmp_component['configuration']['block_revision_id'] = $block->get('revision_id')->value;
            }
            break;

          default:
            break;
        }
        $component = $this->getComponents($tmp_component);
        $section->appendComponent($component);
      }
    }

    return $sections;
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

  /**
   * Helper to get section components.
   */
  protected function getComponents($component) {
    $configuration = $component["configuration"];
    if (empty($configuration)) {
      return FALSE;
    }
    $region = $component["region"];
    $component = new SectionComponent($this->uuidService->generate(), $region, $configuration);
    return $component;
  }

  /**
   * Looks up a block from a given migration.
   *
   * @param string $migration_id
   *   The migration id to search.
   * @param string $id
   *   The source id from the migration.
   *
   * @return int
   *   The block id of the located block.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\migrate\MigrateException
   */
  public function lookupBlock($migration_id, $id) {
    $source = [$id];
    $block_ids = $this->migrateLookup->lookup($migration_id, $source);
    if (empty($block_ids)) {
      throw new MigrateException(sprintf('Unable to find related migrated block for source id %s in migration %s', $id, $migration_id), MigrationInterface::MESSAGE_WARNING);
    }
    return reset($block_ids)['id'];
  }

}
