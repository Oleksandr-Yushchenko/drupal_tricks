<?php

declare(strict_types=1);

namespace Drupal\jsonapi_custom_resource\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi_custom_resource\Resource\UserStorageConsentUpsert;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_custom_resource\Resource\UserStorageConsent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for our JSON:API user resources.
 */
class Routes implements ContainerInjectionInterface {

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The user resource type names.
   *
   * @var array
   */
  protected $resourceTypeNames;

  /**
   * Constructs a new Routes object.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jsonapi.resource_type.repository')
    );
  }

  /**
   * Builds the JSON API user resources routes.
   */
  public function routes() {
    $routes = new RouteCollection();

    $routes->add('jsonapi_user_resources.user_storage_consent', $this->getUserSorageConsentRoute());
    $routes->add('jsonapi_user_resources.user_storage_consent.upsert', $this->getUserSorageConsentUpdateRoute());

    // Prefix all routes with the JSON:API route prefix.
    $routes->addPrefix('/%jsonapi%');
    $routes->addRequirements([
      '_access' => 'TRUE',
    ]);

    return $routes;
  }

  /**
   * Gets the user consent list route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  public function getUserSorageConsentRoute() {
    // Individual route like `/jsonapi/user/user/{uuid}`.
    $route = new Route('/user/user_storage_consent/{entity}');
    $resource_type_names = $this->getResourceTypeNames();
    $route->setMethods(['GET']);
    $route
      ->addDefaults([
        '_jsonapi_resource' => UserStorageConsent::class,
        '_jsonapi_resource_types' => $resource_type_names,
        'resource_type' => reset($resource_type_names),
      ])
      ->setOption('parameters', [
        'entity' => [
          'type' => 'entity:user',
        ],
      ]);

    return $route;
  }

  /**
   * Gets the user consent create/update route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  public function getUserSorageConsentUpdateRoute() {
    // Individual route like `/jsonapi/user/user/{uuid}`.
    $route = new Route('/user/user_storage_consent/{entity}');
    $resource_type_names = $this->getResourceTypeNames();
    $route->setMethods(['POST', 'PATCH']);
    $route
      ->addDefaults([
        '_jsonapi_resource' => UserStorageConsentUpsert::class,
        '_jsonapi_resource_types' => $resource_type_names,
        'resource_type' => reset($resource_type_names),
      ])
      ->setOption('parameters', [
        'entity' => [
          'type' => 'entity:user',
        ],
      ]);

    return $route;
  }

  /**
   * Get the resource type names for the user entity type.
   *
   * @return string[]
   *   The resource type names.
   */
  protected function getResourceTypeNames(): array {
    if (empty($this->resourceTypeNames)) {
      $resource_type = $this->resourceTypeRepository->get('user', 'user');
      $this->resourceTypeNames = [$resource_type->getTypeName()];
    }
    return $this->resourceTypeNames;
  }

}
