<?php

declare(strict_types=1);

namespace Bladeberg\Tests\Feature;

use Bladeberg\Facades\Bladeberg;
use Bladeberg\Tests\TestCase;

class RenderApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('bladeberg.render_api.enabled', true);
        // Empty middleware so the route works without the host's web/auth stack.
        $app['config']->set('bladeberg.render_api.middleware', []);
    }

    public function test_registry_render_returns_html_for_block_content(): void
    {
        $html = Bladeberg::render('<!-- bb:paragraph --><p>Hello</p><!-- /bb:paragraph -->');

        $this->assertStringContainsString('Hello', $html);
        $this->assertStringContainsString('bb-content', $html);
    }

    public function test_render_endpoint_returns_rendered_html(): void
    {
        $response = $this->postJson('/bladeberg/render', [
            'content' => '<!-- bb:paragraph --><p>From the API</p><!-- /bb:paragraph -->',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['html']);

        $this->assertStringContainsString('From the API', $response->json('html'));
    }

    public function test_render_endpoint_validates_missing_content(): void
    {
        $this->postJson('/bladeberg/render', [])
            ->assertStatus(422);
    }

    public function test_render_endpoint_accepts_empty_content(): void
    {
        // 'present' allows an empty string, so empty content renders an empty wrapper.
        $this->postJson('/bladeberg/render', ['content' => ''])
            ->assertOk()
            ->assertJsonStructure(['html']);
    }
}
