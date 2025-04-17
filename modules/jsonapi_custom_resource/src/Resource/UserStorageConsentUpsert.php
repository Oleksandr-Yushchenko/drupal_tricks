<?php

declare(strict_types=1);

namespace Drupal\jsonapi_custom_resource\Resource;

use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceResponse;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides a resource for updating the user storage consent.
 */
final class UserStorageConsentUpsert extends UserStorageConsentBase {

  /**
   * Handles the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
   *   The document.
   * @param \Drupal\user\UserInterface $entity
   *   The account.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function process(Request $request, JsonApiDocumentTopLevel $document, UserInterface $entity): ResourceResponse {
    $data = $document->getData();
    if ($data->getCardinality() !== 1) {
      throw new UnprocessableEntityHttpException("The request document's primary data must not be an array.");
    }

    $resource_object = $data->getIterator()->current();
    assert($resource_object instanceof ResourceObject);
    $required_properties = [
      'title' => ['POST'],
      'user_gave_consent' => ['POST', 'PATCH'],
    ];
    foreach ($required_properties as $required_property => $methods) {
      if (in_array($request->getMethod(), $methods) && !$resource_object->hasField($required_property)) {
        throw new UnprocessableEntityHttpException(sprintf('Missing required property "%s".', $required_property));
      }
    }
    $resource_type = $resource_object->getResourceType();
    $current_time = $this->time->getRequestTime();
    // Get existing user storage consents.
    $user_storage_consent = $this->getUserStorageConsents($entity);

    if ($request->isMethod('PATCH') && $resource_object->getId()) {
      $updated = FALSE;
      $storage_consent = [];
      foreach ($user_storage_consent as $delta => $consent) {
        if ($consent['uuid'] !== $resource_object->getId()) {
          continue;
        }
        $consent['changed'] = $current_time;
        foreach (array_keys($resource_type->getFields()) as $field_name) {
          if (!$resource_object->hasField($field_name)) {
            continue;
          }
          $consent[$field_name] = $resource_object->getField($field_name);
        }
        $user_storage_consent[$delta] = $consent;
        $storage_consent = $consent;
        $updated = TRUE;
        break;
      }
      if (!$updated) {
        throw new UnprocessableEntityHttpException(sprintf('Missing user consent with ID "%s".', $resource_object->getId()));
      }
    }
    else {
      // Create new consent.
      $storage_consent = [
        'uuid' => $this->uuidService->generate(),
        'changed' => $current_time,
      ];
      foreach (array_keys($resource_type->getFields()) as $field_name) {
        if (!$resource_object->hasField($field_name)) {
          continue;
        }
        $storage_consent[$field_name] = $resource_object->getField($field_name);
      }
      $user_storage_consent[] = $storage_consent;
    }

    // Save user storage consents.
    $this->setUserStorageConsents($entity, $user_storage_consent);

    $resource_objects_data = $this->createConsentObjectData($entity, $resource_type, [$storage_consent], 1);
    return $this->createJsonapiResponse($resource_objects_data, $request, 202);
  }

}
