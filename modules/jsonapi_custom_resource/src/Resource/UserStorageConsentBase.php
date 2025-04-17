<?php

declare(strict_types=1);

namespace Drupal\jsonapi_custom_resource\Resource;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\CacheableResourceResponse;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\NullIncludedData;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeAttribute;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Provides a base class for the user storage consent resource.
 */
abstract class UserStorageConsentBase extends EntityResourceBase implements ContainerInjectionInterface {

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected UserDataInterface $userData;

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuidService;

  /**
   * Constructs a new UserStorageConsentUpsert object.
   *
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   */
  public function __construct(UserDataInterface $user_data, TimeInterface $time, UuidInterface $uuid_service) {
    $this->userData = $user_data;
    $this->time = $time;
    $this->uuidService = $uuid_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.data'),
      $container->get('datetime.time'),
      $container->get('uuid')
    );
  }

  /**
   * Gets user storage consents.
   *
   * @param \Drupal\user\UserInterface $user
   *   User entity.
   *
   * @return array
   *   User storage consents list.
   */
  protected function getUserStorageConsents(UserInterface $user): array {
    return $this->userData->get('jsonapi_custom_resource', $user->id(), 'user_storage_consent') ?? [];
  }

  /**
   * Sets user storage consents.
   *
   * @param \Drupal\user\UserInterface $user
   *   User entity.
   * @param array $user_storage_consent
   *   User storage consents list.
   */
  protected function setUserStorageConsents(UserInterface $user, array $user_storage_consent): void {
    $this->userData->set('jsonapi_custom_resource', $user->id(), 'user_storage_consent', $user_storage_consent);
  }

  /**
   * Creates resource object data for the consent.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity from which to create a resource object.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type.
   * @param array $storage_data
   *   The user storage consents data.
   * @param int $cardinality
   *   The data cardinality.
   *
   * @return \Drupal\jsonapi\JsonApiResource\ResourceObjectData
   *   The resource object data.
   */
  protected function createConsentObjectData(EntityInterface $entity, ResourceType $resource_type, array $storage_data, $cardinality = -1): ResourceObjectData {
    $data = [];
    $links = new LinkCollection([]);
    $resource_fields = $resource_type->getFields();
    foreach ($storage_data as $consent) {
      $fields = [];
      foreach (array_keys($resource_fields) as $field_name) {
        $fields[$field_name] = $consent[$field_name] ?? NULL;
        if ($field_name === 'changed') {
          // Create the ISO date in Universal Time.
          $iso_date = date("Y-m-d\TH:i:s", $fields[$field_name]) . 'Z';
          $fields[$field_name] = $iso_date;
        }
      }
      $resource_object = new ResourceObject(
        $entity,
        $resource_type,
        $consent['uuid'],
        NULL,
        $fields,
        $links
      );
      $data[] = $resource_object;
    }
    return new ResourceObjectData($data, $cardinality);
  }

  /**
   * {@inheritdoc}
   */
  protected function createJsonapiResponse(ResourceObjectData $data, Request $request, $response_code = 200, array $headers = [], LinkCollection $links = NULL, array $meta = []): ResourceResponse {
    $links = ($links ?: new LinkCollection([]));
    if (!$links->hasLinkWithKey('self')) {
      $self_link = new Link(new CacheableMetadata(), Url::fromUri($request->getUri()), 'self');
      $links = $links->withLink('self', $self_link);
    }
    $document = new JsonApiDocumentTopLevel($data, new NullIncludedData(), $links, $meta);

    $response = new CacheableResourceResponse($document, $response_code, $headers);
    // Make sure that different sparse fieldsets are cached differently.
    $cache_contexts[] = 'url.query_args:fields';
    // Make sure that different sets of includes are cached differently.
    $cache_contexts[] = 'url.query_args:include';
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts($cache_contexts)
      ->setCacheMaxAge(0);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    $fields = [
      'uuid' => new ResourceTypeAttribute('uuid'),
      'title' => new ResourceTypeAttribute('title'),
      'user_gave_consent' => new ResourceTypeAttribute('user_gave_consent'),
      'changed' => new ResourceTypeAttribute('changed'),
    ];
    $resource_type = new ResourceType(
      'user',
      'user_storage_consent',
      NULL,
      FALSE,
      FALSE,
      TRUE,
      FALSE,
      $fields
    );
    return [$resource_type];
  }

}
