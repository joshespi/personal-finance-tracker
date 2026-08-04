<?php

namespace Tests\Unit;

use App\Services\FinnhubClient;
use Tests\TestCase;

class FinnhubClientTest extends TestCase
{
    public function test_redact_strips_token_from_url(): void
    {
        $message = 'cURL error 6: Could not resolve host: finnhub.io for https://finnhub.io/api/v1/quote?symbol=AAPL&token=sk_live_abc123';

        $this->assertSame(
            'cURL error 6: Could not resolve host: finnhub.io for https://finnhub.io/api/v1/quote?symbol=AAPL&token=***',
            FinnhubClient::redact($message)
        );
        $this->assertStringNotContainsString('sk_live_abc123', FinnhubClient::redact($message));
    }

    public function test_redact_leaves_message_without_a_token_unchanged(): void
    {
        $message = 'Connection timed out';

        $this->assertSame($message, FinnhubClient::redact($message));
    }
}
