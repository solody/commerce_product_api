<?php

namespace Drupal\commerce_product_api\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "commerce_product_api_product_sreach",
 *   label = @Translation("Product sreach"),
 *   uri_paths = {
 *     "create" = "/api/rest/commerce-product/product-search"
 *   }
 * )
 */
class ProductSreach extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new ProductSreach object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('commerce_product_api'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post($data) {

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    /** @var \Drupal\search_api\Query\QueryInterface $query */
    $index = \Drupal\search_api\Entity\Index::load($data['index_id']);
    if (!$index) throw new BadRequestHttpException('invalid index.');
    $query = $index->query();

    // Change the parse mode for the search.
    /** @var \Drupal\search_api\ParseMode\ParseModeInterface $parse_mode */
    $parse_mode = \Drupal::service('plugin.manager.search_api.parse_mode')
      ->createInstance('direct');
    $parse_mode->setConjunction('OR');
    $query->setParseMode($parse_mode);

    if (isset($data['product_type'])) {
      $query->addCondition('type', $data['product_type']);
    }

    if (isset($data['product_categories'])) {
      $query->addCondition('categories', $data['product_categories'], 'IN');
    }

    // Set fulltext search keywords and fields.
    $query->keys($data['keywords']);

    // Do paging.
    $page = isset($data['page']) ? (int)abs($data['page']) : 0;
    $limit = isset($data['limit']) ? (int)abs($data['limit']) : 10;
    $query->range($page * $limit, $limit);

    // Add sorting.
    $query->sort('changed', 'DESC');

    // Execute the search.
    /** @var \Drupal\search_api\Query\ResultSet $results */
    $results = $query->execute();

    $products = [];
    if ($results->getResultCount()) {
      foreach ($results->getResultItems() as $resultItem) {
        $products[] = $resultItem->getOriginalObject();
      }
    }

    return new ModifiedResourceResponse($products, 200);
  }

  public function permissions() {
    return [];
  }
}
