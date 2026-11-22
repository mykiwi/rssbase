<?php

declare(strict_types=1);

namespace App\Controller;

use App\AtProto\OAuth\ClientIdentity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The document authorization servers read the confidential client from: its client_id is this URL.
 */
final class ClientMetadataController extends AbstractController
{
    #[Route(ClientIdentity::METADATA_PATH, name: 'oauth_client_metadata', methods: ['GET'])]
    public function __invoke(ClientIdentity $clientIdentity): JsonResponse
    {
        // A loopback client describes itself in its client_id.
        if (!$clientIdentity->isConfidential()) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($clientIdentity->metadata());
    }
}
