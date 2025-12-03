<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\JwtService;
use App\Service\RabbitMqPublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
class AuthController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private JwtService $jwtService,
        private RabbitMqPublisher $rabbitMqPublisher,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json([
                'error' => 'Email and password are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if user already exists
        $existingUser = $this->userRepository->findByEmail($data['email']);
        if ($existingUser) {
            return $this->json([
                'error' => 'User with this email already exists'
            ], Response::HTTP_CONFLICT);
        }

        // Create new user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword(password_hash($data['password'], PASSWORD_BCRYPT));

        // Validate user
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->userRepository->save($user);

        // Publish user.created event to RabbitMQ
        try {
            $this->rabbitMqPublisher->publishUserCreated($user->getId(), $user->getEmail());
        } catch (\Exception $e) {
            // Log error but don't fail the registration
            error_log('Failed to publish user.created event: ' . $e->getMessage());
        }

        // Generate JWT token
        $token = $this->jwtService->generateToken($user->getId(), $user->getEmail());

        return $this->json([
            'message' => 'User registered successfully',
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
            'token' => $token
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json([
                'error' => 'Email and password are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findByEmail($data['email']);

        if (!$user || !password_verify($data['password'], $user->getPassword())) {
            return $this->json([
                'error' => 'Invalid credentials'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtService->generateToken($user->getId(), $user->getEmail());

        return $this->json([
            'message' => 'Login successful',
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
            'token' => $token
        ]);
    }

    #[Route('/password-reset/request', name: 'password_reset_request', methods: ['POST'])]
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'])) {
            return $this->json([
                'error' => 'Email is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findByEmail($data['email']);

        if (!$user) {
            // Return success even if user doesn't exist (security best practice)
            return $this->json([
                'message' => 'If the email exists, a password reset link has been sent'
            ]);
        }

        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $user->setResetToken($resetToken);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $this->userRepository->save($user);

        // In a real application, you would send an email here
        // For this example, we'll just return the token
        return $this->json([
            'message' => 'Password reset token generated',
            'resetToken' => $resetToken, // Don't return this in production!
            'note' => 'In production, this would be sent via email'
        ]);
    }

    #[Route('/password-reset/confirm', name: 'password_reset_confirm', methods: ['POST'])]
    public function confirmPasswordReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['token']) || !isset($data['newPassword'])) {
            return $this->json([
                'error' => 'Token and new password are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findByResetToken($data['token']);

        if (!$user || !$user->isResetTokenValid()) {
            return $this->json([
                'error' => 'Invalid or expired reset token'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Update password and clear reset token
        $user->setPassword(password_hash($data['newPassword'], PASSWORD_BCRYPT));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);

        $this->userRepository->save($user);

        return $this->json([
            'message' => 'Password reset successfully'
        ]);
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'service' => 'auth-service',
            'timestamp' => time()
        ]);
    }
}
