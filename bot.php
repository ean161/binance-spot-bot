<?php
error_reporting(0);
date_default_timezone_set("Asia/Ho_Chi_Minh");
define("BASE_URL", "https://testnet.binance.vision");
define("API_KEY", "");
define("SECRET_KEY", "");
define("SYMBOL", "BNBUSDT");
define("HOME_COIN", "BNB");
define("LOT", 3);

$fee = 0.01;
$amount = 0;

$profitPercent = 0.2 / 100;
$isWaiting = false;

$lastSell = 0;
$balance = getUSDT();
$rootBalance = $balance;
clog("* Root balance: ".$balance);

getBalance();
while (true) {
    $balance = getUSDT();
    $price = getCurrentPrice(SYMBOL);
    $lowerMock = $buyPrice - ($buyPrice * (0.5) / 100);

    if (!$isWaiting && ((time() - $lastSell > 300) || ($price < $lowerMock))) {
        $isWaiting = true;
        $amount = number_format(5.5/$price, LOT, '.');

        $buyOrder = authPost("api/v3/order", [
            "symbol" => SYMBOL,
            "side" => "BUY",
            "type" => "MARKET",
            "quantity" => $amount
        ]);
        $buyPrice = $buyOrder["fills"][0]["price"];
        $buyBalance = $balance;
        $target = ($buyPrice * $profitPercent + $buyPrice);

        clog("\033[1;31m[BUY]\033[1;0m ".$amount);
        clog("Buy at: ".$buyPrice.", target: ".$target);

        if (!$buyPrice) {
            exit("ERROR");
        }
    } else if($isWaiting) {
        if ($price >= $target) {
            clog("Warn at: ".$price);

            $isWaiting = false;
            $lastSell = time();

            $sellOrder = authPost("api/v3/order", [
                "symbol" => SYMBOL,
                "side" => "SELL",
                "type" => "MARKET",
                "quantity" => $amount
            ]);

            clog("\033[1;32m[SELL]\033[1;0m ".$amount);
            $sellPrice = $sellOrder["fills"][0]["price"];
            clog("Sell at: ".$sellPrice);
            clog("Profit: ".(getUSDT() - $buyBalance));
            getBalance();
            clog("Total profit: ".(getUSDT() - $rootBalance));

            if (!$sellPrice) {
                exit("ERROR");
            }
        } else {
            fileLog("losePrice", $price);
        }
    }

    sleep(1);
}

function clog($data) {
    echo "[".date("H:i d/m")."] ".$data."\033[1;0m\n";
}

function getBalance() {
    clog("Balances:");
    $balances = authGet("api/v3/account", []);
    $result = ["USDT" => 0, HOME_COIN => 0];

    foreach ($balances["balances"] as $val) {
        if ($val["asset"] == "USDT" || $val["asset"] == HOME_COIN) {
            clog("\t".$val["asset"].": ".$val["free"]);
        }
    }
}

function getUSDT() {
    $balances = authGet("api/v3/account", []);
    $result = ["USDT" => 0, HOME_COIN => 0];

    foreach ($balances["balances"] as $val) {
        if ($val["asset"] == "USDT") {
            return $val["free"];
        }
    }
}

function fileLog($name, $data) {
    $fn = fopen("logs/".$name.".txt", "a+");
    fwrite($fn, $data."\n");
    fclose($fn);
}

function getCurrentPrice($symbol) {
    $ticker = json_decode(file_get_contents("https://testnet.binance.vision/api/v3/ticker/price?symbol=".$symbol), true);
    return $ticker["price"];
}

function authGet($endpoint, $querys) {
    $timestamp = round(microtime(true) * 1000);
    $querys["timestamp"] = $timestamp;
    $queryString = http_build_query($querys);
    $signature = hash_hmac('sha256', $queryString, SECRET_KEY);

    $url = BASE_URL."/".$endpoint."?".$queryString."&signature=".$signature;
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: ".API_KEY]);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $resp = curl_exec($curl);
    curl_close($curl);

    fileLog("gets", $resp);
    return json_decode($resp, true);
}

function authPost($endpoint, $data) {
    $timestamp = round(microtime(true) * 1000);
    $data["timestamp"] = $timestamp;
    $queryString = http_build_query($data);
    $signature = hash_hmac('sha256', $queryString, SECRET_KEY);

    $data["signature"] = $signature; 
    $postData = http_build_query($data);

    $url = BASE_URL."/".$endpoint;

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "X-MBX-APIKEY: ".API_KEY,
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $resp = curl_exec($curl);
    curl_close($curl);
    fileLog("posts", $resp);
    return json_decode($resp, true);
}
