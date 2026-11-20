<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

final class IsolatedTestDatabase
{
    public static function assertConnection(): void
    {
        $expected = (string) getenv('DBCMS_TEST_DATABASE');
        if (getenv('DBCMS_TEST_ISOLATED') !== '1'
            || preg_match('/^(?:core_testing|core_test_[a-f0-9]{12})$/D', $expected) !== 1
            || ! app()->environment('testing')
            || DB::connection()->getDriverName() !== 'mysql'
            || DB::connection()->getDatabaseName() !== $expected
            || DB::transactionLevel() !== 0
            || DB::selectOne('SELECT DATABASE() AS name')->name !== $expected) {
            throw new LogicException('Requires an explicitly selected disposable MySQL database and no enclosing transaction.');
        }
    }
}
