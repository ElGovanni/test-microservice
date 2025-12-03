<?php

namespace App\Controller;

use App\Repository\UserProfileRepository;
use App\Service\JwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class ProfileController extends AbstractController
{
    public function __construct(
        private UserProfileRepository $userProfileRepository,
        private JwtService $jwtService
    ) {
    }

    #[Route('/profile', name: 'get_profile', methods: ['GET'])]
    public function getProfile(Request $request): JsonResponse
    {
        $userId = $this->validateAndGetUserId($request);
        if ($userId === null) {
            return $this->json([
                'error' => 'Unauthorized - Invalid or missing token'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $profile = $this->userProfileRepository->findByUserId($userId);

        if (!$profile) {
            return $this->json([
                'error' => 'Profile not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'userId' => $profile->getUserId(),
            'email' => $profile->getEmail(),
            'bio' => $profile->getBio(),
            'avatar' => $profile->getAvatar(),
            'firstName' => $profile->getFirstName(),
            'lastName' => $profile->getLastName(),
            'createdAt' => $profile->getCreatedAt()->format('c'),
            'updatedAt' => $profile->getUpdatedAt()->format('c'),
        ]);
    }

    #[Route('/profile', name: 'update_profile', methods: ['PUT', 'PATCH'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $userId = $this->validateAndGetUserId($request);
        if ($userId === null) {
            return $this->json([
                'error' => 'Unauthorized - Invalid or missing token'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $profile = $this->userProfileRepository->findByUserId($userId);

        if (!$profile) {
            return $this->json([
                'error' => 'Profile not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Update fields if provided
        if (isset($data['bio'])) {
            $profile->setBio($data['bio']);
        }

        if (isset($data['avatar'])) {
            $profile->setAvatar($data['avatar']);
        }

        if (isset($data['firstName'])) {
            $profile->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $profile->setLastName($data['lastName']);
        }

        $this->userProfileRepository->save($profile);

        return $this->json([
            'message' => 'Profile updated successfully',
            'userId' => $profile->getUserId(),
            'email' => $profile->getEmail(),
            'bio' => $profile->getBio(),
            'avatar' => $profile->getAvatar(),
            'firstName' => $profile->getFirstName(),
            'lastName' => $profile->getLastName(),
            'updatedAt' => $profile->getUpdatedAt()->format('c'),
        ]);
    }

    private function validateAndGetUserId(Request $request): ?int
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7); // Remove "Bearer " prefix

        return $this->jwtService->getUserIdFromToken($token);
    }
}
