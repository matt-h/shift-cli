<?php

namespace Shift\Cli\Support;

use App;
use Arr;

class ComplexClass
{
    public function imported()
    {
        App::make('app');
        Arr::wrap('arr');
    }

    public function global()
    {
        \DB::query('SELECT * FROM users');
        \Str::of('something');
    }

    public function noop()
    {
        SomeApp::make('app');
        Another\Arr::wrap('arr');
    }
}
