<?php

namespace HyperfTest\Database;

use Mockery as m;
use Hyperf\Support\Str;
use PHPUnit\Framework\TestCase;
use Hyperf\Console\OutputStyle;
use Hyperf\Container\Container;
use Hyperf\Filesystem\Filesystem;
use Hyperf\Support\Facades\Facade;
use Hyperf\Database\Migrations\Migrator;
use Hyperf\Database\Capsule\Manager as DB;
use Hyperf\Database\Migrations\DatabaseMigrationRepository;

class DatabaseMigratorIntegrationTest extends TestCase
{
    protected $db;
    protected $migrator;

    /**
     * Bootstrap Eloquent.
     *
     * @return void
     */
    public function setUp()
    {
        $this->db = $db = new DB;

        $db->addConnection([
            'driver'    => 'sqlite',
            'database'  => ':memory:',
        ]);

        $db->setAsGlobal();

        $container = new Container;
        $container->instance('db', $db->getDatabaseManager());
        Facade::setFacadeApplication($container);

        $this->migrator = new Migrator(
            $repository = new DatabaseMigrationRepository($db->getDatabaseManager(), 'migrations'),
            $db->getDatabaseManager(),
            new Filesystem
        );

        $output = m::mock(OutputStyle::class);
        $output->shouldReceive('writeln');

        $this->migrator->setOutput($output);

        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }
    }

    public function tearDown()
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }

    public function testBasicMigrationOfSingleFolder()
    {
        $ran = $this->migrator->run([__DIR__.'/migrations/one']);

        $this->assertTrue($this->db->schema()->hasTable('users'));
        $this->assertTrue($this->db->schema()->hasTable('password_resets'));

        $this->assertTrue(Str::contains($ran[0], 'users'));
        $this->assertTrue(Str::contains($ran[1], 'password_resets'));
    }

    public function testMigrationsCanBeRolledBack()
    {
        $this->migrator->run([__DIR__.'/migrations/one']);
        $this->assertTrue($this->db->schema()->hasTable('users'));
        $this->assertTrue($this->db->schema()->hasTable('password_resets'));
        $rolledBack = $this->migrator->rollback([__DIR__.'/migrations/one']);
        $this->assertFalse($this->db->schema()->hasTable('users'));
        $this->assertFalse($this->db->schema()->hasTable('password_resets'));

        $this->assertTrue(Str::contains($rolledBack[0], 'password_resets'));
        $this->assertTrue(Str::contains($rolledBack[1], 'users'));
    }

    public function testMigrationsCanBeReset()
    {
        $this->migrator->run([__DIR__.'/migrations/one']);
        $this->assertTrue($this->db->schema()->hasTable('users'));
        $this->assertTrue($this->db->schema()->hasTable('password_resets'));
        $rolledBack = $this->migrator->reset([__DIR__.'/migrations/one']);
        $this->assertFalse($this->db->schema()->hasTable('users'));
        $this->assertFalse($this->db->schema()->hasTable('password_resets'));

        $this->assertTrue(Str::contains($rolledBack[0], 'password_resets'));
        $this->assertTrue(Str::contains($rolledBack[1], 'users'));
    }

    public function testNoErrorIsThrownWhenNoOutstandingMigrationsExist()
    {
        $this->migrator->run([__DIR__.'/migrations/one']);
        $this->assertTrue($this->db->schema()->hasTable('users'));
        $this->assertTrue($this->db->schema()->hasTable('password_resets'));
        $this->migrator->run([__DIR__.'/migrations/one']);
    }

    public function testNoErrorIsThrownWhenNothingToRollback()
    {
        $this->migrator->run([__DIR__.'/migrations/one']);
        $this->assertTrue($this->db->schema()->hasTable('users'));
        $this->assertTrue($this->db->schema()->hasTable('password_resets'));
        $this->migrator->rollback([__DIR__.'/migrations/one']);
        $this->assertFalse($this->db->schema()->hasTable('users'));
        $this->assertFalse($this->db->schema()->hasTable('password_resets'));
        $this->migrator->rollback([__DIR__.'/migrations/one']);
    }

    public function testMigrationsCanRunAcrossMultiplePaths()
    {
        $this->migrator->run([__DIR__.'/migrations/one', __DIR__.'/migrations/two']);
        $this->assertTrue($this->db->schema()->hasTable('users'));
        $this->assertTrue($this->db->schema()->hasTable('password_resets'));
        $this->assertTrue($this->db->schema()->hasTable('flights'));
    }

    public function testMigrationsCanBeRolledBackAcrossMultiplePaths()
    {
        $this->migrator->run([__DIR__.'/migrations/one', __DIR__.'/migrations/two']);
        $this->assertTrue($this->db->schema()->hasTable('users'));
        $this->assertTrue($this->db->schema()->hasTable('password_resets'));
        $this->assertTrue($this->db->schema()->hasTable('flights'));
        $this->migrator->rollback([__DIR__.'/migrations/one', __DIR__.'/migrations/two']);
        $this->assertFalse($this->db->schema()->hasTable('users'));
        $this->assertFalse($this->db->schema()->hasTable('password_resets'));
        $this->assertFalse($this->db->schema()->hasTable('flights'));
    }

    public function testMigrationsCanBeResetAcrossMultiplePaths()
    {
        $this->migrator->run([__DIR__.'/migrations/one', __DIR__.'/migrations/two']);
        $this->assertTrue($this->db->schema()->hasTable('users'));
        $this->assertTrue($this->db->schema()->hasTable('password_resets'));
        $this->assertTrue($this->db->schema()->hasTable('flights'));
        $this->migrator->reset([__DIR__.'/migrations/one', __DIR__.'/migrations/two']);
        $this->assertFalse($this->db->schema()->hasTable('users'));
        $this->assertFalse($this->db->schema()->hasTable('password_resets'));
        $this->assertFalse($this->db->schema()->hasTable('flights'));
    }
}