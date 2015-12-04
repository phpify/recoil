<?php

namespace Recoil\Coroutine;

use Exception;
use GuzzleHttp\Promise\PromiseInterface as GuzzlePromise;
use React\Promise\CancellablePromiseInterface;
use Recoil\Coroutine\Exception\PromiseRejectedException;
use Recoil\Kernel\Strand\Strand;

/**
 * A coroutine that resumes when a promise is fulfilled or rejected.
 */
class PromiseCoroutine implements Coroutine
{
    use CoroutineTrait;

    /**
     * @param object $promise The wrapped promise object.
     */
    public function __construct($promise)
    {
        $this->promise = $promise;
    }

    /**
     * Start the coroutine.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function call(Strand $strand)
    {
        $strand->suspend();

        $this->promise->then(
            function ($value) use ($strand) {
                if ($this->promise) {
                    $strand->resumeWithValue($value);
                }
            },
            function ($reason) use ($strand) {
                if ($this->promise) {
                    $strand->resumeWithException(
                        $this->adaptReasonToException($reason)
                    );
                }
            }
        );
    }

    /**
     * Inform the coroutine that the executing strand is being terminated.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function terminate(Strand $strand)
    {
        if (
            $this->promise instanceof CancellablePromiseInterface ||
            $this->promise instanceof GuzzlePromise
        ) {
            $this->promise->cancel();
        }
    }

    /**
     * Finalize the coroutine.
     *
     * This method is invoked after the coroutine is popped from the call stack.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function finalize(Strand $strand)
    {
        $this->promise = null;
    }

    /**
     * Adapt a promise rejection reason into an exception.
     *
     * @param mixed $reason
     *
     * @return Exception
     */
    protected function adaptReasonToException($reason)
    {
        if ($reason instanceof Exception) {
            return $reason;
        }

        return new PromiseRejectedException($reason);
    }

    private $promise;
}
