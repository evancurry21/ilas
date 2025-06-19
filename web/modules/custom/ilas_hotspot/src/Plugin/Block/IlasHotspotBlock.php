<?php

namespace Drupal\ilas_hotspot\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an 'ILAS Hotspot' Block.
 *
 * @Block(
 *   id = "ilas_hotspot_block",
 *   admin_label = @Translation("ILAS Hotspot"),
 *   category = @Translation("ILAS"),
 * )
 */
class IlasHotspotBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Define hotspot data
    $hotspots = [
      [
        'title' => 'Housing',
        'content' => 'Idaho Legal Aid helped <strong>1,505 clients with housing needs</strong>, preventing evictions and improving housing conditions for vulnerable Idahoans throughout the state.',
        'category' => 'housing',
        'icon' => '/themes/custom/b5subtheme/images/icons/house-icon.svg',
        'placement' => 'top',
      ],
      [
        'title' => 'Health',
        'content' => 'ILAS <strong>helped clients in 268 cases</strong> by advocating for access to necessary and lifesaving health care coverage and services.',
        'category' => 'health',
        'icon' => '/themes/custom/b5subtheme/images/icons/health-icon.svg',
        'placement' => 'right',
      ],
      [
        'title' => 'Consumer Rights',
        'content' => 'ILAS helped over 267 clients fight back against unlawful debt collection and predatory lending.',
        'category' => 'consumer-rights',
        'icon' => '/themes/custom/b5subtheme/images/icons/consumericon.svg',
        'placement' => 'left',
      ],
      [
        'title' => 'Individual Rights',
        'content' => '<strong>In over 174 cases</strong>, Idaho Legal Aid Services provided assistance with wills, advance directives, employment rights, expungement, and other individual rights matters.',
        'category' => 'individual-rights',
        'icon' => '/themes/custom/b5subtheme/images/icons/individual-rights-icon.svg',
        'placement' => 'bottom',
      ],
      [
        'title' => 'Older Adults',
        'content' => 'Our attorneys helped clients secure essential public benefits including SNAP, Medicaid, Social Security, and unemployment benefits to ensure families could meet their basic needs.',
        'category' => 'older-adults',
        'icon' => '/themes/custom/b5subtheme/images/icons/older-adults-icon.svg',
        'placement' => 'left',
      ],
      [
        'title' => 'Family',
        'content' => 'ILAS provided critical legal assistance to survivors of domestic violence, securing protection orders and helping families navigate difficult transitions. Over 1,686 clients were served in 2024.',
        'category' => 'family',
        'icon' => '/themes/custom/b5subtheme/images/icons/family.svg',
        'placement' => 'right',
      ],
    ];
    
    // Create hotspot render array
    return ilas_hotspot_create(
      '/themes/custom/b5subtheme/images/icons/impact-graphic-2.svg',
      $hotspots,
      TRUE
    );
  }

}