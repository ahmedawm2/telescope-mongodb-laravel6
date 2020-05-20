<?php

namespace Laravel\Telescope\Watchers;

use Illuminate\Contracts\Auth\Authenticatable;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Telescope\FormatModel;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

class GateWatcher extends Watcher
{
    use FetchesStackTrace;

    /**
     * Register the watcher.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function register($app)
    {
        Gate::after([$this, 'recordGateCheck']);
    }

    /**
     * Record a gate check.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @param  string  $ability
     * @param  bool  $result
     * @param  array  $arguments
     * @return bool
     */
    public function recordGateCheck(?Authenticatable $user, $ability, $result, $arguments)
    {
        if (! Telescope::isRecording() || $this->shouldIgnore($ability)) {
            return;
        }

        $caller = $this->getCallerFromStackTrace();

        Telescope::recordGate(IncomingEntry::make([
            'ability' => $ability,
            'result' => $result ? 'allowed' : 'denied',
            'arguments' => $this->formatArguments($arguments),
            'file' => $caller['file'],
            'line' => $caller['line'],
        ]));

        return $result;
    }

    /**
     * Determine if the ability should be ignored.
     *
     * @param  string  $ability
     * @return bool
     */
    private function shouldIgnore($ability)
    {
        return Str::is($this->options['ignore_abilities'] ?? [], $ability);
    }

    /**
     * Format the given arguments.
     *
     * @param  array  $arguments
     * @return array
     */
    private function formatArguments($arguments)
    {
        return collect($arguments)->map(function ($argument) {
            return $argument instanceof Model ? FormatModel::given($argument) : $argument;
        })->toArray();
    }
}
