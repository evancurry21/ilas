<?php

namespace Drupal\ilas_civicrm_sync\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Service for synchronizing Drupal taxonomies with CiviCRM tags.
 */
class TaxonomySyncService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a TaxonomySyncService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('ilas_civicrm_sync');
  }

  /**
   * Sync all terms from a vocabulary to CiviCRM tags.
   *
   * @param string $vocabulary_id
   *   The vocabulary machine name.
   * @param string $tag_set_name
   *   The CiviCRM tag set name (optional).
   *
   * @return array
   *   Array of synced tag IDs.
   */
  public function syncVocabularyToTags($vocabulary_id, $tag_set_name = NULL) {
    $synced_tags = [];
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Create or get tag set if specified
      $parent_id = NULL;
      if ($tag_set_name) {
        $parent_id = $this->getOrCreateTagSet($tag_set_name);
      }
      
      // Load all terms from the vocabulary
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vocabulary_id);
      
      foreach ($terms as $term) {
        $tag_id = $this->syncTermToTag($term, $parent_id);
        if ($tag_id) {
          $synced_tags[] = $tag_id;
        }
      }
      
      $this->logger->info('Synced @count terms from vocabulary @vid to CiviCRM tags', [
        '@count' => count($synced_tags),
        '@vid' => $vocabulary_id,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to sync vocabulary to tags: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
    
    return $synced_tags;
  }

  /**
   * Sync a single term to a CiviCRM tag.
   *
   * @param object $term
   *   The term object from loadTree().
   * @param int|null $parent_id
   *   The parent tag ID in CiviCRM.
   *
   * @return int|null
   *   The CiviCRM tag ID or NULL on failure.
   */
  protected function syncTermToTag($term, $parent_id = NULL) {
    try {
      // Check if tag already exists
      $existing = civicrm_api3('Tag', 'get', [
        'name' => 'drupal_term_' . $term->tid,
        'used_for' => 'civicrm_contact',
      ]);
      
      $params = [
        'name' => 'drupal_term_' . $term->tid,
        'label' => $term->name,
        'description' => 'Synced from Drupal taxonomy term',
        'used_for' => 'civicrm_contact',
        'is_selectable' => 1,
      ];
      
      if ($parent_id) {
        $params['parent_id'] = $parent_id;
      }
      
      if ($existing['count'] > 0) {
        $params['id'] = reset($existing['values'])['id'];
      }
      
      $result = civicrm_api3('Tag', 'create', $params);
      
      return $result['id'] ?? NULL;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to sync term @tid: @error', [
        '@tid' => $term->tid,
        '@error' => $e->getMessage(),
      ]);
    }
    
    return NULL;
  }

  /**
   * Get or create a tag set (parent tag).
   *
   * @param string $tag_set_name
   *   The tag set name.
   *
   * @return int|null
   *   The tag ID or NULL on failure.
   */
  protected function getOrCreateTagSet($tag_set_name) {
    try {
      $existing = civicrm_api3('Tag', 'get', [
        'name' => 'tagset_' . strtolower(str_replace(' ', '_', $tag_set_name)),
        'used_for' => 'civicrm_contact',
      ]);
      
      if ($existing['count'] > 0) {
        return reset($existing['values'])['id'];
      }
      
      $result = civicrm_api3('Tag', 'create', [
        'name' => 'tagset_' . strtolower(str_replace(' ', '_', $tag_set_name)),
        'label' => $tag_set_name,
        'description' => 'Tag set for ' . $tag_set_name,
        'used_for' => 'civicrm_contact',
        'is_tagset' => 1,
      ]);
      
      return $result['id'] ?? NULL;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create tag set: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
    
    return NULL;
  }

  /**
   * Apply tags to a contact based on content relationships.
   *
   * @param int $contact_id
   *   The CiviCRM contact ID.
   * @param array $term_ids
   *   Array of Drupal term IDs.
   */
  public function applyTagsToContact($contact_id, array $term_ids) {
    try {
      civicrm_initialize();
      
      foreach ($term_ids as $term_id) {
        // Get the CiviCRM tag ID for this term
        $tag_result = civicrm_api3('Tag', 'get', [
          'name' => 'drupal_term_' . $term_id,
        ]);
        
        if ($tag_result['count'] > 0) {
          $tag_id = reset($tag_result['values'])['id'];
          
          // Apply tag to contact
          civicrm_api3('EntityTag', 'create', [
            'entity_table' => 'civicrm_contact',
            'entity_id' => $contact_id,
            'tag_id' => $tag_id,
          ]);
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to apply tags to contact: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }
}