<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Services\FacebookPagePublisher;
use PHPUnit\Framework\TestCase;

final class FacebookPagePublisherTest extends TestCase
{
    public function testConnectionRequiresBothPageAndToken(): void
    {
        Config::set('social.facebook.pages.vanassist', ['page_id' => '123', 'access_token' => '']);
        self::assertFalse(FacebookPagePublisher::configured('vanassist'));

        Config::set('social.facebook.pages.vanassist', ['page_id' => '123', 'access_token' => 'secret-token']);
        self::assertTrue(FacebookPagePublisher::configured('vanassist'));

        Config::set('social.facebook.pages.vanassist', ['page_id' => '', 'access_token' => '']);
    }

    public function testUnapprovedAssetCannotPublishEvenWhenConnected(): void
    {
        $this->expectExceptionMessage('Only approved Facebook assets');
        FacebookPagePublisher::publish('vanassist', ['platform' => 'facebook', 'status' => 'draft']);
    }
}
