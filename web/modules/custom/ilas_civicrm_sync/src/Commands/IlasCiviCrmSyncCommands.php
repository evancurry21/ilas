<?php

namespace Drupal\ilas_civicrm_sync\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ilas_civicrm_sync\Service\TaxonomySyncService;

/**
 * Drush commands for ILAS CiviCRM Sync.
 */
class IlasCiviCrmSyncCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The taxonomy sync service.
   *
   * @var \Drupal\ilas_civicrm_sync\Service\TaxonomySyncService
   */
  protected $taxonomySync;

  /**
   * Constructs IlasCiviCrmSyncCommands.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TaxonomySyncService $taxonomy_sync) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->taxonomySync = $taxonomy_sync;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('ilas_civicrm_sync.taxonomy_sync')
    );
  }

  /**
   * Synchronize all Drupal users to CiviCRM contacts.
   *
   * @command ilas:sync-users
   * @aliases isync-users
   * @usage ilas:sync-users
   *   Sync all existing Drupal users to CiviCRM contacts.
   */
  public function syncUsers() {
    $this->output()->writeln('Starting user synchronization...');
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Load all users except anonymous
      $users = $this->entityTypeManager->getStorage('user')->loadByProperties([
        'status' => 1,
      ]);
      
      $synced = 0;
      $errors = 0;
      
      foreach ($users as $user) {
        if ($user->isAnonymous()) {
          continue;
        }
        
        $this->output()->writeln('Syncing user: ' . $user->getEmail());
        
        try {
          ilas_civicrm_sync_user_to_contact($user, 'create');
          $synced++;
        }
        catch (\Exception $e) {
          $this->logger()->error('Failed to sync user {uid}: {error}', [
            'uid' => $user->id(),
            'error' => $e->getMessage(),
          ]);
          $errors++;
        }
      }
      
      $this->output()->writeln("Synchronization complete. Synced: $synced, Errors: $errors");
    }
    catch (\Exception $e) {
      $this->logger()->error('User sync failed: {error}', [
        'error' => $e->getMessage(),
      ]);
      return 1;
    }
    
    return 0;
  }

  /**
   * Synchronize Drupal taxonomies to CiviCRM tags.
   *
   * @command ilas:sync-taxonomies
   * @aliases isync-tax
   * @usage ilas:sync-taxonomies
   *   Sync all taxonomies to CiviCRM tags.
   */
  public function syncTaxonomies() {
    $this->output()->writeln('Starting taxonomy synchronization...');
    
    try {
      // Sync Service Areas vocabulary
      $this->output()->writeln('Syncing Service Areas...');
      $service_areas = $this->taxonomySync->syncVocabularyToTags('service_areas', 'Service Areas');
      $this->output()->writeln('Synced ' . count($service_areas) . ' service area tags');
      
      // Sync Topics vocabulary
      $this->output()->writeln('Syncing Topics...');
      $topics = $this->taxonomySync->syncVocabularyToTags('topics', 'Legal Topics');
      $this->output()->writeln('Synced ' . count($topics) . ' topic tags');
      
      // Sync Tags vocabulary
      $this->output()->writeln('Syncing General Tags...');
      $tags = $this->taxonomySync->syncVocabularyToTags('tags');
      $this->output()->writeln('Synced ' . count($tags) . ' general tags');
      
      $this->output()->writeln('Taxonomy synchronization complete!');
    }
    catch (\Exception $e) {
      $this->logger()->error('Taxonomy sync failed: {error}', [
        'error' => $e->getMessage(),
      ]);
      return 1;
    }
    
    return 0;
  }

  /**
   * Sync office information to CiviCRM organizations.
   *
   * @command ilas:sync-offices
   * @aliases isync-offices
   * @usage ilas:sync-offices
   *   Sync office information nodes to CiviCRM organizations.
   */
  public function syncOffices() {
    $this->output()->writeln('Starting office synchronization...');
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Load all office information nodes
      $offices = $this->entityTypeManager->getStorage('node')->loadByProperties([
        'type' => 'office_information',
        'status' => 1,
      ]);
      
      $synced = 0;
      
      foreach ($offices as $office) {
        $this->output()->writeln('Syncing office: ' . $office->getTitle());
        
        try {
          // Create organization contact
          $params = [
            'contact_type' => 'Organization',
            'organization_name' => $office->getTitle(),
          ];
          
          // Add address if available
          if ($office->hasField('field_address') && !$office->get('field_address')->isEmpty()) {
            $address = $office->get('field_address')->getValue()[0]['value'];
            // Basic parsing - this could be enhanced
            $params['api.Address.create'] = [
              'location_type_id' => 'Main',
              'street_address' => $address,
              'is_primary' => 1,
            ];
          }
          
          // Add phone if available
          if ($office->hasField('field_telephone') && !$office->get('field_telephone')->isEmpty()) {
            $phone = $office->get('field_telephone')->getValue()[0]['value'];
            $params['api.Phone.create'] = [
              'phone' => $phone,
              'location_type_id' => 'Main',
              'phone_type_id' => 'Phone',
              'is_primary' => 1,
            ];
          }
          
          $result = civicrm_api3('Contact', 'create', $params);
          
          if (!empty($result['id'])) {
            $synced++;
            
            // Store mapping in node field if you want to track it
            $this->output()->writeln('Created organization contact ID: ' . $result['id']);
          }
        }
        catch (\Exception $e) {
          $this->logger()->error('Failed to sync office {nid}: {error}', [
            'nid' => $office->id(),
            'error' => $e->getMessage(),
          ]);
        }
      }
      
      $this->output()->writeln("Office synchronization complete. Synced: $synced offices");
    }
    catch (\Exception $e) {
      $this->logger()->error('Office sync failed: {error}', [
        'error' => $e->getMessage(),
      ]);
      return 1;
    }
    
    return 0;
  }

  /**
   * Run all synchronization tasks.
   *
   * @command ilas:sync-all
   * @aliases isync-all
   * @usage ilas:sync-all
   *   Run all synchronization tasks in order.
   */
  public function syncAll() {
    $this->output()->writeln('Running complete CiviCRM synchronization...');
    
    // Run each sync task
    $this->syncUsers();
    $this->output()->writeln('');
    
    $this->syncTaxonomies();
    $this->output()->writeln('');
    
    $this->syncOffices();
    $this->output()->writeln('');
    
    $this->output()->writeln('All synchronization tasks complete!');
    
    return 0;
  }
}