<?php


namespace Drupal\stock\Entity;


use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Entidad alerta.
 *
 * @ingroup alerta
 *
 * @ContentEntityType(
 *   id = "alerta",
 *   label = "Alerta",
 *   base_table = "alerta",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   }
 * )
 */
class Alerta extends ContentEntityBase {

  const ESTADO_ENVIADA = 'enviada' ;
  const ESTADO_PENDIENTE = 'pendiente';
  const ESTADO_COMPLETADA = 'completada';

  /**
   * Obtener producto.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\EntityBase|null
   */
  public function getProducto(): \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\EntityBase|null {
    $producto = NULL;
    if ($this->get('producto')->target_id) {
      $producto = ProductVariation::load($this->get('producto')->target_id);
    }
    return $producto;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['usuario'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Usuario')
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'));

    $fields['producto'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Producto')
      ->setSetting('target_type', 'commerce_product_variation')
      ->setSetting('handler', 'default');

    $fields['estado'] = BaseFieldDefinition::create('list_string')
      ->setLabel('Estado')
      ->setSettings([
        'max_length' => 60,
        'text_processing' => 0,
        'allowed_values' => [self::ESTADO_PENDIENTE => 'Pendiente', self::ESTADO_ENVIADA => 'Enviada', self::ESTADO_COMPLETADA => 'Completada'],
      ])
      ->setDefaultValue('pendiente');

    return $fields;
  }
}
