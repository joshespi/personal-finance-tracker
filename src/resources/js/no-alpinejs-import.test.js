import { describe, it, expect } from 'vitest';
import { readFileSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

// Livewire bundles and boots its own Alpine instance on every page (see the
// 'alpine:init' comment in app.js). A second `import Alpine from 'alpinejs'` anywhere
// in our own JS would start a competing instance and silently break every wire:*
// binding app-wide — this happened once already (the "dual Alpine/Livewire conflict"
// incident). `alpinejs` is intentionally NOT a dependency (see package.json) so a
// stray import fails the build immediately; this test catches it in CI too, before a
// build is even attempted, and pinpoints the offending file.
const jsDir = dirname(fileURLToPath(import.meta.url));

function jsFiles(dir) {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const full = join(dir, entry.name);
        if (entry.isDirectory()) return jsFiles(full);
        return entry.name.endsWith('.js') && !entry.name.endsWith('.test.js') ? [full] : [];
    });
}

describe('alpinejs import guard', () => {
    it('never imports the standalone alpinejs package from our own source', () => {
        const offenders = jsFiles(jsDir)
            .filter((file) => /from\s+['"]alpinejs['"]|require\(\s*['"]alpinejs['"]\s*\)/.test(readFileSync(file, 'utf8')))
            .map((file) => file.slice(jsDir.length + 1));

        expect(offenders).toEqual([]);
    });
});
