<?php

namespace App\Http\Controllers;

use App\Models\Coin\Coin;
use Exception;

class NodeStatusController extends Controller
{
    public function index()
    {

        $data['btcForkedCoins'] = Coin::query()
            ->where('api->selected_apis', API_BITCOIN)
            ->active()
            ->get();
        $data['title'] = __('Node Status');
        return view('node_status.node_status', $data);
    }

    public function show($coin, $api)
    {
        try {
            $response = app($api, [$coin])->getStatus();
        } catch (Exception $exception) {
            $response= [
              'status' => false,
              'version' => '',
              'network' => ''
            ];
        }

        return view('node_status.single_status', compact('response'))->render();
    }
}
