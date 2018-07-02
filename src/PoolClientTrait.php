<?php

namespace Reaction\ClientsPool;

/**
 * Trait PoolClientTrait
 * @package Reaction\ClientsPool
 */
trait PoolClientTrait
{
    public $createdAt = 0;

    /** @var string|null */
    protected $_poolClientId;
    /** @var int */
    protected $_poolClientState = ClientInterface::CLIENT_POOL_STATE_READY;
    /** @var int */
    protected $_poolClientQueueCounter = 0;

    /**
     * Get client unique ID
     * @return string
     */
    public function getClientId()
    {
        if (!isset($this->_poolClientId)) {
            $now = ceil(microtime(true) * 10000);
            if (class_exists('Reaction', false)) {
                $rand = \Reaction::$app->security->generateRandomString(8);
            } else {
                $rand = $this->randomStr();
            }
            $this->_poolClientId = $now . $rand;
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
        $this->emit(ClientInterface::CLIENT_POOL_EVENT_CLOSE);
    }

    /**
     * Change client state helper
     * @param int $state
     */
    protected function changeState($state)
    {
        $this->_poolClientState = $state;
        $this->emit(ClientInterface::CLIENT_POOL_EVENT_CHANGE_STATE, [$state]);
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
        if ($this->_poolClientQueueCounter === 0 && $this->_poolClientState === ClientInterface::CLIENT_POOL_STATE_BUSY) {
            $this->changeState(ClientInterface::CLIENT_POOL_STATE_READY);
        }
        $this->emit(ClientInterface::CLIENT_POOL_EVENT_CHANGE_QUEUE, [$queueCounter]);
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
     * Generate a random string, using a cryptographically secure
     * pseudo random number generator (random_int)
     * Used only if no Reaction class found
     *
     * @param int    $length How many characters do we want?
     * @param string $keySpace A string of all possible characters to select from
     * @return string
     * @throws \Exception
     */
    private function randomStr($length = 8, $keySpace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $pieces = [];
        $max = mb_strlen($keySpace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces[] = $keySpace[random_int(0, $max)];
        }
        return implode('', $pieces);
    }
}