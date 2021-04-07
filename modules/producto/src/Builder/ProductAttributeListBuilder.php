<?php

namespace Drupal\producto\Builder;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\Entity\User;

/**
 * Defines the list builder for product attributes.
 */
class ProductAttributeListBuilder extends ConfigEntityListBuilder {

  private function getAdminAccess() {
    /** @var User $user */
    $user = User::load(\Drupal::currentUser()->id());
    return $user->hasPermission('administer product attributes');
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Attribute name');
    if ($this->getAdminAccess()) {
      $header['id'] = $this->t('ID');
    }
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    if ($this->getAdminAccess()) {
      $row['id'] = $entity->id();
    }
    return $row + parent::buildRow($entity);
  }

  public function getOperations(EntityInterface $entity): array {
    $operactions = parent::getOperations($entity);
    if (!$this->getAdminAccess()) {
      unset($operactions['delete']);
    }
    return $operactions;
  }


}
