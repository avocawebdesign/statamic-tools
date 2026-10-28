<?php

namespace Avocadesign\StatamicTools;

use Avocadesign\StatamicTools\Library\SampleImages;
use Avocadesign\StatamicTools\Console\CheckName;
use Avocadesign\StatamicTools\Console\Feedback;
use Avocadesign\StatamicTools\Console\FeedbackNotify;
use Avocadesign\StatamicTools\Console\SiteCatalogue;
use Avocadesign\StatamicTools\Console\SiteAudit;
use Avocadesign\StatamicTools\Console\SiteCheck;
use Avocadesign\StatamicTools\Console\LibraryInstall;
use Avocadesign\StatamicTools\Console\LibraryList;
use Avocadesign\StatamicTools\Console\MakeCollection;
use Avocadesign\StatamicTools\Console\PrototypeVersion;
use Avocadesign\StatamicTools\Console\SiteInstall;
use Avocadesign\StatamicTools\Console\SiteScript;
use Avocadesign\StatamicTools\Console\SiteUrls;
use Avocadesign\StatamicTools\Console\SitePermissions;
use Avocadesign\StatamicTools\Feedback\CommitFeedback;
use Avocadesign\StatamicTools\Feedback\FeedbackChanged;
use Avocadesign\StatamicTools\Feedback\FeedbackSettings;
use Avocadesign\StatamicTools\Feedback\FeedbackStore;
use Avocadesign\StatamicTools\Http\Middleware\InjectFeedbackWidget;
use Avocadesign\StatamicTools\Permissions\GrantEditorAccess;
use Statamic\Events\AssetContainerCreated;
use Statamic\Events\CollectionCreated;
use Statamic\Events\GlobalSetCreated;
use Statamic\Events\NavCreated;
use Statamic\Events\TaxonomyCreated;
use Illuminate\Console\Scheduling\Schedule;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'web' => __DIR__.'/../routes/web.php',
        'actions' => __DIR__.'/../routes/actions.php',
    ];

    protected $commands = [
        CheckName::class,
        Feedback::class,
        FeedbackNotify::class,
        LibraryInstall::class,
        LibraryList::class,
        MakeCollection::class,
        PrototypeVersion::class,
        SiteInstall::class,
        SiteScript::class,
        SiteUrls::class,
        SiteAudit::class,
        SiteCheck::class,
        SiteCatalogue::class,
        SitePermissions::class,
    ];

    // A structure created in the control panel or through Statamic's PHP API gives the editor role its permissions.
    protected $listen = [
        CollectionCreated::class => [GrantEditorAccess::class],
        TaxonomyCreated::class => [GrantEditorAccess::class],
        NavCreated::class => [GrantEditorAccess::class],
        GlobalSetCreated::class => [GrantEditorAccess::class],
        AssetContainerCreated::class => [GrantEditorAccess::class],
        // A change to a comment is committed through the site's server git script, where it has one.
        FeedbackChanged::class => [CommitFeedback::class],
    ];

    // The config is merged as the provider registers, so it is there when Statamic asks for the schedule, which it
    // does before bootAddon.
    public function register()
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/statamic-tools.php', 'statamic-tools');
    }

    // While feedback is on, the team gets a digest of new comments once reviewers go quiet: the command checks every
    // five minutes and decides. It needs Laravel's scheduler running on the server: `php artisan schedule:run` every
    // minute in cron.
    protected function schedule(Schedule $schedule)
    {
        if (FeedbackSettings::active()) {
            $schedule->command('avoca:feedback:notify')->everyFiveMinutes()->withoutOverlapping();
        }
    }

    public function bootAddon()
    {
        // Resolved rather than constructed, so the reference pages, the library installer and the tests
        // all read the same container, and a test can point it at a folder of its own.
        $this->app->bindIf(SampleImages::class, fn () => SampleImages::make());

        $this->app->bindIf(FeedbackStore::class, fn () => FeedbackStore::make());

        // Feedback adds its loader to the site's pages only while it is on: switched on, and with a password anywhere
        // but a local machine. Off, the middleware is never registered, so no request runs it. First in the group, it
        // works outside the static cache.
        if (FeedbackSettings::active()) {
            $this->app['router']->prependMiddlewareToGroup('statamic.web', InjectFeedbackWidget::class);
        }

        // The /site pages must never be served from the static cache.
        $prefix = config('statamic-tools.site.prefix', 'site');
        $urls = config('statamic.static_caching.exclude.urls', []);
        if (is_array($urls)) {
            config()->set('statamic.static_caching.exclude.urls', array_values(array_unique([...$urls, "/{$prefix}", "/{$prefix}/*"])));
        }
    }
}
