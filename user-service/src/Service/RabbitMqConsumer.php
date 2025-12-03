<?php

namespace App\Service;

use App\Entity\UserProfile;
use App\Repository\UserProfileRepository;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class RabbitMqConsumer
{
    private string $host;
    private int $port;
    private string $user;
    private string $password;

    public function __construct(
        private UserProfileRepository $userProfileRepository,
        private LoggerInterface $logger,
        string $rabbitMqUrl
    ) {
        // Parse AMQP URL: amqp://user:pass@host:port
        $parsed = parse_url($rabbitMqUrl);
        $this->host = $parsed['host'] ?? 'rabbitmq';
        $this->port = $parsed['port'] ?? 5672;
        $this->user = $parsed['user'] ?? 'guest';
        $this->password = $parsed['pass'] ?? 'guest';
    }

    public function consumeUserCreatedEvents(): void
    {
        $connection = new AMQPStreamConnection(
            $this->host,
            $this->port,
            $this->user,
            $this->password
        );

        $channel = $connection->channel();

        // Declare exchange
        $channel->exchange_declare('user_events', 'topic', false, true, false);

        // Declare queue
        $channel->queue_declare('user_profile_creation', false, true, false, false);

        // Bind queue to exchange with routing key
        $channel->queue_bind('user_profile_creation', 'user_events', 'user.created');

        $this->logger->info('Waiting for user.created events...');

        $callback = function (AMQPMessage $msg) {
            $this->handleUserCreatedEvent($msg);
        };

        $channel->basic_consume('user_profile_creation', '', false, true, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    private function handleUserCreatedEvent(AMQPMessage $msg): void
    {
        try {
            $data = json_decode($msg->body, true);

            $this->logger->info('Received user.created event', [
                'userId' => $data['userId'],
                'email' => $data['email']
            ]);

            // Check if profile already exists
            $existingProfile = $this->userProfileRepository->findByUserId($data['userId']);
            if ($existingProfile) {
                $this->logger->warning('Profile already exists for user', ['userId' => $data['userId']]);
                return;
            }

            // Create new profile
            $profile = new UserProfile();
            $profile->setUserId($data['userId']);
            $profile->setEmail($data['email']);

            $this->userProfileRepository->save($profile);

            $this->logger->info('Profile created successfully', ['userId' => $data['userId']]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle user.created event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
