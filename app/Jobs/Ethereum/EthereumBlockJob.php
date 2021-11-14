<?php

namespace App\Jobs\Ethereum;

use App\Models\Wallet\Wallet;
use App\Override\Api\EthereumClient;
use App\Override\Logger;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EthereumBlockJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, EthereumClient;

    const FULL_TRANSACTION_OBJECT = true;

    private string $blockHash;
    private string $url;

    public function __construct(string $blockHash)
    {
        $this->queue = 'default_long';
        $this->connection = 'redis-long-running';
        $this->blockHash = $blockHash;
        $this->url = settings("ethereum_server_url") ?? "";
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $response = $this->call('eth_getBlockByHash', [$this->blockHash, EthereumBlockJob::FULL_TRANSACTION_OBJECT]);
            $response->throw();
            if ($response->successful()) {
                $transactions = collect($response['result']['transactions']);
                $toAddresses = $transactions->pluck('to')->unique()->filter();
                $fromAddresses = $transactions->pluck('from')->unique()->filter();

                //Deposit Processing
                $toWallets = Wallet::query()
                    ->whereHas('coin', function ($query) {
                        $query->where('api->selected_apis', API_ETHEREUM);
                    })
                    ->whereIn('address', $toAddresses)
                    ->get();

                foreach ($toWallets as $toWallet) {
                    ProcessEthereumDeposit::dispatch($toWallet, $transactions->where('to', $toWallet->address));
                }

                //Withdrawal Confirmation
                $fromWallets = Wallet::query()
                    ->whereHas('coin', function ($query) {
                        $query->where('api->selected_apis', API_ETHEREUM);
                    })
                    ->whereIn('address', $fromAddresses)
                    ->systemWallet()
                    ->get();

                foreach ($fromWallets as $fromWallet) {
                    ProcessEthereumWithdrawal::dispatch($fromWallet, $transactions->where('from', $fromWallet->address));
                }
            }
        } catch (Exception $exception) {
            Logger::error($exception, "[FAILED][EthereumBlockJob]");
        }
    }
}
