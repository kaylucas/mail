<?php

namespace Tests\Unit\Services;

use App\Models\GraphSubscription;
use App\Models\Office365Connection;
use App\Models\User;
use App\Services\GraphSubscriptionService;
use App\Services\Office365Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Mockery;

class GraphSubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private GraphSubscriptionService $service;
    private $mockOffice365Service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockOffice365Service = Mockery::mock(Office365Service::class);
        $this->service = new GraphSubscriptionService($this->mockOffice365Service);
    }

    /**
     * Test that string expiration minutes from config are handled correctly.
     */
    public function test_handles_string_expiration_minutes_from_config(): void
    {
        // Set config to return a string (simulating env variable)
        Config::set('services.microsoft_graph.subscription_expiration_minutes', '10080');
        Config::set('services.microsoft_graph.webhook_base_url', 'https://example.com');
        Config::set('services.microsoft_graph.notification_url_path', '/webhooks/notifications');

        // Create test user with Office365 connection
        $user = User::factory()->create();
        $connection = Office365Connection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // Mock the Office365Service response
        $this->mockOffice365Service
            ->shouldReceive('createGraphSubscription')
            ->once()
            ->andReturn(['id' => 'test-subscription-id']);

        // This should not throw a type error
        $subscription = $this->service->createSubscription($user);

        $this->assertInstanceOf(GraphSubscription::class, $subscription);
        $this->assertEquals('test-subscription-id', $subscription->subscription_id);
        $this->assertNotNull($subscription->expires_at);
    }

    /**
     * Test that non-numeric expiration minutes throw an exception.
     */
    public function test_throws_exception_for_non_numeric_expiration_minutes(): void
    {
        Config::set('services.microsoft_graph.webhook_base_url', 'https://example.com');
        Config::set('services.microsoft_graph.notification_url_path', '/webhooks/notifications');

        $user = User::factory()->create();
        Office365Connection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expiration minutes must be numeric');

        $this->service->createSubscription($user, ['expirationMinutes' => 'invalid']);
    }

    /**
     * Test that expiration minutes below minimum are adjusted to 45.
     */
    public function test_adjusts_expiration_minutes_below_minimum(): void
    {
        Config::set('services.microsoft_graph.webhook_base_url', 'https://example.com');
        Config::set('services.microsoft_graph.notification_url_path', '/webhooks/notifications');

        $user = User::factory()->create();
        Office365Connection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->mockOffice365Service
            ->shouldReceive('createGraphSubscription')
            ->once()
            ->withArgs(function ($connection, $params) {
                // Check that expiration is at least 45 minutes from now
                $expiresAt = $params['expirationDateTime'];
                $diffInMinutes = now()->diffInMinutes($expiresAt);
                return $diffInMinutes >= 44 && $diffInMinutes <= 46; // Allow small variance
            })
            ->andReturn(['id' => 'test-subscription-id']);

        $subscription = $this->service->createSubscription($user, ['expirationMinutes' => 10]);
        
        $this->assertNotNull($subscription);
    }

    /**
     * Test that expiration minutes above maximum are capped at 10,080.
     */
    public function test_caps_expiration_minutes_at_maximum(): void
    {
        Config::set('services.microsoft_graph.webhook_base_url', 'https://example.com');
        Config::set('services.microsoft_graph.notification_url_path', '/webhooks/notifications');

        $user = User::factory()->create();
        Office365Connection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->mockOffice365Service
            ->shouldReceive('createGraphSubscription')
            ->once()
            ->withArgs(function ($connection, $params) {
                // Check that expiration is capped at 10,080 minutes (7 days)
                $expiresAt = $params['expirationDateTime'];
                $diffInMinutes = now()->diffInMinutes($expiresAt);
                return $diffInMinutes >= 10079 && $diffInMinutes <= 10081; // Allow small variance
            })
            ->andReturn(['id' => 'test-subscription-id']);

        $subscription = $this->service->createSubscription($user, ['expirationMinutes' => 20000]);
        
        $this->assertNotNull($subscription);
    }

    /**
     * Test that null expiration minutes use the default value.
     */
    public function test_uses_default_for_null_expiration_minutes(): void
    {
        Config::set('services.microsoft_graph.subscription_expiration_minutes', null);
        Config::set('services.microsoft_graph.webhook_base_url', 'https://example.com');
        Config::set('services.microsoft_graph.notification_url_path', '/webhooks/notifications');

        $user = User::factory()->create();
        Office365Connection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->mockOffice365Service
            ->shouldReceive('createGraphSubscription')
            ->once()
            ->withArgs(function ($connection, $params) {
                // Should use default of 10,080 minutes
                $expiresAt = $params['expirationDateTime'];
                $diffInMinutes = now()->diffInMinutes($expiresAt);
                return $diffInMinutes >= 10079 && $diffInMinutes <= 10081;
            })
            ->andReturn(['id' => 'test-subscription-id']);

        $subscription = $this->service->createSubscription($user);
        
        $this->assertNotNull($subscription);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
