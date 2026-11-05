import { readFileSync, existsSync } from 'node:fs';
import { resolve, relative } from 'node:path';

/** Asset integration reads the same installed Composer inventory as PHP. */
export function cmsAssets(root = process.cwd()) {
    const inventory = resolve(root, 'vendor/composer/installed.json');
    const installed = JSON.parse(readFileSync(inventory, 'utf8'));
    const packages = (installed.packages ?? installed).filter(pkg => pkg.extra?.dbcms);
    const directory = pkg => resolve(root, 'vendor/composer', pkg['install-path']);
    const inputs = packages
        .filter(pkg => ['dbcms-theme', 'dbcms-admin-theme'].includes(pkg.type))
        .map(pkg => relative(root, resolve(directory(pkg), 'theme.css')))
        .filter(path => existsSync(resolve(root, path)));
    const editors = packages
        .filter(pkg => pkg.type === 'dbcms-module')
        .map(pkg => resolve(directory(pkg), 'Resources/js/admin.js'))
        .filter(path => existsSync(path));
    const sources = [resolve(root, 'resources/views'), ...packages.map(directory)];
    const core = packages.find(pkg => pkg.name === 'dbravoan/dbcms-core');
    if (!core) throw new Error('Install the dbCMS core Composer dependency before building assets.');

    return {
        inputs,
        aliases: { '@dbcms': resolve(directory(core), 'resources') },
        plugin: {
            name: 'dbcms-composer-assets',
            enforce: 'pre',
            resolveId(id) {
                return id === 'virtual:dbcms-modules' ? '\0dbcms-modules' : undefined;
            },
            load(id) {
                if (id !== '\0dbcms-modules') return;
                return editors.map((path, index) => `import editor${index} from ${JSON.stringify(path)};`).join('\n')
                    + '\nexport default function register(Alpine) {'
                    + editors.map((_, index) => `editor${index}(Alpine);`).join('\n') + '}';
            },
            transform(code, id) {
                if (id.split('?')[0] !== resolve(directory(core), 'resources/css/app.css')) return;
                return code + '\n' + sources.map(path => `@source ${JSON.stringify(path)};`).join('\n');
            },
        },
    };
}
