<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    public function test_web_manifest_is_valid_and_branded(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true);

        $this->assertIsArray($manifest);
        $this->assertSame('Vulkain', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertArrayHasKey('start_url', $manifest);
        $this->assertArrayHasKey('scope', $manifest);

        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
    }

    public function test_service_worker_and_offline_page_exist(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('icon-192.png'));
        $this->assertFileExists(public_path('icon-512.png'));

        $sw = (string) file_get_contents(public_path('sw.js'));
        // Le SW sert la page offline en repli de navigation et s'active immédiatement.
        $this->assertStringContainsString('/offline.html', $sw);
        $this->assertStringContainsString('skipWaiting', $sw);
    }

    public function test_root_template_registers_pwa(): void
    {
        $blade = (string) file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString('rel="manifest"', $blade);
        $this->assertStringContainsString("serviceWorker.register('/sw.js')", $blade);
        $this->assertStringContainsString('apple-mobile-web-app-capable', $blade);
    }
}
