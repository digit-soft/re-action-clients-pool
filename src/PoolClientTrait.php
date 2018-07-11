<?php

namespace Reaction\ClientsPool;

/**
 * Trait to use with your class, that implements `PoolClientInterface` to provide needed methods and properties.
 * @package Reaction\ClientsPool
 */
trait PoolClientTrait
{
    /**
     * @var int Created time
     */
    public $createdAt = 0;
    /**
     * @var PoolInterface Pool which this client belongs to
     */
    public $pool;

    /** @var string|null Client ID in pool */
    protected $_poolClientId;
    /** @var int Current state */
    protected $_poolClientState = PoolClientInterface::CLIENT_POOL_STATE_READY;
    /** @var int Previous state */
    protected $_poolClientStatePrev;
    /** @var int Queue counter */
    protected $_poolClientQueueCounter = 0;

    /**
     * Get client unique ID
     * @return string
     */
    public function getClientId()
    {
        if (!isset($this->_poolClientId)) {
            while (!isset($id) || !$this->isPoolClientIdUnique($id)) {
                $id = static::generatePoolClientId();
            }
            $this->_poolClientId = $id;
        }
        return $this->_poolClientId;
    }

    /**
     * Get client state
     * @return string
     */
    public function getClientState()
    {
        return $this->_poolClientState;
    }

    /**
     * Get queue count
     * @return int
     */
    public function getClientQueueCount()
    {
        return $this->_poolClientQueueCounter;
    }

    /**
     * Close client
     */
    public function clientClose()
    {
        $this->emit(PoolClientInterface::CLIENT_POOL_EVENT_CLOSE);
    }

    /**
     * Lock client
     */
    public function clientLock()
    {
        $this->changeState(PoolClientInterface::CLIENT_POOL_STATE_LOCKED);
    }

    /**
     * Unlock client
     */
    public function clientUnlock()
    {
        $this->restoreState();
    }

    /**
     * Change client state helper
     * @param int $state
     */
    protected function changeState($state)
    {
        $currState = $this->_poolClientState;
        //Save previous state
        if (isset($currState) && $currState !== PoolClientInterface::CLIENT_POOL_STATE_LOCKED) {
            $this->_poolClientStatePrev = $currState;
        }
        $this->_poolClientState = $state;
        $this->emit(PoolClientInterface::CLIENT_POOL_EVENT_CHANGE_STATE, [$state]);
    }

    /**
     * @param int $defaultState
     */
    protected function restoreState($defaultState = PoolClientInterface::CLIENT_POOL_STATE_READY)
    {
        $prevState = isset($this->_poolClientStatePrev) ? $this->_poolClientStatePrev : $defaultState;
        $this->changeState($prevState);
    }

    /**
     * Change client queue counter helper
     * @param int $queueCounter
     */
    protected function changeQueueCount($queueCounter)
    {
        if ($queueCounter < 0) {
            $queueCounter = 0;
        }
        $this->_poolClientQueueCounter = $queueCounter;
        //Automatically change client state to `ClientInterface::CLIENT_POOL_STATE_READY` on empty queue
        if ($this->_poolClientQueueCounter === 0 && $this->_poolClientState === PoolClientInterface::CLIENT_POOL_STATE_BUSY) {
            $this->changeState(PoolClientInterface::CLIENT_POOL_STATE_READY);
        } elseif ($this->_poolClientQueueCounter > 0 && $this->_poolClientState === PoolClientInterface::CLIENT_POOL_STATE_READY) {
            $this->changeState(PoolClientInterface::CLIENT_POOL_STATE_BUSY);
        }
        $this->emit(PoolClientInterface::CLIENT_POOL_EVENT_CHANGE_QUEUE, [$queueCounter]);
    }

    /**
     * Change client queue counter helper (increase)
     */
    protected function changeQueueCountInc()
    {
        $counter = $this->_poolClientQueueCounter + 1;
        $this->changeQueueCount($counter);
    }

    /**
     * Change client queue counter helper (decrease)
     */
    protected function changeQueueCountDec()
    {
        $counter = $this->_poolClientQueueCounter > 0 ? $this->_poolClientQueueCounter - 1 : 0;
        $this->changeQueueCount($counter);
    }

    /**
     * Get pool client ID prefix
     * @return string
     */
    protected static function getClientIdPrefix()
    {
        return '';
    }

    /**
     * Generate a random string, using a cryptographically secure
     * pseudo random number generator (random_int)
     * Used only if no Reaction class found
     *
     * @param int    $length How many characters do we want?
     * @param string $keySpace A string of all possible characters to select from
     * @return string
     * @throws \Exception
     */
    private static function randomStr($length = 8, $keySpace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $pieces = [];
        $max = mb_strlen($keySpace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces[] = $keySpace[random_int(0, $max)];
        }
        return implode('', $pieces);
    }

    /**
     * Check that pool client ID is unique
     * @param string $id
     * @return bool
     */
    private function isPoolClientIdUnique($id)
    {
        return !isset($this->pool) || !$this->pool->clientExists($id);
    }

    /**
     * Generate random client ID
     * @return string
     */
    private static function generatePoolClientId()
    {
        $now = ceil(microtime(true) * 10000);
        if (class_exists('Reaction', false)) {
            $rand = \Reaction::$app->security->generateRandomString(8);
        } else {
            $rand = static::randomStr();
        }
        return static::getClientIdPrefix() . $now . $rand;
    }
}