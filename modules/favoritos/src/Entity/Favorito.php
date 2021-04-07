<?php


namespace Drupal\favoritos\Entity;


use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Entidad favorito.
 *
 * @ingroup favorito
 *
 * @ContentEntityType(
 *   id = "favorito",
 *   label = "Favorito",
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "favorito",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   }
 * )
 */
class Favorito extends ContentEntityBase {
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['cookie'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Cookie'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ]);

    $fields['usuario'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Usuario')
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['producto'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Producto')
      ->setSetting('target_type', 'commerce_product')
      ->setSetting('handler', 'default');


    return $fields;
  }

}
