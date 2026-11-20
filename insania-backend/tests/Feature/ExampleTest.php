<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_server_home_address_shows_nothing(): void
    {
        $this->get('/')->assertStatus(404);
    }
}
