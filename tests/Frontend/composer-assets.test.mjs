import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, mkdir, writeFile, symlink, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { resolve, dirname } from 'node:path';
import { build } from 'vite';
import { cmsAssets } from '../../resources/vite.mjs';

for (const linked of [false, true]) {
    test(`a real Vite application CSS build includes Composer block styles (${linked ? 'symlinks' : 'distribution directories'})`, async () => {
        const root = await mkdtemp(resolve(tmpdir(), 'dbcms-composer-assets-'));
        const put = async (path, content) => { await mkdir(dirname(path), { recursive: true }); await writeFile(path, content); };
        try {
            const installed = [];
            for (const [name, type, identifier] of [['dbcms-core', 'library', 'core'], ['fixture-blocks', 'dbcms-block-package', 'fixture_blocks']]) {
                const directory = resolve(root, linked ? 'catalogue' : 'vendor/dbravoan', name);
                await mkdir(directory, { recursive: true });
                if (linked) {
                    const link = resolve(root, 'vendor/dbravoan', name);
                    await mkdir(dirname(link), { recursive: true });
                    await symlink(directory, link, 'dir');
                }
                installed.push({ name: `dbravoan/${name}`, type, 'install-path': `../dbravoan/${name}`, extra: { dbcms: { identifier } } });
                await put(resolve(directory, name === 'dbcms-core' ? 'resources/css/app.css' : 'Resources/css/blocks.css'), name === 'dbcms-core' ? '.test-owned-core { color: #123456; }' : '.test-owned-block { display: grid; gap: 13px; }');
            }
            const native = resolve(root, linked ? 'catalogue/dbcms-core' : 'vendor/dbravoan/dbcms-core');
            await put(resolve(native, 'resources/css/native-blocks.css'), '.test-native-block { color: #456789; }');
            await put(resolve(native, 'resources/js/native-blocks.js'), 'export default function register() {}');
            await put(resolve(native, 'resources/admin-theme/theme.css'), '.test-native-admin { color: #123abc; }');
            await put(resolve(root, 'vendor/composer/installed.json'), JSON.stringify({ packages: installed }));
            const entry = resolve(root, 'resources/css/app.css');
            await put(entry, '@import "../../vendor/dbravoan/dbcms-core/resources/css/app.css";');
            const assets = cmsAssets(root);
            assert.equal(assets.plugin.transform('.unrelated {}', resolve(root, 'other.css')), undefined);
            const result = await build({ configFile: false, root, logLevel: 'silent', plugins: [assets.plugin], resolve: { preserveSymlinks: linked }, build: { write: false, minify: false, cssMinify: false, rolldownOptions: { input: entry } } });
            const css = result.output.filter(output => output.type === 'asset' && output.fileName.endsWith('.css')).map(output => String(output.source)).join('\n');
            assert.match(css, /\.test-owned-core\s*\{/);
            assert.match(css, /\.test-native-block\s*\{/);
            assert.match(css, /\.test-owned-block\s*\{[^}]*display:\s*grid;[^}]*gap:\s*13px;/);
            assert.equal((css.match(/\.test-owned-block\s*\{/g) || []).length, 1);
        } finally {
            await rm(root, { recursive: true, force: true });
        }
    });
}
