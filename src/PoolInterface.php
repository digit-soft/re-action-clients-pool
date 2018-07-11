<?php

namespace Reaction\ClientsPool;

use Evenement\EventEmitterInterface;

/**
 * Interface for `Pool` class or it's subclasses.
 * @package Reaction\ClientsPool
 */
interface PoolInterface extends EventEmitterInterface
{
    /**
     * Get client from pool
     * @return PoolClientInterface
     */
    public function getClient();

    /**
     * Get client with empty queue (in ready state)
     * @return PoolClientInterface|null
     */
    public function getClientIdle();

    /**
     * Get least busy client
     * @return PoolClientInterface|null
     */
    public function getClientLeastBusy();

    /**
     * Create client
     * @param bool $addToPool
     * @return PoolClientInterface
     */
    public function createClient($addToPool = true);

    /**
     * Check that clients max count reached
     * @return bool
     */
    public function isReachedMaxClients();

    /**
     * Check that client with given ID exists in pool
     * @param string $id
     * @return bool
     */
    public function clientExists($id);

    /**
     * Close all clients/connections
     */
    public function closeAll();
}