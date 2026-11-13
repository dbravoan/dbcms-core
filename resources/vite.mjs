import { readFileSync, existsSync, realpathSync } from 'node:fs';
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
    const runtimes = packages.map(pkg => ({
        selector: pkg.type === 'dbcms-block-package' ? '[data-cms-type]' : `[data-cms-${pkg.extra.dbcms.identifier.replaceAll('_', '-')}],[data-cms-module="${pkg.extra.dbcms.identifier}"]`,
        path: ['Resources/js/public.js', 'Resources/js/blocks.js'].map(file => resolve(directory(pkg), file)).find(file => existsSync(file)),
    })).filter(entry => entry.path);
    const blockStyles = packages.map(pkg => resolve(directory(pkg), 'Resources/css/blocks.css')).filter(path => existsSync(path));
    const sources = [resolve(root, 'resources/views'), ...packages
        .filter(pkg => !['dbcms-theme', 'dbcms-admin-theme'].includes(pkg.type))
        .map(directory)];
    const core = packages.find(pkg => pkg.name === 'dbravoan/dbcms-core');
    if (!core) throw new Error('Install the dbCMS core Composer dependency before building assets.');
    const canonical = path => existsSync(path) ? realpathSync(path) : resolve(path);
    // Vite transforms the application entry. Imported CSS can be resolved by
    // its CSS processor without a separate plugin transform for the core file.
    const stylesheetEntries = new Set([
        resolve(root, 'resources/css/app.css'),
        resolve(directory(core), 'resources/css/app.css'),
    ].map(canonical));

    return {
        inputs,
        aliases: { '@dbcms': resolve(directory(core), 'resources') },
        plugin: {
            name: 'dbcms-composer-assets',
            enforce: 'pre',
            resolveId(id) {
                if (id === 'virtual:dbcms-modules') return '\0dbcms-modules';
                if (id === 'virtual:dbcms-blocks') return '\0dbcms-blocks';
            },
            load(id) {
                if (id === '\0dbcms-blocks') {
                    return `export default function register(Alpine) {
                        const entries = [${runtimes.map(entry => `{selector:${JSON.stringify(entry.selector)},load:()=>import(${JSON.stringify(entry.path)})}`).join(',')}];
                        const loaded = new Set();
                        const scan = () => entries.forEach((entry,index) => {
                            if (!loaded.has(index) && document.querySelector(entry.selector)) {
                                loaded.add(index);
                                entry.load().then(module => module.default?.(Alpine)).catch(error => { loaded.delete(index); console.error('No se pudo iniciar el bloque',error); });
                            }
                        });
                        scan();
                        new MutationObserver(scan).observe(document.documentElement,{childList:true,subtree:true});
                    }`;
                }
                if (id !== '\0dbcms-modules') return;
                return editors.map((path, index) => `import editor${index} from ${JSON.stringify(path)};`).join('\n')
                    + '\nexport default function register(Alpine) {'
                    + editors.map((_, index) => `editor${index}(Alpine);`).join('\n') + '}';
            },
            transform(code, id) {
                const path = id.split('?')[0];
                if (!path.endsWith('.css') || !stylesheetEntries.has(canonical(path))) return;
                return blockStyles.map(path => `@import ${JSON.stringify(path)};`).join('\n') + '\n' + code + '\n' + sources.map(path => `@source ${JSON.stringify(path)};`).join('\n');
            },
        },
    };
}
