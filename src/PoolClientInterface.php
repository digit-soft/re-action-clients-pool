<?php

namespace Reaction\ClientsPool;

use Evenement\EventEmitterInterface;

/**
 * Interface ClientInterface.
 * You must implement this interface in your class, that will be managed by pool.
 * @package Reaction\ClientsPool
 *
 * @property integer       $createdAt
 * @property PoolInterface $pool
 */
interface PoolClientInterface extends EventEmitterInterface
{
    const CLIENT_POOL_EVENT_CLOSE = 'client_close';
    const CLIENT_POOL_EVENT_CHANGE_STATE = 'change_state';
    const CLIENT_POOL_EVENT_CHANGE_QUEUE = 'change_queue';

    const CLIENT_POOL_STATE_READY = 0;
    const CLIENT_POOL_STATE_BUSY = 1;
    const CLIENT_POOL_STATE_LOCKED = 2;
    const CLIENT_POOL_STATE_NOT_READY = 3;
    const CLIENT_POOL_STATE_CLOSING = 4;

    /**
     * Get client unique ID
     * @return string
     */
    public function getClientId();

    /**
     * Get client state
     * @return string
     */
    public function getClientState();

    /**
     * Get queue count
     * @return int
     */
    public function getClientQueueCount();

    /**
     * Close client
     */
    public function clientClose();

    /**
     * Lock client
     */
    public function clientLock();

    /**
     * Unlock client
     */
    public function clientUnlock();
}