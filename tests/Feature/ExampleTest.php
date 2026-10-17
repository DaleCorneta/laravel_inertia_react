<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_renders_the_inertia_welcome_component(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Welcome'));
    }
}
