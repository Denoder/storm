<?php namespace Winter\Storm\Foundation;

use Closure;
use Throwable;
use Carbon\Laravel\ServiceProvider as CarbonServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application as ApplicationBase;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Collection;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Winter\Storm\Events\EventServiceProvider;
use Winter\Storm\Filesystem\PathResolver;
use Winter\Storm\Foundation\ProviderRepository;
use Winter\Storm\Foundation\Providers\ExecutionContextProvider;
use Winter\Storm\Foundation\Providers\LogServiceProvider;
use Winter\Storm\Foundation\Providers\MakerServiceProvider;
use Winter\Storm\Router\RoutingServiceProvider;
use Winter\Storm\Support\Str;
use Winter\Storm\Support\Facades\Config;

class Application extends ApplicationBase
{
    /**
     * The base path for plugins.
     *
     * @var string
     */
    protected $pluginsPath;

    /**
     * The base path for themes.
     *
     * @var string
     */
    protected $themesPath;

    /**
     * The base temp path.
     *
     * @var string
     */
    protected $tempPath;

    /**
     * The base path for uploads.
     *
     * @var string
     */
    protected $uploadsPath;

    /**
     * The base path for media.
     *
     * @var string
     */
    protected $mediaPath;

    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version()
    {
        return static::VERSION . ' - Winter CMS';
    }

    /**
     * Get the path to the public / web directory.
     *
     * @return string
     */
    public function publicPath()
    {
        return $this->basePath;
    }

    /**
     * Get the path to the language files.
     *
     * @return string
     */
    public function langPath($path = '')
    {
        return PathResolver::join($this->basePath, '/lang' . (!empty($path) ? "/$path" : ''));
    }

    /**
     * Register the basic bindings into the container.
     *
     * @return void
     */
    protected function registerBaseBindings()
    {
        parent::registerBaseBindings();

        $this->bind('Illuminate\Foundation\Application', static::class);
    }

    /**
     * Register all of the base service providers.
     *
     * @return void
     */
    protected function registerBaseServiceProviders()
    {
        $this->register(new EventServiceProvider($this));

        $this->register(new LogServiceProvider($this));

        $this->register(new RoutingServiceProvider($this));

        $this->register(new MakerServiceProvider($this));

        $this->register(new ExecutionContextProvider($this));

        $this->register(new CarbonServiceProvider($this));
    }

    /**
     * Run the given array of bootstrap classes.
     *
     * @param  array  $bootstrappers
     * @return void
     */
    public function bootstrapWith(array $bootstrappers)
    {
        $this->hasBeenBootstrapped = true;

        $exceptions = [];
        foreach ($bootstrappers as $bootstrapper) {
            $this['events']->dispatch('bootstrapping: '.$bootstrapper, [$this]);

            // Defer any exceptions until after the application has been
            // bootstrapped so that the exception handler can run without issues
            try {
                $this->make($bootstrapper)->bootstrap($this);
            } catch (\Exception $ex) {
                $exceptions[] = $ex;
            }

            $this['events']->dispatch('bootstrapped: '.$bootstrapper, [$this]);
        }

        if (!empty($exceptions)) {
            throw $exceptions[0];
        }
    }

    /**
     * Bind all of the application paths in the container.
     *
     * @return void
     */
    protected function bindPathsInContainer()
    {
        parent::bindPathsInContainer();

        $this->instance('path.plugins', $this->pluginsPath());
        $this->instance('path.themes', $this->themesPath());
        $this->instance('path.temp', $this->tempPath());
        $this->instance('path.uploads', $this->uploadsPath());
        $this->instance('path.media', $this->mediaPath());
        $this->instance('path.lang', $this->langPath());
    }

    /**
     * Get the path to the public / web directory.
     *
     * @return string
     */
    public function pluginsPath()
    {
        return $this->pluginsPath ?: PathResolver::join($this->basePath, '/plugins');
    }

    /**
     * Set the plugins path for the application.
     *
     * @param  string $path
     * @return $this
     */
    public function setPluginsPath($path)
    {
        $path = PathResolver::standardize($path);
        $this->pluginsPath = $path;
        $this->instance('path.plugins', $path);
        return $this;
    }

    /**
     * Get the path to the public / web directory.
     *
     * @return string
     */
    public function themesPath()
    {
        return $this->themesPath ?: PathResolver::join($this->basePath, '/themes');
    }

    /**
     * Set the themes path for the application.
     *
     * @param  string $path
     * @return $this
     */
    public function setThemesPath($path)
    {
        $path = PathResolver::standardize($path);
        $this->themesPath = $path;
        $this->instance('path.themes', $path);
        return $this;
    }

    /**
     * Get the path to the public / web directory.
     *
     * @return string
     */
    public function tempPath()
    {
        return $this->tempPath ?: PathResolver::join($this->basePath, '/storage/temp');
    }

    /**
     * Set the temp path for the application.
     *
     * @return static
     */
    public function setTempPath($path)
    {
        $path = PathResolver::standardize($path);
        $this->tempPath = $path;
        $this->instance('path.temp', $path);
        return $this;
    }

    /**
     * Get the path to the uploads directory.
     *
     * @return string
     */
    public function uploadsPath()
    {
        return $this->uploadsPath ?: PathResolver::join($this->basePath, '/storage/app/uploads');
    }

    /**
     * Set the uploads path for the application.
     *
     * @return static
     */
    public function setUploadsPath($path)
    {
        $path = PathResolver::standardize($path);
        $this->uploadsPath = $path;
        $this->instance('path.uploads', $path);
        return $this;
    }

    /**
     * Get the path to the media directory.
     *
     * @return string
     */
    public function mediaPath()
    {
        return $this->mediaPath ?: PathResolver::join($this->basePath, '/storage/app/media');
    }

    /**
     * Set the media path for the application.
     *
     * @return static
     */
    public function setMediaPath($path)
    {
        $path = PathResolver::standardize($path);
        $this->mediaPath = $path;
        $this->instance('path.media', $path);
        return $this;
    }

    /**
     * Normalize a relative or absolute path to a cache file.
     *
     * @param  string  $key
     * @param  string  $default
     * @return string
     */
    protected function normalizeCachePath($key, $default)
    {
        $path = parent::normalizeCachePath($key, $default);

        $directory = pathinfo($path, PATHINFO_DIRNAME);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $path;
    }

    /**
     * Resolve the given type from the container.
     *
     * (Overriding Container::make)
     *
     * @param  string  $abstract
     * @return mixed
     */
    public function make($abstract, array $parameters = [])
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->deferredServices[$abstract])) {
            $this->loadDeferredProvider($abstract);
        }

        if ($parameters) {
            return $this->make(Maker::class)->make($abstract, $parameters);
        }

        return parent::make($abstract);
    }

    /**
     * Register a "before" application filter.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public function before($callback)
    {
        $this->make('router')->before($callback);
    }

    /**
     * Register an "after" application filter.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public function after($callback)
    {
        $this->make('router')->after($callback);
    }

    /**
     * Register an application error handler.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function error(Closure $callback)
    {
        $this->make('Illuminate\Contracts\Debug\ExceptionHandler')->error($callback);
    }

    /**
     * Register an error handler for fatal errors.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function fatal(Closure $callback)
    {
        $this->error(function (FatalError $e) use ($callback) {
            return call_user_func($callback, $e);
        });
    }

    /**
     * Determine if we are running in the back-end area.
     *
     * @return bool
     */
    public function runningInBackend()
    {
        return $this['execution.context'] == 'back-end';
    }

    /**
     * Returns true if a database connection is present.
     */
    public function hasDatabase(): bool
    {
        try {
            $this['db.connection']->getPdo();
        } catch (Throwable $ex) {
            return false;
        }

        return true;
    }

    /**
     * Checks if the provided table is present on the default database connection.
     */
    public function hasDatabaseTable(string $table): bool
    {
        return $this->hasDatabase() && $this['db.connection']->getSchemaBuilder()->hasTable($table);
    }

    /**
     * Set the current application locale.
     * @param  string  $locale
     * @return void
     */
    public function setLocale($locale)
    {
        parent::setLocale($locale);

        $this['events']->dispatch('locale.changed', [$locale]);
    }

    /**
     * Register all of the configured providers.
     *
     * @param bool $isRetry If true, this is a second attempt without the cached packages.
     * @return void
     */
    public function registerConfiguredProviders($isRetry = false)
    {
        $providers = Collection::make($this->get('config')['app.providers'])
                        ->partition(function ($provider) {
                            return Str::startsWith($provider, 'Illuminate\\');
                        });

        if (Config::get('app.loadDiscoveredPackages', false)) {
            $providers->splice(1, 0, [$this->make(PackageManifest::class)->providers()]);
        }

        $filesystem = new Filesystem;
        $repository = new ProviderRepository($this, $filesystem, $this->getCachedServicesPath());

        try {
            $repository->load($providers->collapse()->toArray());
        } catch (Throwable $e) {
            // If provider loading fails, we'll try it without the cached packages first, before failing completely
            if ($isRetry) {
                throw $e;
            }

            $this->clearPackageCache();
            $this->registerConfiguredProviders(true);
        }
    }

    /**
     * Clears cached packages, services and classes.
     *
     * @return void
     */
    protected function clearPackageCache()
    {
        $filesystem = new Filesystem;
        $filesystem->delete([
            $this->getCachedPackagesPath(),
            $this->getCachedServicesPath(),
            $this->getCachedClassesPath(),
        ]);

        // Reset package manifest
        $this->make(PackageManifest::class)->manifest = [];
    }


    //
    // Core aliases
    //

    /**
     * Register the core class aliases in the container.
     *
     * @return void
     */
    public function registerCoreContainerAliases()
    {
        $aliases = [
            'app'                  => [\Winter\Storm\Foundation\Application::class, \Illuminate\Contracts\Container\Container::class, \Illuminate\Contracts\Foundation\Application::class],
            'blade.compiler'       => [\Illuminate\View\Compilers\BladeCompiler::class],
            'cache'                => [\Illuminate\Cache\CacheManager::class, \Illuminate\Contracts\Cache\Factory::class],
            'cache.store'          => [\Illuminate\Cache\Repository::class, \Illuminate\Contracts\Cache\Repository::class],
            'config'               => [\Illuminate\Config\Repository::class, \Illuminate\Contracts\Config\Repository::class],
            'cookie'               => [\Illuminate\Cookie\CookieJar::class, \Illuminate\Contracts\Cookie\Factory::class, \Illuminate\Contracts\Cookie\QueueingFactory::class],
            'encrypter'            => [\Illuminate\Encryption\Encrypter::class, \Illuminate\Contracts\Encryption\Encrypter::class],
            'db'                   => [\Illuminate\Database\DatabaseManager::class, \Illuminate\Database\ConnectionResolverInterface::class],
            'db.connection'        => [\Illuminate\Database\Connection::class, \Illuminate\Database\ConnectionInterface::class],
            'db.schema'            => [\Illuminate\Database\Schema\Builder::class],
            'events'               => [\Illuminate\Events\Dispatcher::class, \Illuminate\Contracts\Events\Dispatcher::class],
            'files'                => [\Illuminate\Filesystem\Filesystem::class],
            'filesystem'           => [\Winter\Storm\Filesystem\FilesystemManager::class, \Illuminate\Contracts\Filesystem\Factory::class],
            'filesystem.disk'      => [\Illuminate\Contracts\Filesystem\Filesystem::class],
            'filesystem.cloud'     => [\Illuminate\Contracts\Filesystem\Cloud::class],
            'hash'                 => [\Illuminate\Contracts\Hashing\Hasher::class],
            'translator'           => [\Illuminate\Translation\Translator::class, \Illuminate\Contracts\Translation\Translator::class],
            'log'                  => [\Illuminate\Log\Logger::class, \Psr\Log\LoggerInterface::class],
            'mail.manager'         => [\Illuminate\Mail\MailManager::class, \Illuminate\Contracts\Mail\Factory::class],
            'mailer'               => [\Illuminate\Mail\Mailer::class, \Illuminate\Contracts\Mail\Mailer::class, \Illuminate\Contracts\Mail\MailQueue::class],
            'queue'                => [\Illuminate\Queue\QueueManager::class, \Illuminate\Contracts\Queue\Factory::class, \Illuminate\Contracts\Queue\Monitor::class],
            'queue.connection'     => [\Illuminate\Contracts\Queue\Queue::class],
            'queue.failer'         => [\Illuminate\Queue\Failed\FailedJobProviderInterface::class],
            'redirect'             => [\Illuminate\Routing\Redirector::class],
            'redis'                => [\Illuminate\Redis\RedisManager::class, \Illuminate\Contracts\Redis\Factory::class],
            'request'              => [\Illuminate\Http\Request::class, \Symfony\Component\HttpFoundation\Request::class],
            'router'               => [\Illuminate\Routing\Router::class, \Illuminate\Contracts\Routing\Registrar::class, \Illuminate\Contracts\Routing\BindingRegistrar::class],
            'session'              => [\Illuminate\Session\SessionManager::class],
            'session.store'        => [\Illuminate\Session\Store::class, \Illuminate\Contracts\Session\Session::class],
            'url'                  => [\Winter\Storm\Router\UrlGenerator::class, \Illuminate\Contracts\Routing\UrlGenerator::class],
            'validator'            => [\Winter\Storm\Validation\Factory::class, \Illuminate\Contracts\Validation\Factory::class],
            'view'                 => [\Illuminate\View\Factory::class, \Illuminate\Contracts\View\Factory::class],
        ];

        foreach ($aliases as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    //
    // Caching
    //

    /**
     * Get the path to the configuration cache file.
     *
     * @return string
     */
    public function getCachedConfigPath()
    {
        return PathResolver::join($this->storagePath(), '/framework/config.php');
    }

    /**
     * Get the path to the routes cache file.
     *
     * @return string
     */
    public function getCachedRoutesPath()
    {
        return PathResolver::join($this->storagePath(), '/framework/routes.php');
    }

    /**
     * Get the path to the cached "compiled.php" file.
     *
     * @return string
     */
    public function getCachedCompilePath()
    {
        return PathResolver::join($this->storagePath(), '/framework/compiled.php');
    }

    /**
     * Get the path to the cached services.json file.
     *
     * @return string
     */
    public function getCachedServicesPath()
    {
        return PathResolver::join($this->storagePath(), '/framework/services.php');
    }

    /**
     * Get the path to the cached packages.php file.
     *
     * @return string
     */
    public function getCachedPackagesPath()
    {
        return PathResolver::join($this->storagePath(), '/framework/packages.php');
    }

    /**
     * Get the path to the cached packages.php file.
     *
     * @return string
     */
    public function getCachedClassesPath()
    {
        return PathResolver::join($this->storagePath(), '/framework/classes.php');
    }

    /**
     * Get the application namespace.
     *
     * @return string
     */
    public function getNamespace()
    {
        /**
         * @TODO: Review calls to $app->getNamespace() that assume a single application namespace
         * (Usually \App) instead of a collection of modules & plugins that all form the namespace.
         * This is typically used for autoloading files and cleaning up output to remove extra
         * unnecessary paths but those tasks should be handled completely differently in Winter CMS.
         */
        return '';
    }

    /**
     * This is a temporary fix for an issue with twig reflection.
     * The full fix is here: https://github.com/twigphp/Twig/pull/3719
     *
     * @TODO: Remove this after Twig PR 3719 is merged.
     */
    public function __toString(): string
    {
        return get_called_class();
    }
}
