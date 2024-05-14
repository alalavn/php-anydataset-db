<?php

namespace ByJG\AnyDataset\Db\Traits;

use ByJG\AnyDataset\Db\Exception\TransactionNotStartedException;
use ByJG\AnyDataset\Db\Exception\TransactionStartedException;
use ByJG\AnyDataset\Db\IsolationLevelEnum;

/**
 * Trait TransactionTrait
 * @package ByJG\AnyDataset\Db
 *
 */
trait TransactionTrait
{
    protected IsolationLevelEnum|null $isolationLevel = null;

    protected int $transactionCount = 0;

    public function beginTransaction(IsolationLevelEnum $isolationLevel = null, bool $allowJoin = false): void
    {
        if ($this->hasActiveTransaction()) {
            if (!$allowJoin) {
                throw new TransactionStartedException("There is already an active transaction");
            } else if (!empty($isolationLevel) && $this->activeIsolationLevel() != $isolationLevel) {
                throw new TransactionStartedException("You cannot join a transaction with a different isolation level");
            }
            $this->transactionCount++;
            return;
        }

        $this->logger->debug("SQL: Begin transaction");
        $isolLevelCommand = $this->getDbHelper()->getIsolationLevelCommand($isolationLevel);
        if (!empty($isolLevelCommand)) {
            $this->getInstance()->exec($isolLevelCommand);
        }
        $this->getInstance()->beginTransaction();
        $this->transactionCount = 1;
        $this->isolationLevel = $isolationLevel;
    }

    public function commitTransaction(): void
    {
        $this->logger->debug("SQL: Commit transaction");
        if (!$this->hasActiveTransaction()) {
            throw new TransactionNotStartedException("There is no active transaction");
        }
        $this->transactionCount--;
        if ($this->transactionCount > 0) {
            return;
        }
        $this->getInstance()->commit();
        $this->isolationLevel = null;
    }

    public function rollbackTransaction(): void
    {
        $this->logger->debug("SQL: Rollback transaction");
        if (!$this->hasActiveTransaction()) {
            throw new TransactionNotStartedException("There is no active transaction");
        }
        $this->getInstance()->rollBack();
        $this->isolationLevel = null;
    }

    public function remainingCommits(): int
    {
        return $this->transactionCount;
    }

    public function requiresTransaction(): void
    {
        if (!$this->hasActiveTransaction()) {
            throw new TransactionNotStartedException("A transaction is required.");
        }
    }

    public function hasActiveTransaction(): bool
    {
        return $this->remainingCommits() > 0;
    }

    public function activeIsolationLevel(): ?IsolationLevelEnum
    {
        return $this->isolationLevel;
    }

}