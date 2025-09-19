<?php

use App\Support\Async\Pool as AppAsyncPool;
use App\Support\Async\Task as AppAsyncTask;

if (!class_exists(\Spatie\Async\Pool::class)) {
    class_alias(AppAsyncPool::class, \Spatie\Async\Pool::class);
}

if (!class_exists(\Spatie\Async\Task::class)) {
    class_alias(AppAsyncTask::class, \Spatie\Async\Task::class);
}
