<?php namespace Sgpatil\Orientdb;

use Illuminate\Support\ServiceProvider;
use Sgpatil\Orientdb\Migrations\Migrator;
use Sgpatil\Orientdb\Migrations\MigrationCreator;
use Sgpatil\Orientdb\Console\Migrations\ResetCommand;
use Sgpatil\Orientdb\Console\Migrations\RefreshCommand;
use Sgpatil\Orientdb\Console\Migrations\InstallCommand;
use Sgpatil\Orientdb\Console\Migrations\MigrateCommand;
use Sgpatil\Orientdb\Console\Migrations\RollbackCommand;
use Sgpatil\Orientdb\Console\Migrations\MigrateMakeCommand;
use Sgpatil\Orientdb\Migrations\DatabaseMigrationRepository;

class MigrationServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->registerRepository();

		// Once we have registered the migrator instance we will go ahead and register
		// all of the migration related commands that are used by the "Artisan" CLI
		// so that they may be easily accessed for registering with the consoles.
		$this->registerMigrator();

		$this->registerCommands();
	}

	/**
	 * Register the migration repository service.
	 *
	 * @return void
	 */
	protected function registerRepository()
	{
		$this->app->singleton('migration.orient.repository', function($app)
		{
			$table = $app['config']['database.migrations'];
                        $db = $app->make("ConnectionResolverInterface");

			return new DatabaseMigrationRepository($db, $table);
		});
	}

	/**
	 * Register the migrator service.
	 *
	 * @return void
	 */
	protected function registerMigrator()
	{
		// The migrator is responsible for actually running and rollback the migration
		// files in the application. We'll pass in our database connection resolver
		// so the migrator can resolve any of these connections when it needs to.
		$this->app->singleton('orient.migrator', function($app)
		{
			$repository = $this->app->make('MigrationRepositoryInterface');//$app['orient.migration.repository'];
			return new Migrator($repository, $app['db'], $app['files']);
		});
	}

	/**
	 * Register all of the migration commands.
	 *
	 * @return void
	 */
	protected function registerCommands()
	{
		$commands = array('Migrate', 'Rollback', 'Reset', 'Refresh', 'Install', 'Make');

		// We'll simply spin through the list of commands that are migration related
		// and register each one of them with an application container. They will
		// be resolved in the Artisan start file and registered on the console.
		foreach ($commands as $command)
		{
			$this->{'register'.$command.'Command'}();
		}

		// Once the commands are registered in the application IoC container we will
		// register them with the Artisan start event so that these are available
		// when the Artisan application actually starts up and is getting used.
		$this->commands(
			'command.orient.migrate', 'command.orient.migrate.make',
			'command.orient.migrate.install', 'command.orient.migrate.rollback',
			'command.orient.migrate.reset', 'command.orient.migrate.refresh'
		);
	}

	/**
	 * Register the "migrate" migration command.
	 *
	 * @return void
	 */
	protected function registerMigrateCommand()
	{
		$this->app->singleton('command.orient.migrate', function($app)
		{
			$packagePath = $app['path.base'].'/vendor';

			return new MigrateCommand($app['orient.migrator'], $packagePath);
		});
	}

	/**
	 * Register the "rollback" migration command.
	 *
	 * @return void
	 */
	protected function registerRollbackCommand()
	{
		$this->app->singleton('command.orient.migrate.rollback', function($app)
		{
			return new RollbackCommand($app['orient.migrator']);
		});
	}

	/**
	 * Register the "reset" migration command.
	 *
	 * @return void
	 */
	protected function registerResetCommand()
	{
		$this->app->singleton('command.orient.migrate.reset', function($app)
		{
			return new ResetCommand($app['orient.migrator']);
		});
	}

	/**
	 * Register the "refresh" migration command.
	 *
	 * @return void
	 */
	protected function registerRefreshCommand()
	{
		$this->app->singleton('command.orient.migrate.refresh', function()
		{
			return new RefreshCommand;
		});
	}

	/**
	 * Register the "install" migration command.
	 *
	 * @return void
	 */
	protected function registerInstallCommand()
	{
		$this->app->singleton('command.orient.migrate.install', function($app)
		{
			return new InstallCommand($app['migration.orient.repository']);
		});
	}

	/**
	 * Register the "install" migration command.
	 *
	 * @return void
	 */
	protected function registerMakeCommand()
	{
            $this->registerCreator();

		$this->app->singleton('command.orient.migrate.make', function($app)
		{
			// Once we have the migration creator registered, we will create the command
			// and inject the creator. The creator is responsible for the actual file
			// creation of the migrations, and may be extended by these developers.
			$creator = $app['migration.orient.creator'];

			$composer = $app['composer'];

			return new MigrateMakeCommand($creator, $composer);
		});
                
		
	}

	/**
	 * Register the migration creator.
	 *
	 * @return void
	 */
	protected function registerCreator()
	{
		$this->app->singleton('migration.orient.creator', function($app)
		{
			return new MigrationCreator($app['files']);
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array(
			'migrator', 'migration.repository', 'command.migrate',
			'command.migrate.rollback', 'command.migrate.reset',
			'command.migrate.refresh', 'command.migrate.install',
			'migration.creator', 'command.migrate.make',
		);
	}

}
