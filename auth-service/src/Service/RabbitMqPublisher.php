<?php

namespace App\Service;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMqPublisher
{
    private string $host;
    private int $port;
    private string $user;
    private string $password;

    public function __construct(string $rabbitMqUrl)
    {
        // Parse AMQP URL: amqp://user:pass@host:port
        $parsed = parse_url($rabbitMqUrl);
        $this->host = $parsed['host'] ?? 'rabbitmq';
        $this->port = $parsed['port'] ?? 5672;
        $this->user = $parsed['user'] ?? 'guest';
        $this->password = $parsed['pass'] ?? 'guest';
    }

    public function publish(string $exchange, string $routingKey, array $data): void
    {
        $connection = new AMQPStreamConnection(
            $this->host,
            $this->port,
            $this->user,
            $this->password
        );

        $channel = $connection->channel();

        // Declare exchange (fanout type for broadcasting)
        $channel->exchange_declare($exchange, 'topic', false, true, false);

        $messageBody = json_encode($data);
        $message = new AMQPMessage($messageBody, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $channel->basic_publish($message, $exchange, $routingKey);

        $channel->close();
        $connection->close();
    }

    public function publishUserCreated(int $userId, string $email): void
    {
        $this->publish('user_events', 'user.created', [
            'event' => 'user.created',
            'userId' => $userId,
            'email' => $email,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ]);
    }
}
