<?php

namespace Imamuseum\HarvesterTransaction\Traits;

use Imamuseum\HarvesterTransaction\EloquentTransactionLog as Transaction;

trait TransactionLogTrait
{
    public static function bootTransactionLogTrait()
    {
        $table = (new self)->getTable();

        if (config('harvester-transaction.log')) {
            static::created(function($item) use ($table) {
                Transaction::log($table, 'created', $item->id);
            });

            static::updated(function($item) use ($table) {
                Transaction::log($table, 'updated', $item->id);
            });

            static::deleted(function($item) use ($table) {
                Transaction::log($table, 'deleted', $item->id);
            });
        }
    }
}
