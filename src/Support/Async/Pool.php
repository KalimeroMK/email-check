<?php

namespace App\Support\Async;

use RuntimeException;
use Throwable;

class Pool
{
    private int $concurrency = 10;

    private int $timeout = 60;

    private int $sleepTime = 50000;

    private int $taskCounter = 0;

    /** @var array<int, Task> */
    private array $queue = [];

    /**
     * @var array<int, array{task: Task, socket: resource, started_at: float}>
     */
    private array $running = [];

    public static function create(): self
    {
        return new self();
    }

    public function concurrency(int $concurrency): self
    {
        $this->concurrency = max(1, $concurrency);

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);

        return $this;
    }

    public function sleepTime(int $microseconds): self
    {
        $this->sleepTime = max(1, $microseconds);

        return $this;
    }

    public function add(callable $task): Task
    {
        $taskObject = new Task($task, $this->taskCounter++);
        $this->queue[] = $taskObject;

        return $taskObject;
    }

    public function wait(): void
    {
        if (!function_exists('pcntl_fork')) {
            throw new RuntimeException('pcntl extension is required for async processing.');
        }

        while ($this->queue !== [] || $this->running !== []) {
            $this->startPendingTasks();
            $this->collectFinishedTasks();

            if ($this->running !== []) {
                usleep($this->sleepTime);
            }
        }
    }

    private function startPendingTasks(): void
    {
        while ($this->queue !== [] && count($this->running) < $this->concurrency) {
            $task = array_shift($this->queue);
            if (!$task instanceof Task) {
                break;
            }

            $this->launchTask($task);
        }
    }

    private function launchTask(Task $task): void
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($sockets === false) {
            throw new RuntimeException('Failed to create socket pair for async task.');
        }

        [$parentSocket, $childSocket] = $sockets;

        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Failed to fork process for async task.');
        }

        if ($pid === 0) {
            fclose($parentSocket);
            $payload = $this->runTask($task);
            fwrite($childSocket, $payload);
            fclose($childSocket);
            exit(0);
        }

        fclose($childSocket);
        stream_set_blocking($parentSocket, false);

        $this->running[$pid] = [
            'task' => $task,
            'socket' => $parentSocket,
            'started_at' => microtime(true),
        ];
    }

    private function runTask(Task $task): string
    {
        try {
            $result = $task->execute();

            return serialize(['status' => 'success', 'result' => $result]);
        } catch (Throwable $throwable) {
            return serialize(['status' => 'error', 'exception' => $throwable]);
        }
    }

    private function collectFinishedTasks(): void
    {
        if ($this->running === []) {
            return;
        }

        pcntl_signal_dispatch();

        foreach ($this->running as $pid => $meta) {
            $task = $meta['task'];
            $socket = $meta['socket'];
            $startedAt = $meta['started_at'];

            $status = 0;
            $waitResult = pcntl_waitpid($pid, $status, WNOHANG);
            if ($waitResult === -1) {
                fclose($socket);
                unset($this->running[$pid]);
                $task->triggerFailure(new RuntimeException('Failed to wait for async task.'));

                continue;
            }

            if ($waitResult === 0) {
                if ($this->timeout > 0 && (microtime(true) - $startedAt) >= $this->timeout) {
                    $this->terminateTask($pid, $socket, $task, sprintf(
                        'Async task exceeded timeout of %d seconds.',
                        $this->timeout
                    ));
                }

                continue;
            }

            $payload = stream_get_contents($socket);
            fclose($socket);
            unset($this->running[$pid]);

            if ($payload === false || $payload === '') {
                $task->triggerFailure(new RuntimeException('Async task returned an empty result.'));

                continue;
            }

            $this->handlePayload($task, $payload);
        }
    }

    private function terminateTask(int $pid, $socket, Task $task, string $message): void
    {
        if (function_exists('posix_kill')) {
            @posix_kill($pid, SIGKILL);
        }

        $status = 0;
        pcntl_waitpid($pid, $status);
        fclose($socket);
        unset($this->running[$pid]);

        $task->triggerFailure(new RuntimeException($message));
    }

    private function handlePayload(Task $task, string $payload): void
    {
        $data = @unserialize($payload);
        if (!is_array($data) || !isset($data['status'])) {
            $task->triggerFailure(new RuntimeException('Invalid async payload received.'));

            return;
        }

        if ($data['status'] === 'success') {
            $task->triggerSuccess($data['result'] ?? null);

            return;
        }

        $exception = $data['exception'] ?? null;
        if ($exception instanceof Throwable) {
            $task->triggerFailure($exception);

            return;
        }

        $task->triggerFailure(new RuntimeException('Async task failed with an unknown error.'));
    }
}
