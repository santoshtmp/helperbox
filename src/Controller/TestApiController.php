<?php

namespace Drupal\helperbox\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * 
 */
class TestApiController extends ControllerBase {

    public function getData() {
        $data = [
            'name' => 'Test helperbox',
            'version' => '1.0',
            'features' => ['custom modules', 'api test'],
        ];

        return new JsonResponse($data);
    }
}
