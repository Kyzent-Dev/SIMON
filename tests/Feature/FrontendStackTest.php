<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class FrontendStackTest extends TestCase
{
    public function test_package_json_has_required_and_forbidden_deps(): void
    {
        $pkg = json_decode(file_get_contents(dirname(__DIR__, 2).'/package.json'), true);
        $deps = ($pkg['dependencies'] ?? []) + ($pkg['devDependencies'] ?? []);

        foreach (['alpinejs', '@alpinejs/intersect', '@alpinejs/focus', '@alpinejs/sort', 'chart.js', 'tailwindcss', '@tailwindcss/forms'] as $d) {
            $this->assertArrayHasKey($d, $deps, $d);
        }
        foreach (['react', 'react-dom', 'framer-motion'] as $d) {
            $this->assertArrayNotHasKey($d, $deps, $d);
        }
    }

    public function test_app_css_has_theme_tokens_and_motion_rules(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        foreach (['@theme', '--color-mint-700:#17735B', 'prefers-reduced-motion', 'html.motion-ready [data-motion="fade-up"]', '--dur-slow'] as $s) {
            $this->assertStringContainsString($s, $css);
        }
    }

    public function test_app_js_does_not_import_motion(): void
    {
        $js = file_get_contents(dirname(__DIR__, 2).'/resources/js/app.js');

        $this->assertDoesNotMatchRegularExpression('/from\s+[\'"]motion|import\s+[\'"]motion|motion\.js/', $js);
    }
}
