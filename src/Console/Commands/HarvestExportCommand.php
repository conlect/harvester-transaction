<?php

namespace Imamuseum\HarvesterTransaction\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Imamuseum\HarvesterTransaction\Models\Transaction;

class HarvestExportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'harvest:export
                            {--modified : Run export on modified transactions.}
                            {--deleted : Run export on deleted transactions.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export command.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->client = new Client();
        $this->token = config('harvester-transaction.token');
        $this->take = config('harvester-transaction.defaults.take');
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('modified')) $action = 'modified';
        if ($this->option('deleted')) $action = 'deleted';

        // make a request to the api for created and updated objects
        $response = $this->client->request('GET', config('app.url').'api/transactions/objects?action='.$action);

        // get the response from the api
        $api = json_decode($response->getBody());
        // get number of pages from api response
        $last_page = $api->meta->last_page;

        // set defaults
        $page = 1;
        $ids = [];

        // make requests to Drupal for paginated results
        while ($page <= $last_page) {
            $res = $this->client->request('GET', config('harvester-transaction.export_url'), [
                'query' => ['token' => $this->token,
                            'action' => $action,
                            'take' => $this->take,
                            'page' => $page,
                ]
            ]);
            // if response status is 200
            if ($res->getStatusCode() == 200) {
                // build array of ids to delete from transactions table
                $res_ids = json_decode($res->getBody());
                $ids = array_merge($ids, (array)$res_ids);
                $page++;
            }
        }

        // empty the transaction table based on returned ids from Drupal
        foreach ($ids as $id) {
            if ($this->option('modified')) $transactions = ['updated', 'created'];
            if ($this->option('deleted')) $transactions = ['deleted'];
            Transaction::where('table_id', '=', $id)->where('table', '=', 'objects')->whereIn('action', $transactions)->delete();
        }
    }
}
