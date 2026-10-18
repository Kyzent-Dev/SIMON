<?php

namespace Tests\Feature;

use Tests\TestCase;

class LayoutPlaceholderTest extends TestCase
{
    private function src(string $path): string
    {
        return file_get_contents(base_path($path));
    }

    public function test_public_placeholder_is_visible_without_js(): void
    {
        $html = $this->withoutVite()->get('/')->assertOk()
            ->assertSee('data-motion="fade-up"', false)
            ->assertSee('Halaman publik (placeholder)')
            ->getContent();

        preg_match('/<html[^>]*>/', $html, $m);
        $this->assertStringNotContainsString('motion-ready', $m[0]);
        $this->assertDoesNotMatchRegularExpression('/opacity:\s*0/', $html);
    }

    public function test_dashboard_placeholder_renders(): void
    {
        $this->withoutVite()->get('/dashboard')->assertOk()->assertSee('Dashboard (placeholder)');
    }

    public function test_entries_are_split(): void
    {
        $public = $this->src('resources/views/layouts/public.blade.php');
        $app = $this->src('resources/views/layouts/app.blade.php');
        $this->assertStringContainsString('resources/js/public.js', $public);
        $this->assertStringNotContainsString('resources/js/app.js', $public);
        $this->assertStringContainsString('resources/js/app.js', $app);
        $this->assertStringNotContainsString('public.js', $app);
        $this->assertStringContainsString('resources/js/public.js', $this->src('vite.config.js'));
    }

    public function test_motion_import_is_confined(): void
    {
        $this->assertStringContainsString('from "motion";', $this->src('resources/js/motion.js'));
        foreach (glob(base_path('resources/js/*.js')) as $f) {
            $c = file_get_contents($f);
            $this->assertStringNotContainsString('motion/react', $c);
            $this->assertStringNotContainsString('framer-motion', $c);
        }
        $this->assertDoesNotMatchRegularExpression('/motion/', $this->src('resources/js/app.js'));
    }
}
