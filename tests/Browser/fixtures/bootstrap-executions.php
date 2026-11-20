<?php

declare(strict_types=1);

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Orchestration\ExecutionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\PrivacyFixtures;

$root = getenv('DBCMS_BROWSER_APP') ?: dirname(__DIR__, 3).'/.build/browser-app';
if (getenv('DBCMS_BROWSER_FIXTURE') !== '1'
    || ! is_file($root.'/.dbcms-browser-disposable')
    || trim((string) file_get_contents($root.'/.dbcms-browser-disposable')) !== 'dbcms-core-browser'
    || preg_match('/^dbcms_browser_[a-z0-9_]+$/D', (string) getenv('DB_DATABASE')) !== 1) {
    throw new RuntimeException('Requires a marked disposable application and dedicated browser database.');
}
require $root.'/vendor/autoload.php';
require_once dirname(__DIR__, 2).'/Support/PrivacyFixtures.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== getenv('DB_DATABASE') || DB::transactionLevel() !== 0) {
    throw new RuntimeException('The browser application must use its explicitly selected disposable database.');
}
$companyFile = storage_path('browser-company.json');
file_put_contents($companyFile, json_encode(PrivacyFixtures::company(), JSON_THROW_ON_ERROR));
$exit = Artisan::call('cms:install', [
    '--db-host' => getenv('DB_HOST'), '--db-port' => getenv('DB_PORT') ?: '3306', '--db-database' => getenv('DB_DATABASE'),
    '--db-username' => getenv('DB_USERNAME'), '--db-password' => getenv('DB_PASSWORD'), '--without-demo' => true,
    '--admin-name' => 'Browser administrator', '--admin-email' => 'browser@example.test', '--admin-password' => 'browser-only-password',
    '--company-file' => $companyFile, '--site-name' => 'Core browser acceptance', '--brand' => 'dbcms',
    '--theme' => (string) config('cms.theme'), '--force' => true,
]);
if ($exit !== 0) {
    throw new RuntimeException('The disposable browser installation failed.');
}
$user = User::where('email', 'browser@example.test')->sole();
$fixtures = [];
foreach (explode(',', getenv('DBCMS_BROWSER_THEMES') ?: 'dbcms,termocms') as $brand) {
    $executions = [];
    foreach (['approve', 'cancel'] as $decision) {
        $target = Page::create(['template' => 'default', 'is_published' => false]);
        $target->translations()->create(['locale' => 'es', 'title' => 'Browser execution '.$brand.' '.$decision, 'slug' => 'browser-execution-'.$brand.'-'.$decision]);
        $execution = app(ExecutionService::class)->startCapability('cms_entity_delete', ['type' => 'page', 'id' => $target->id], new ActorContext((int) $user->id));
        if ($execution->status !== 'approval_required') {
            throw new RuntimeException('Expected a real core approval checkpoint.');
        }
        $executions[$decision] = $execution->id;
    }
    $fixtures[$brand] = ['executions' => $executions];
}
file_put_contents(storage_path('browser-fixtures.json'), json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
