<?php

namespace GungCahyadiPP\AutoDocs;

use Illuminate\Support\ServiceProvider;

class AutoDocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(\Dedoc\Scramble\ScrambleServiceProvider::class);
        $this->app->register(\Dedoc\ScramblePro\ScrambleProServiceProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
