<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        'App\Events\RaidEnded' => [
            'App\Listeners\DeleteRaidChannels',
        ],
        'App\Events\RaidCreated' => [
            'App\Listeners\PostRaidToDiscord',
            'App\Listeners\DeleteDiscordMessage',
        ],
        'App\Events\RaidUpdated' => [
            'App\Listeners\PostRaidToDiscord',
            'App\Listeners\DeleteDiscordMessage'
        ],
        'App\Events\RaidDuplicate' => [
            'App\Listeners\DeleteDiscordMessage'
        ],
        'App\Events\RaidDeleted' => [
            'App\Listeners\PurgeDiscordRaidData',
        ],
        'App\Events\QuestInstanceCreated' => [
            'App\Listeners\PostQuestInstanceToDiscord',
        ],
        'App\Events\QuestInstanceDeleted' => [
            'App\Listeners\PurgeDiscordQuestInstanceData',
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
