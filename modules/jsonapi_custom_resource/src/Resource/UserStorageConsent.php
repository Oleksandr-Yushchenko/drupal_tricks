<?php

declare(strict_types=1);

namespace Drupal\jsonapi_custom_resource\Resource;

use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a resource for listing the user storage consent.
 */
final class UserStorageConsent extends UserStorageConsentBase {

  /**
   * Handles the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\user\UserInterface $entity
   *   The account.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function process(Request $request, UserInterface $entity): ResourceResponse {
    $parameters = $request->attributes->all();
    $resource_type = $parameters['resource_types'][0] ?? NULL;
    assert($resource_type instanceof ResourceType);

    $user_storage_consent = $this->getUserStorageConsents($entity);
    $resource_objects_data = $this->createConsentObjectData($entity, $resource_type, $user_storage_consent);
    return $this->createJsonapiResponse($resource_objects_data, $request);
  }

}
