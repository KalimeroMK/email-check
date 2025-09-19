<?php

namespace App\Support\Async;

use Throwable;

class Task
{
    /** @var callable */
    private $callback;

    /** @var callable|null */
    private $thenCallback = null;

    /** @var callable|null */
    private $catchCallback = null;

    private int $id;

    public function __construct(callable $callback, int $id)
    {
        $this->callback = $callback;
        $this->id = $id;
    }

    public function then(callable $callback): self
    {
        $this->thenCallback = $callback;

        return $this;
    }

    public function catch(callable $callback): self
    {
        $this->catchCallback = $callback;

        return $this;
    }

    public function execute(): mixed
    {
        $callback = $this->callback;

        return $callback();
    }

    public function triggerSuccess(mixed $result): void
    {
        if ($this->thenCallback !== null) {
            ($this->thenCallback)($result);
        }
    }

    public function triggerFailure(Throwable $throwable): void
    {
        if ($this->catchCallback !== null) {
            ($this->catchCallback)($throwable);

            return;
        }

        throw $throwable;
    }

    public function getId(): int
    {
        return $this->id;
    }
}
