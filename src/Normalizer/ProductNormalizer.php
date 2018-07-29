<?php

namespace Drupal\commerce_product_api\Normalizer;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\serialization\Normalizer\ContentEntityNormalizer;

class ProductNormalizer extends ContentEntityNormalizer {

  public function supportsNormalization($data, $format = NULL) {
    if ($data instanceof EntityAdapter) {
      $entity =  $data->getValue();
      $route = \Drupal::routeMatch()->getRouteObject();
      return $entity instanceof ProductInterface && $route->hasRequirement('_product_search_api');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity_adapter, $format = NULL, array $context = []) {
    $entity =  $entity_adapter->getValue();
    $data = parent::normalize($entity, $format, $context);

    // TODO:: addCacheableDependency
    // $this->addCacheableDependency($context, $something);

    // 隐藏字段
    unset($data['body']);

    \Drupal::moduleHandler()->alter('product_search_result', $data, $entity, $this);

    return $data;
  }

}
